<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Banking\BankTransactionController;
use App\Http\Controllers\Banking\NarrationReviewController;
use App\Http\Controllers\Banking\SmsIngestController;
use App\Http\Controllers\Banking\StatementUploadController;

use App\Http\Controllers\AiChatController;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified'])->prefix('banking')->group(function () {

    // Display pending transactions view
    Route::get('/transactions/pending', [BankTransactionController::class, 'pending'])
        ->name('banking.transactions.pending');

    // Handle narration review (Inertia will submit to this)
    Route::post('/transactions/{transaction}/review/{action}', [NarrationReviewController::class, 'handle'])
        ->where('action', 'approve|correct|reject')
        ->name('banking.transactions.review');

    // SMS Ingest
    Route::post('/transactions/sms', SmsIngestController::class)
        ->name('banking.transactions.sms.ingest');

    // Statement Upload
    Route::post('/transactions/statement', StatementUploadController::class)
        ->name('banking.transactions.statement.upload');
});

Route::middleware(['auth', 'verified'])->group(function () {

    // Render the chat UI
    Route::get('/accounting/chat', [AiChatController::class, 'index'])
        ->name('accounting.chat');

    // Handle each message (Inertia router.post)
    Route::post('/accounting/chat', [AiChatController::class, 'send'])
        ->name('accounting.chat.send');

});
require __DIR__.'/auth.php';
