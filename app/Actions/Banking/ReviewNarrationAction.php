<?php

namespace App\Actions\Banking;

use App\Models\BankTransaction;
use App\Models\NarrationRule;
use App\Models\NarrationSubHead;
use Illuminate\Support\Facades\DB;

class ReviewNarrationAction
{
    public function approve(BankTransaction $transaction): BankTransaction
    {
        $transaction->update(['review_status' => 'reviewed']);
        return $transaction->fresh(['narrationHead', 'narrationSubHead']);
    }

    public function correct(
        BankTransaction $transaction,
        int    $narrationSubHeadId,
        ?string $narrationNote = null,
        ?string $partyName     = null,
        bool   $saveAsRule     = false,
    ): BankTransaction {
        return DB::transaction(function () use (
            $transaction, $narrationSubHeadId, $narrationNote, $partyName, $saveAsRule
        ) {
            $subHead = NarrationSubHead::findOrFail($narrationSubHeadId);

            $transaction->update([
                'narration_head_id'     => $subHead->narration_head_id,
                'narration_sub_head_id' => $subHead->id,
                'narration_note'        => $narrationNote,
                'party_name'            => $partyName ?? $transaction->party_name,
                'narration_source'      => 'manual',
                'review_status'         => 'reviewed',
            ]);

            if ($saveAsRule && strlen($transaction->raw_narration) >= 4) {
                $this->createLearningRule($transaction, $subHead->narration_head_id, $subHead->id);
            }

            return $transaction->fresh(['narrationHead', 'narrationSubHead']);
        });
    }

    public function reject(BankTransaction $transaction): BankTransaction
    {
        $transaction->update([
            'review_status'         => 'flagged',
            'narration_head_id'     => null,
            'narration_sub_head_id' => null,
            'narration_note'        => null,
        ]);
        return $transaction->fresh();
    }

    private function createLearningRule(BankTransaction $transaction, int $headId, int $subHeadId): NarrationRule
    {
        $matchValue = strtolower(substr($transaction->raw_narration, 0, 30));

        return NarrationRule::updateOrCreate(
            [
                'company_id'  => $transaction->bankAccount->company_id,
                'match_value' => $matchValue,
                'match_type'  => 'contains',
            ],
            [
                'transaction_type'      => $transaction->type,
                'narration_head_id'     => $headId,
                'narration_sub_head_id' => $subHeadId,
                'priority'              => 10,
                'is_active'             => true,
                'source'                => 'learned',
            ]
        );
    }
}
