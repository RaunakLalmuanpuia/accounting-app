<?php

namespace App\Http\Controllers\Api\Banking;

use App\Http\Controllers\Controller;
use App\Models\BankTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankTransactionController extends Controller
{
    /**
     * GET /api/banking/transactions
     */
    public function index(Request $request): JsonResponse
    {
        $transactions = BankTransaction::with(['narrationHead', 'narrationSubHead'])
            ->when($request->bank_account_id, fn($q, $v) => $q->where('bank_account_id', $v))
            ->when($request->review_status,   fn($q, $v) => $q->where('review_status', $v))
            ->when($request->type,             fn($q, $v) => $q->where('type', $v))
            ->orderByDesc('transaction_date')
            ->paginate(25);

        return response()->json($transactions);
    }

    /**
     * GET /api/banking/transactions/{bankTransaction}
     */
    public function show(BankTransaction $bankTransaction): JsonResponse
    {
        $bankTransaction->load(['narrationHead', 'narrationSubHead', 'bankAccount']);
        return response()->json($bankTransaction);
    }

    /**
     * GET /api/banking/transactions/pending
     */
    public function pending(Request $request): JsonResponse
    {
//        dd($request);
        $transactions = BankTransaction::with(['narrationHead', 'narrationSubHead'])
            ->where('review_status', 'pending')
            ->when($request->bank_account_id, fn($q, $v) => $q->where('bank_account_id', $v))
            ->orderByDesc('transaction_date')
            ->paginate(25);

        return response()->json($transactions);
    }
}
