<?php

namespace App\Ai\Tools\Client;

use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetClients implements Tool
{
    public function __construct(protected User $user) {}

    public function description(): Stringable|string
    {
        return 'List the clients belonging to the user\'s company. Supports optional filtering by name, city, state, or active status, and pagination.';
    }

    public function handle(Request $request): Stringable|string
    {
        $company = $this->user->companies()->first();

        if (! $company) {
            return json_encode(['success' => false, 'message' => 'No company profile found.']);
        }

        $query = $company->clients();

        if (! empty($request['search'])) {
            $search = $request['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            });
        }

        if (isset($request['is_active'])) {
            $query->where('is_active', (bool) $request['is_active']);
        }

        $perPage = min((int) ($request['per_page'] ?? 20), 50);
        $page    = max((int) ($request['page']     ?? 1), 1);

        $total   = $query->count();
        $clients = $query->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get(['id', 'name', 'email', 'phone', 'city', 'state', 'gst_number', 'currency', 'is_active']);

        return json_encode([
            'success' => true,
            'total'   => $total,
            'page'    => $page,
            'clients' => $clients->toArray(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'search'    => $schema->string(),
            'is_active' => $schema->boolean(),
            'page'      => $schema->integer()->min(1),
            'per_page'  => $schema->integer()->min(1)->max(50),
        ];
    }
}
