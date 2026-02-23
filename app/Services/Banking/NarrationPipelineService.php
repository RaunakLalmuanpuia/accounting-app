<?php

namespace App\Services\Banking;

use App\Ai\Agents\SmsParserAgent;
use App\DTOs\Banking\ParsedTransactionDTO;
use App\Models\BankAccount;
use App\Models\BankTransaction;

class NarrationPipelineService
{
    public function __construct(
        private NarrationRuleEngine $ruleEngine,
        private NarrationAiService  $aiService,
    ) {}

    /**
     * Parse raw SMS text and run the full narration pipeline.
     */
    public function processFromSms(string $rawSms, BankAccount $account): BankTransaction
    {
        // Step 1: Parse SMS via AI agent (structured output)
        $response = SmsParserAgent::make()->prompt("Parse this bank SMS:\n\n{$rawSms}");

        $dto = ParsedTransactionDTO::fromArray([
//            'raw_narration'    => $response['raw_narration'] ?? $rawSms,
            'raw_narration'    => $rawSms, // Store the raw sms
            'type'             => $response['type'],
            'amount'           => $response['amount'],
            'bank_reference'   => $response['bank_reference'] ?? '',
            'party_name'       => $response['party_name'] ?? null,
            'transaction_date' => $response['transaction_date'],
            'balance_after'    => $response['balance_after'] ?? null,
            'bank_name'        => $response['bank_name'] ?? null,
        ]);

        return $this->process($dto, $account);
    }

    /**
     * Run the narration pipeline on an already-parsed transaction DTO.
     * Used by the statement upload flow.
     */
    public function process(ParsedTransactionDTO $dto, BankAccount $account): BankTransaction
    {
        $companyId = $account->company_id;

        // ── Dedup check ───────────────────────────────────────────────────
        $hash = BankTransaction::makeDedupHash(
            $dto->transactionDate->toDateString(),
            $dto->amount,
            $dto->type,
            $dto->bankReference
        );

        $isDuplicate = BankTransaction::where('bank_account_id', $account->id)
            ->where('dedup_hash', $hash)
            ->exists();

        // ── Tier 1: Rule engine ───────────────────────────────────────────
        $suggestion = $this->ruleEngine->match(
            $dto->rawNarration,
            $dto->type,
            $dto->amount,
            $companyId
        );

        // ── Tier 2: AI fallback ───────────────────────────────────────────
        if (!$suggestion) {
            $suggestion = $this->aiService->suggest(
                $dto->rawNarration,
                $dto->type,
                $dto->amount,
                $dto->transactionDate->toDateString(),
                $companyId
            );
        }

        // ── Persist ───────────────────────────────────────────────────────
        // Rule matches go straight to 'reviewed'; AI suggestions need human review
//        $reviewStatus = $suggestion->source === 'rule' ? 'reviewed' : 'pending';

        $reviewStatus = 'pending';

        $transaction = BankTransaction::create([
            'bank_account_id'       => $account->id,
            'transaction_date'      => $dto->transactionDate,
            'bank_reference'        => $dto->bankReference,
            'raw_narration'         => $dto->rawNarration,
            'type'                  => $dto->type,
            'amount'                => $dto->amount,
            'balance_after'         => $dto->balanceAfter,
            'narration_head_id'     => $suggestion->narrationHeadId,
            'narration_sub_head_id' => $suggestion->narrationSubHeadId,
            'narration_note'        => $suggestion->narrationNote,
            'party_name'            => $suggestion->partyName ?? $dto->partyName,
            'narration_source'      => $suggestion->source,
            'ai_confidence'         => $suggestion->confidence,
            'ai_suggestions'        => $suggestion->aiSuggestions,
            'ai_metadata'           => $suggestion->aiMetadata,
            'review_status'         => $reviewStatus,
            'applied_rule_id'       => $suggestion->appliedRuleId,
            'dedup_hash'            => $hash,
            'is_duplicate'          => $isDuplicate,
            'import_source'         => 'sms',
        ]);

        if ($dto->balanceAfter) {
            $account->update(['current_balance' => $dto->balanceAfter]);
        }

        return $transaction;
    }
}
