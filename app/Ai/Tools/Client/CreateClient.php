<?php

namespace App\Ai\Tools\Client;

use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CreateClient implements Tool
{
    public function __construct(protected User $user) {}

    public function description(): Stringable|string
    {
        return 'Create a new client/customer for the user\'s company. At minimum, a name is required.';
    }

    public function handle(Request $request): Stringable|string
    {
        $company = $this->user->companies()->first();

        if (! $company) {
            return json_encode(['success' => false, 'message' => 'Please set up your company profile first.']);
        }

        // Duplicate guard
        $exists = $company->clients()
            ->where('name', $request['name'])
            ->exists();

        if ($exists) {
            return json_encode([
                'success' => false,
                'message' => "A client named \"{$request['name']}\" already exists.",
            ]);
        }

        $client = $company->clients()->create([
            'name'          => $request['name'],
            'email'         => $request['email']         ?? null,
            'phone'         => $request['phone']         ?? null,
            'gst_number'    => $request['gst_number']    ?? null,
            'pan_number'    => $request['pan_number']    ?? null,
            'gst_type'      => $request['gst_type']      ?? null,
            'address'       => $request['address']       ?? null,
            'city'          => $request['city']          ?? null,
            'state'         => $request['state']         ?? null,
            'state_code'    => $request['state_code']    ?? null,
            'pincode'       => $request['pincode']       ?? null,
            'country'       => $request['country']       ?? 'India',
            'currency'      => $request['currency']      ?? 'INR',
            'payment_terms' => $request['payment_terms'] ?? null,
            'credit_limit'  => $request['credit_limit']  ?? null,
            'notes'         => $request['notes']         ?? null,
            'is_active'     => true,
        ]);

        return json_encode([
            'success'     => true,
            'message'     => "Client \"{$client->name}\" created successfully.",
            'client_id'   => $client->id,
            'client_name' => $client->name,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name'          => $schema->string()->required(),
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
        ];
    }
}
