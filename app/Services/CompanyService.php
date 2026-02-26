<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;

class CompanyService
{
    public function __construct(protected User $user) {}

    public function getCompany(): ?Company
    {
        return $this->user->companies()->first();
    }

    public function hasCompany(): bool
    {
        return $this->user->companies()->exists();
    }

    public function createCompany(array $data): Company
    {
        return $this->user->companies()->create($data);
    }

    public function updateCompany(Company $company, array $data): bool
    {
        return $company->update($data);
    }

    public function getUpdatableFields(): array
    {
        return [
            'company_name', 'gst_number', 'pan_number',
            'state', 'state_code', 'address', 'city', 'pincode', 'country',
            'email', 'phone', 'website',
            'bank_account_name', 'bank_account_number', 'bank_ifsc_code',
            'bank_name', 'bank_branch', 'invoice_footer_note', 'is_active',
        ];
    }

    public function extractUpdates(array $input): array
    {
        $updates = [];
        foreach ($this->getUpdatableFields() as $field) {
            if (array_key_exists($field, $input)) {
                $updates[$field] = $input[$field];
            }
        }
        return $updates;
    }

    public function formatCompany(Company $company): array
    {
        return [
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
        ];
    }
}
