<?php

namespace App\Http\Controllers\Api\Banking;

use App\Actions\Banking\ProcessStatementAction;
use App\Constants\ApiResponseType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Banking\StatementUploadRequest;
use App\Models\BankAccount;
use Illuminate\Http\JsonResponse;

class StatementUploadController extends Controller
{
    public function __construct(private ProcessStatementAction $action) {}

    /**
     * POST /api/banking/transactions/statement
     *
     * Accepts multipart/form-data with:
     *   - bank_account_id  (integer)
     *   - statement        (file: pdf | csv | xlsx | xls)
     */
    public function __invoke(StatementUploadRequest $request): JsonResponse
    {
        $account = BankAccount::findOrFail($request->bank_account_id);

        // TODO: authorize($account->company_id === auth()->user()->company_id)

        $result = $this->action->execute($request->file('statement'), $account);

        $statusCode = $result['imported'] > 0 ? 201 : 200;

        return response()->json([
            'status'=>ApiResponseType::SUCCESS,
            'message'    => $this->buildMessage($result),
            'batch_id'   => $result['batch_id'],
            'summary'    => [
                'total'      => $result['total'],
                'imported'   => $result['imported'],
                'duplicates' => $result['duplicates'],
                'failed'     => $result['failed'],
            ],
            'transactions' => $result['transactions'],
        ], $statusCode);
    }

    private function buildMessage(array $result): string
    {
        return sprintf(
            'Statement processed: %d imported, %d duplicates skipped, %d failed out of %d total transactions.',
            $result['imported'],
            $result['duplicates'],
            $result['failed'],
            $result['total']
        );
    }
}
