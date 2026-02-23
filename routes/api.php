<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\RegisterController;
use App\Http\Controllers\Api\Banking\SmsIngestController;
use App\Http\Controllers\Api\Banking\BankTransactionController;
use App\Http\Controllers\Api\Banking\NarrationReviewController;

Route::get('/user', function (Request $request) {
    return $request->user()->load(['roles']);
})->middleware('auth:sanctum');


Route::group(['prefix' => 'auth'], function () {
    Route::post('login', [RegisterController::class, 'login']);
    Route::delete('logout', [RegisterController::class, 'logout'])->middleware('auth:sanctum');
});


Route::prefix('banking')->middleware(['api'])->group(function () {

    // SMS Ingest
    Route::post('/transactions/sms', SmsIngestController::class);

    // Transactions
    Route::get('/transactions',              [BankTransactionController::class, 'index']);
    Route::get('/transactions/pending',      [BankTransactionController::class, 'pending']);
    Route::get('/transactions/{bankTransaction}', [BankTransactionController::class, 'show']);

    // Narration Review
    Route::post(
        '/transactions/{transaction}/review/{action}',
        [NarrationReviewController::class, 'handle']
    )->where('action', 'approve|correct|reject');

});
