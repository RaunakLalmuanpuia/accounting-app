<?php

namespace App\Http\Controllers\Banking;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\NarrationHead;
use App\Models\NarrationSubHead;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BankTransactionController extends Controller
{
    public function pending(Request $request): Response
    {
        $user = auth()->user();

        // ✅ Get user's first company
        $company = $user->companies()
            ->orderBy('id')
            ->first();

        if (!$company) {
            abort(404, 'No company found for this user.');
        }

        // ✅ Get first bank account of that company
        $bankAccountId = $company->bankAccounts()
            ->orderBy('id')
            ->value('id');

        if (!$bankAccountId) {
            abort(404, 'No bank account found for this company.');
        }

        $transactions = BankTransaction::with(['narrationHead', 'narrationSubHead'])
            ->where('bank_account_id', $bankAccountId)
            ->where('is_duplicate', false)
            ->whereIn('review_status', ['pending', 'reviewed'])
            ->orderByDesc('transaction_date')
            ->paginate(25);


        // Inside pending():
        $bankAccounts = BankAccount::where('company_id', $company->id)->get(); // Adjust scope as needed


        $heads = NarrationHead::with('activeSubHeads')
            ->forCompany($company->id)   // 🔥 IMPORTANT LINE
            ->active()
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Banking/PendingReviews', [
            'transactions' => $transactions,
            'heads'        => $heads,
            'bankAccounts' => $bankAccounts,
        ]);
    }
}
