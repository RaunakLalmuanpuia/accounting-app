<?php

namespace App\Ai\Tools\Client;

use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetClientDetails implements Tool
{
    public function __construct(protected User $user) {}

    public function description(): Stringable|string
    {
        return 'Get full details for a specific client including contact info, GST/PAN, bank details, payment terms, outstanding balance, and recent invoices. Requires the client\'s ID or name.';
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

        // Recent invoices summary
        $recentInvoices = $client->invoices()
            ->latest()
            ->limit(5)
            ->get(['id', 'invoice_number', 'invoice_date', 'due_date', 'amount_due', 'status'])
            ->toArray();

        return json_encode([
            'success' => true,
            'client'  => [
                'id'              => $client->id,
                'name'            => $client->name,
                'email'           => $client->email,
                'phone'           => $client->phone,
                'gst_number'      => $client->gst_number,
                'pan_number'      => $client->pan_number,
                'gst_type'        => $client->gst_type,
                'address'         => $client->address,
                'city'            => $client->city,
                'state'           => $client->state,
                'state_code'      => $client->state_code,
                'pincode'         => $client->pincode,
                'country'         => $client->country,
                'currency'        => $client->currency,
                'payment_terms'   => $client->payment_terms,
                'credit_limit'    => $client->credit_limit,
                'notes'           => $client->notes,
                'is_active'       => $client->is_active,
                'recent_invoices' => $recentInvoices,
            ],
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'client_id' => $schema->integer(),
            'name'      => $schema->string(),
        ];
    }
}
