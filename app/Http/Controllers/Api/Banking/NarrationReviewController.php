<?php

namespace App\Http\Controllers\Api\Banking;

use App\Actions\Banking\ReviewNarrationAction;
use App\Constants\ApiResponseType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Banking\NarrationReviewRequest;
use App\Models\BankTransaction;
use Illuminate\Http\JsonResponse;

class NarrationReviewController extends Controller
{
    public function __construct(private ReviewNarrationAction $action) {}

    /**
     * POST /api/banking/transactions/{transaction}/review/{action}
     */
    public function handle(NarrationReviewRequest $request, BankTransaction $transaction, string $action): JsonResponse
    {
        $result = match ($action) {
            'approve' => $this->action->approve($transaction),

            'correct' => $this->action->correct(
                transaction:        $transaction,
                narrationSubHeadId: (int) $request->narration_sub_head_id,
                narrationNote:      $request->narration_note,
                partyName:          $request->party_name,
                saveAsRule:         (bool) $request->input('save_as_rule', false),
            ),

            'reject'  => $this->action->reject($transaction),

            default   => abort(422, 'Invalid action. Use: approve, correct, reject'),
        };

        return response()->json([
            'status'=>ApiResponseType::SUCCESS,
            'message'     => "Transaction {$action}d successfully.",
            'transaction' => $result,
        ]);
    }
}
