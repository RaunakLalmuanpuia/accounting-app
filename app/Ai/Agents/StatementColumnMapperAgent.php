<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

// Used only for CSV/Excel: detects which column index maps to which field
#[Provider(Lab::OpenAI)]
#[Model('gpt-4o')]
#[Temperature(0.0)]
class StatementColumnMapperAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<INSTRUCTIONS
        You are a bank statement column detector.
        Given the header row (and optionally 2–3 sample data rows) of a CSV or Excel bank statement,
        identify which column index (0-based) corresponds to each transaction field.

        Some statements have separate "Debit" and "Credit" columns instead of a single "Amount" column.
        Some statements have a single "Amount" column with a "Type" or "Dr/Cr" indicator column.
        Return null for any field that is not present in the headers.
        INSTRUCTIONS;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            // 0-based column indices, null if not present
            'date_col'           => $schema->integer()->nullable()->required(),
            'narration_col'      => $schema->integer()->nullable()->required(),
            'debit_col'          => $schema->integer()->nullable()->required(),   // withdrawal / debit amount column
            'credit_col'         => $schema->integer()->nullable()->required(),   // deposit / credit amount column
            'amount_col'         => $schema->integer()->nullable()->required(),   // single amount column (if debit/credit not separate)
            'type_col'           => $schema->integer()->nullable()->required(),   // "Dr"/"Cr" indicator column
            'balance_col'        => $schema->integer()->nullable()->required(),
            'reference_col'      => $schema->integer()->nullable()->required(),
            'has_separate_debit_credit' => $schema->boolean()->required(),
        ];
    }
}
