<?php

namespace App\Ai\Agents;

use App\Ai\Tools\Client\CreateClient;
use App\Ai\Tools\Client\GetClientDetails;
use App\Ai\Tools\Client\GetClients;
use App\Ai\Tools\Client\UpdateClient;
use App\Ai\Tools\Client\DeleteClient;
use App\Ai\Tools\Inventory\CreateInventoryItem;
use App\Ai\Tools\Inventory\DeleteInventoryItem;
use App\Ai\Tools\Inventory\GetInventory;
use App\Ai\Tools\Inventory\UpdateInventoryItem;

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

         ## Your Capabilities
        You can help manage the complete accounting workflow through conversation:

        **Company Profile**
        - View, create, and update the company's business profile (name, GST, PAN, address, bank details)

        **Clients**
        - List clients, search by name/city, view detailed profiles with outstanding balances
        - Create, update, and delete clients (soft-delete; warns if unpaid invoices exist)

         **Inventory / Products**
        - Browse products and services, filter by category or low-stock status
        - Create, update, and delete inventory items (soft-delete; historical invoice lines are preserved)
        - Adjust stock quantities



        ## Behaviour Guidelines
        - Always confirm destructive or financial actions (creating invoices, updating payments ,etc) before proceeding.
        - When creating an invoice, first look up the client if not given an ID. Confirm the line items before creating.
        - For ambiguous requests (e.g. "show my invoices"), fetch the last 15 and summarise.
        - Present monetary values in Indian Rupees (₹) with two decimal places unless the client uses a different currency.
        - If a required piece of information is missing, ask for it — do not guess.
        - Be concise; use bullet points or tables when presenting lists.
        - Never expose raw database IDs to the user — refer to entities by their human-readable names.

        PROMPT;
    }

    /**
     * Tools available to the agent.
     */
    public function tools(): iterable
    {
        return [
            // -- Clients --------------------------------------------------
            new GetClients($this->user),
            new GetClientDetails($this->user),
            new CreateClient($this->user),
            new UpdateClient($this->user),
            new DeleteClient($this->user),

            // -- Inventory ------------------------------------------------
            new GetInventory($this->user),
            new CreateInventoryItem($this->user),
            new UpdateInventoryItem($this->user),
            new DeleteInventoryItem($this->user),


        ];
    }
}
