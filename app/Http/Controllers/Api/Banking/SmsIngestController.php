<?php

namespace App\Http\Controllers\Api\Banking;

use App\Actions\Banking\IngestSmsTransactionAction;
use App\Constants\ApiResponseType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Banking\SmsIngestRequest;
use App\Models\BankAccount;
use Illuminate\Http\JsonResponse;

class SmsIngestController extends Controller
{
    public function __construct(private IngestSmsTransactionAction $action) {}

    /**
     * POST /api/banking/transactions/sms
     */
    public function __invoke(SmsIngestRequest $request): JsonResponse
    {
        $account     = BankAccount::findOrFail($request->bank_account_id);
        $transaction = $this->action->execute($request->raw_sms, $account);

        return response()->json([
            'status'=>ApiResponseType::SUCCESS,
            'message'     => 'SMS ingested successfully.',
            'transaction' => $transaction,
        ], 201);
    }
}
