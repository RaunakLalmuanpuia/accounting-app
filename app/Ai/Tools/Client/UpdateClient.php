<?php

namespace App\Ai\Tools\Client;

use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class UpdateClient implements Tool
{
    public function __construct(protected User $user) {}

    public function description(): Stringable|string
    {
        return 'Update details for an existing client. Provide the client_id and any fields to change. Only specified fields are updated.';
    }

    public function handle(Request $request): Stringable|string
    {
        $company = $this->user->companies()->first();

        if (! $company) {
            return json_encode(['success' => false, 'message' => 'No company profile found.']);
        }

        $client = $company->clients()->find($request['client_id']);

        if (! $client) {
            return json_encode(['success' => false, 'message' => 'Client not found.']);
        }

        $updatable = [
            'name', 'email', 'phone', 'gst_number', 'pan_number', 'gst_type',
            'address', 'city', 'state', 'state_code', 'pincode', 'country',
            'currency', 'payment_terms', 'credit_limit', 'notes', 'is_active',
        ];

        $updates = [];
        foreach ($updatable as $field) {
            if (isset($request[$field])) {
                $updates[$field] = $request[$field];
            }
        }

        if (empty($updates)) {
            return json_encode(['success' => false, 'message' => 'No fields provided to update.']);
        }

        $client->update($updates);

        return json_encode([
            'success'        => true,
            'message'        => "Client \"{$client->name}\" updated successfully.",
            'updated_fields' => array_keys($updates),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'client_id'     => $schema->integer()->required(),
            'name'          => $schema->string(),
            'email'         => $schema->string(),
            'phone'         => $schema->string(),
            'gst_number'    => $schema->string(),
            'pan_number'    => $schema->string(),
            'gst_type'      => $schema->string()->enum(['unregistered', 'regular', 'composition', 'consumer']),
            'address'       => $schema->string(),
            'city'          => $schema->string(),
            'state'         => $schema->string(),
            'state_code'    => $schema->string(),
            'pincode'       => $schema->string(),
            'country'       => $schema->string(),
            'currency'      => $schema->string(),
            'payment_terms' => $schema->integer(),
            'credit_limit'  => $schema->number(),
            'notes'         => $schema->string(),
            'is_active'     => $schema->boolean(),
        ];
    }
}
