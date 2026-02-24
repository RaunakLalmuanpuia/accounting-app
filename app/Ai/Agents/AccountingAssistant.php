<?php

namespace App\Ai\Agents;

use App\Ai\Tools\CreateInvoice;
use App\Ai\Tools\FetchInvoices;
use App\Ai\Tools\GetExpenseSummary;
use App\Ai\Tools\GetInventory;
use App\Models\User;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider(Lab::OpenAI)]
#[Model('gpt-4o')]
#[MaxSteps(10)]
#[MaxTokens(4096)]
#[Temperature(0.3)]
class AccountingAssistant implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    public function __construct(public readonly User $user) {}

    /**
     * System instructions for the accounting assistant.
     */
    public function instructions(): Stringable|string
    {
        $today    = now()->toFormattedDateString();
        $userName = $this->user->name;

        return <<<PROMPT
        You are a professional AI accounting assistant for {$userName}.
        Today's date is {$today}.

        PROMPT;
    }

    /**
     * Tools available to the agent.
     */
    public function tools(): iterable
    {
        return [

        ];
    }
}
