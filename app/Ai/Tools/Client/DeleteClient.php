<?php

namespace App\Ai\Tools\Client;

use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class DeleteClient implements Tool
{
    public function __construct(protected User $user) {}

    public function description(): Stringable|string
    {
        return 'Delete (soft-delete) a client from the company. Requires the client ID or name. The client\'s invoice history is preserved. Warns if unpaid invoices exist — set force=true to override.';
    }


    public function handle(Request $request): Stringable|string
    {
        $company = $this->user->companies()->first();

        if (! $company) {
            return json_encode(['success' => false, 'message' => 'No company profile found.']);
        }

        $query = $company->clients();

        if (! empty($request['client_id'])) {
            $query->where('id', $request['client_id']);
        } elseif (! empty($request['name'])) {
            $query->where('name', 'like', '%' . $request['name'] . '%');
        } else {
            return json_encode(['success' => false, 'message' => 'Provide either client_id or name.']);
        }

        $client = $query->first();

        if (! $client) {
            return json_encode(['success' => false, 'message' => 'Client not found.']);
        }

        // Warn about outstanding invoices
        $outstanding = $client->invoices()
            ->whereIn('status', ['sent', 'partial', 'overdue'])
            ->count();

        if ($outstanding > 0 && empty($request['force'])) {
            return json_encode([
                'success'                => false,
                'requires_confirmation'  => true,
                'message'                => "Client \"{$client->name}\" has {$outstanding} unpaid invoice(s). Set force=true to delete anyway.",
            ]);
        }

        $name = $client->name;
        $client->delete();

        return json_encode([
            'success' => true,
            'message' => "Client \"{$name}\" has been deleted successfully.",
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'client_id' => $schema->integer(),
            'name'      => $schema->string(),
            'force'     => $schema->boolean(),
        ];
    }
}
