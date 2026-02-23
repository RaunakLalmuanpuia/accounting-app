<?php

namespace App\Services\Banking;

use App\Ai\Agents\StatementColumnMapperAgent;
use App\Ai\Agents\StatementPdfParserAgent;
use App\DTOs\Banking\ParsedTransactionDTO;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Files;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class StatementParserService
{
    /**
     * Parse an uploaded bank statement file into a collection of ParsedTransactionDTOs.
     *
     * @return Collection<ParsedTransactionDTO>
     */
    public function parse(UploadedFile $file): Collection
    {
        return match (strtolower($file->getClientOriginalExtension())) {
            'pdf'         => $this->parsePdf($file),
            'csv'         => $this->parseCsv($file),
            'xlsx', 'xls' => $this->parseExcel($file),
            default       => throw ValidationException::withMessages([
                'statement' => 'Unsupported file type. Please upload a PDF, CSV, or Excel file.',
            ]),
        };
    }

    // ── PDF ────────────────────────────────────────────────────────────────

    /**
     * Pass the PDF directly to GPT-4o as an attachment.
     * GPT-4o reads the PDF visually and extracts all rows.
     *
     * @return Collection<ParsedTransactionDTO>
     */
    private function parsePdf(UploadedFile $file): Collection
    {
        $response = StatementPdfParserAgent::make()->prompt(
            'Extract all transaction rows from this bank statement PDF.',
            attachments: [$file],
        );

        // Clean up potential markdown formatting just in case the AI ignores the instruction
        $jsonString = str_replace(['```json', '```'], '', $response->text);

        // Decode the string into an associative array
        $parsed = json_decode(trim($jsonString), true);

        $rows = $parsed['transactions'] ?? [];

        if (empty($rows)) {
            throw ValidationException::withMessages([
                'statement' => 'No transactions could be extracted from the PDF. Please check the file.',
            ]);
        }

        return collect($rows)->map(fn(array $row) => $this->rowToDto($row));
    }

    // ── CSV ────────────────────────────────────────────────────────────────

    /**
     * @return Collection<ParsedTransactionDTO>
     */
    private function parseCsv(UploadedFile $file): Collection
    {
        $lines = array_filter(
            array_map('str_getcsv', file($file->getRealPath())),
            fn($row) => count(array_filter($row)) > 0   // skip blank lines
        );

        return $this->parseTabularData(array_values($lines));
    }

    // ── Excel ──────────────────────────────────────────────────────────────

    /**
     * @return Collection<ParsedTransactionDTO>
     */
    private function parseExcel(UploadedFile $file): Collection
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet       = $spreadsheet->getActiveSheet();

        $rows = [];
        foreach ($sheet->getRowIterator() as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            $rowData = [];
            foreach ($cellIterator as $cell) {
                $value = $cell->getValue();

                // Convert Excel date serial numbers to real dates
                if (is_numeric($value) && $cell->getDataType() === 'n') {
                    try {
                        $value = ExcelDate::excelToDateTimeObject($value)->format('Y-m-d');
                    } catch (\Exception) {
                        // Not a date serial — keep raw value
                    }
                }

                $rowData[] = $value !== null ? trim((string) $value) : '';
            }

            if (array_filter($rowData)) {   // skip completely empty rows
                $rows[] = $rowData;
            }
        }

        return $this->parseTabularData($rows);
    }

    // ── Shared tabular logic (CSV + Excel) ─────────────────────────────────

    /**
     * Use AI to detect columns from the header, then map all data rows.
     *
     * @return Collection<ParsedTransactionDTO>
     */
    private function parseTabularData(array $rows): Collection
    {
        if (count($rows) < 2) {
            throw ValidationException::withMessages([
                'statement' => 'The file has too few rows to parse.',
            ]);
        }

        // ── Step 1: Ask AI to detect column positions ──────────────────────
        $header      = $rows[0];
        $sampleRows  = array_slice($rows, 1, min(3, count($rows) - 1));

        $prompt = "Header row:\n" . json_encode($header) .
            "\n\nSample data rows:\n" . json_encode($sampleRows) .
            "\n\nDetect the column mapping for this bank statement.";

        $map = StatementColumnMapperAgent::make()->prompt($prompt);

        // ── Step 2: Map each data row to a DTO ────────────────────────────
        $dataRows = array_slice($rows, 1);

        return collect($dataRows)
            ->filter(fn($row) => $this->isDataRow($row, $map))
            ->map(fn($row)    => $this->tabularRowToDto($row, $map))
            ->filter()          // remove any nulls from unparseable rows
            ->values();
    }

    /**
     * Skip rows that look like subtotals, headers repeated mid-file, or blank.
     */
    private function isDataRow(array $row, mixed $map): bool
    {
        $dateCol = $map['date_col'];
        if ($dateCol === null || !isset($row[$dateCol])) {
            return false;
        }

        $dateVal = trim((string) $row[$dateCol]);

        // Skip if the date cell looks like a header string
        if (empty($dateVal) || preg_match('/^(date|txn|value|posting)/i', $dateVal)) {
            return false;
        }

        return true;
    }

    /**
     * Map a single tabular row to a ParsedTransactionDTO using the AI-detected column map.
     */
    private function tabularRowToDto(array $row, mixed $map): ?ParsedTransactionDTO
    {
        try {
            $dateStr     = $this->cell($row, $map['date_col']);
            $rawNarration = $this->cell($row, $map['narration_col']);
            $reference   = $this->cell($row, $map['reference_col']);
            $balance     = $this->parseAmount($this->cell($row, $map['balance_col']));

            // Resolve type + amount from columns
            if ($map['has_separate_debit_credit']) {
                $debitVal  = $this->parseAmount($this->cell($row, $map['debit_col']));
                $creditVal = $this->parseAmount($this->cell($row, $map['credit_col']));

                if ($debitVal > 0) {
                    $type   = 'debit';
                    $amount = $debitVal;
                } elseif ($creditVal > 0) {
                    $type   = 'credit';
                    $amount = $creditVal;
                } else {
                    return null;    // both zero — skip row
                }
            } else {
                $amount    = $this->parseAmount($this->cell($row, $map['amount_col']));
                $typeRaw   = strtolower(trim($this->cell($row, $map['type_col']) ?? ''));
                $type      = str_contains($typeRaw, 'cr') ? 'credit' : 'debit';
            }

            if ($amount <= 0) {
                return null;
            }

            return ParsedTransactionDTO::fromArray([
                'raw_narration'    => $rawNarration ?: 'Unknown',
                'type'             => $type,
                'amount'           => $amount,
                'bank_reference'   => $reference ?? '',
                'party_name'       => null,
                'transaction_date' => $this->parseDate($dateStr),
                'balance_after'    => $balance,
                'bank_name'        => null,
            ]);
        } catch (\Throwable) {
            return null;    // unparseable row — silently skip
        }
    }

    /**
     * Convert a PDF-parsed row array (already structured by AI) to a DTO.
     */
    private function rowToDto(array $row): ParsedTransactionDTO
    {
        return ParsedTransactionDTO::fromArray([
            'raw_narration'    => $row['raw_narration'] ?? 'Unknown',
            'type'             => $row['type'],
            'amount'           => $row['amount'],
            'bank_reference'   => $row['bank_reference'] ?? '',
            'party_name'       => $row['party_name'] ?? null,
            'transaction_date' => $row['date'],
            'balance_after'    => $row['balance_after'] ?? null,
            'bank_name'        => null,
        ]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function cell(array $row, ?int $col): ?string
    {
        if ($col === null || !isset($row[$col])) {
            return null;
        }
        return trim((string) $row[$col]);
    }

    private function parseAmount(?string $val): float
    {
        if ($val === null || $val === '') {
            return 0.0;
        }
        // Strip currency symbols, commas, spaces: "₹ 1,23,456.78" → "123456.78"
        $cleaned = preg_replace('/[^\d.]/', '', $val);
        return (float) $cleaned;
    }

    private function parseDate(string $dateStr): string
    {
        try {
            return Carbon::parse($dateStr)->format('Y-m-d');
        } catch (\Exception) {
            return now()->format('Y-m-d');
        }
    }
}
