<?php

namespace Tests\Feature\Banking;

use App\Ai\Agents\SmsParserAgent;
use App\Ai\Agents\NarrationSuggestionAgent;
use App\Models\BankAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmsIngestTest extends TestCase
{
    use RefreshDatabase;

    public function test_sms_is_parsed_and_narrated(): void
    {
        $account = BankAccount::factory()->create();

        SmsParserAgent::fake([
            [
                'type'             => 'debit',
                'amount'           => 15000.00,
                'bank_reference'   => '123456789',
                'party_name'       => 'HDFC Bank',
                'transaction_date' => '2025-02-22',
                'balance_after'    => 82450.00,
                'bank_name'        => 'SBI',
                'raw_narration'    => 'NEFT transfer to HDFC Bank',
            ],
        ]);

        NarrationSuggestionAgent::fake([
            [
                'narration_head_name'     => 'Bank Transfer',
                'narration_sub_head_name' => 'NEFT Outward',
                'narration_note'          => 'NEFT transfer to HDFC Bank',
                'party_name'              => 'HDFC Bank',
                'confidence'              => 0.92,
                'reasoning'               => 'NEFT keyword and debit type match',
                'alternatives'            => [],
            ],
        ]);

        $response = $this->postJson('/api/banking/transactions/sms', [
            'bank_account_id' => $account->id,
            'raw_sms'         => 'Your A/c XX1234 is debited with INR 15,000.00...',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('transaction.type', 'debit')
            ->assertJsonPath('transaction.amount', '15000.00')
            ->assertJsonPath('transaction.review_status', 'suggested')
            ->assertJsonPath('transaction.narration_source', 'ai');
    }
}
