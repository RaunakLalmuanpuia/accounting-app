<?php

namespace App\Ai\Tools\Company;

use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class UpdateCompany implements Tool
{
    public function __construct(protected User $user) {}

    public function description(): Stringable|string
    {
        return 'Update one or more fields of the current user\'s company profile. Only the fields provided will be changed — all others remain intact.';
    }

    public function handle(Request $request): Stringable|string
    {
        $company = $this->user->companies()->first();

        if (! $company) {
            return json_encode([
                'success' => false,
                'message' => 'No company profile found. Please create one first.',
            ]);
        }

        $updatable = [
            'company_name', 'gst_number', 'pan_number',
            'state', 'state_code', 'address', 'city', 'pincode', 'country',
            'email', 'phone', 'website',
            'bank_account_name', 'bank_account_number', 'bank_ifsc_code',
            'bank_name', 'bank_branch', 'invoice_footer_note', 'is_active',
        ];

        $updates = [];
        foreach ($updatable as $field) {
            if (isset($request[$field])) {
                $updates[$field] = $request[$field];
            }
        }

        if (empty($updates)) {
            return json_encode(['success' => false, 'message' => 'No fields were provided to update.']);
        }

        $company->update($updates);

        return json_encode([
            'success'        => true,
            'message'        => 'Company profile updated successfully.',
            'updated_fields' => array_keys($updates),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'company_name'        => $schema->string(),
            'gst_number'          => $schema->string(),
            'pan_number'          => $schema->string(),
            'state'               => $schema->string(),
            'state_code'          => $schema->string(),
            'address'             => $schema->string(),
            'city'                => $schema->string(),
            'pincode'             => $schema->string(),
            'country'             => $schema->string(),
            'email'               => $schema->string(),
            'phone'               => $schema->string(),
            'website'             => $schema->string(),
            'bank_account_name'   => $schema->string(),
            'bank_account_number' => $schema->string(),
            'bank_ifsc_code'      => $schema->string(),
            'bank_name'           => $schema->string(),
            'bank_branch'         => $schema->string(),
            'invoice_footer_note' => $schema->string(),
            'is_active'           => $schema->boolean(),
        ];
    }
}
