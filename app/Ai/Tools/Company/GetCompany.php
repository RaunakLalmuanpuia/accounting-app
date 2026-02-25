<?php

namespace App\Ai\Tools\Company;

use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetCompany implements Tool
{
    public function __construct(protected User $user) {}

    public function description(): Stringable|string
    {
        return 'Retrieve the current user\'s company profile including name, GST number, PAN, address, bank details, and contact information.';
    }

    public function handle(Request $request): Stringable|string
    {
        $company = $this->user->companies()->first();

        if (! $company) {
            return json_encode([
                'found'   => false,
                'message' => 'No company profile found. You can create one by providing the necessary details.',
            ]);
        }

        return json_encode([
            'found'   => true,
            'company' => [
                'id'                  => $company->id,
                'company_name'        => $company->company_name,
                'gst_number'          => $company->gst_number,
                'pan_number'          => $company->pan_number,
                'state'               => $company->state,
                'state_code'          => $company->state_code,
                'address'             => $company->address,
                'city'                => $company->city,
                'pincode'             => $company->pincode,
                'country'             => $company->country,
                'email'               => $company->email,
                'phone'               => $company->phone,
                'website'             => $company->website,
                'bank_account_name'   => $company->bank_account_name,
                'bank_account_number' => $company->bank_account_number,
                'bank_ifsc_code'      => $company->bank_ifsc_code,
                'bank_name'           => $company->bank_name,
                'bank_branch'         => $company->bank_branch,
                'invoice_footer_note' => $company->invoice_footer_note,
                'is_active'           => $company->is_active,
                'created_at'          => $company->created_at?->toDateString(),
            ],
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
