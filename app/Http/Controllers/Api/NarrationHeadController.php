<?php
// php artisan make:controller Api/NarrationHeadController --api
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NarrationHead;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NarrationHeadController extends Controller
{
    /**
     * Get all narration heads with their active sub-heads
     * GET /api/narration-heads
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'company_id' => 'required|integer|exists:companies,id',
            'type'       => 'sometimes|string|in:debit,credit,both',
            'with_subs'  => 'sometimes|boolean',
        ]);

        $query = NarrationHead::query()->active()
            ->forCompany($request->integer('company_id')); // now required from form data

        if ($request->filled('type')) {
            $query->forTransactionType($request->string('type'));
        }

        if ($request->boolean('with_subs', true)) {
            $query->with('activeSubHeads');
        }

        $heads = $query->orderBy('sort_order')->get();

        return response()->json([
            'success' => true,
            'data'    => $heads,
            'total'   => $heads->count(),
        ]);
    }

    /**
     * Get a single narration head with its sub-heads
     * GET /api/narration-heads/{id}
     */
    public function show(int $id): JsonResponse
    {
        $head = NarrationHead::with('activeSubHeads')
            ->active()
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $head,
        ]);
    }

    /**
     * Get only sub-heads for a specific narration head
     * GET /api/narration-heads/{id}/sub-heads
     */
    public function subHeads(int $id): JsonResponse
    {
        $head = NarrationHead::active()->findOrFail($id);

        $subHeads = $head->activeSubHeads()->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'head'      => $head->only(['id', 'name', 'slug', 'type', 'color', 'icon']),
                'sub_heads' => $subHeads,
                'total'     => $subHeads->count(),
            ],
        ]);
    }
}
