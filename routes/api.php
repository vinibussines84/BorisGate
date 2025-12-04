<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Controllers principais
use App\Http\Controllers\Api\TransactionPixController;
use App\Http\Controllers\Api\WithdrawOutController;
use App\Http\Controllers\Api\BalanceController;
use App\Http\Controllers\Api\WebhookCoffePayController;
use App\Http\Controllers\Api\Webhooks\WebhookPluggouController; // ← CORRIGIDO

// ───────────────────────────────────────────────────────────────────────────────
// HEALTHCHECK
// ───────────────────────────────────────────────────────────────────────────────
Route::get('/ping', fn () => response()->json([
    'ok'        => true,
    'timestamp' => now()->toIso8601String(),
]))->name('api.ping');

// ───────────────────────────────────────────────────────────────────────────────
// USER AUTH
// ───────────────────────────────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
})->name('api.me');

// ───────────────────────────────────────────────
// PIX — CASH IN
// ───────────────────────────────────────────────
Route::post('/transaction/pix', [TransactionPixController::class, 'store'])
    ->name('transaction.pix.store');

Route::get('/v1/transaction/status/external/{externalId}', [TransactionPixController::class, 'statusByExternal'])
    ->where('externalId', '[A-Za-z0-9\-_]+')
    ->name('transaction.pix.status.external');

// ───────────────────────────────────────────────
// WITHDRAW — CASH OUT
// ───────────────────────────────────────────────
Route::post('/withdraw/out', [WithdrawOutController::class, 'store'])
    ->name('withdraw.out.store');

// ───────────────────────────────────────────────
// BALANCE
// ───────────────────────────────────────────────
Route::get('/v1/balance/available', [BalanceController::class, 'available'])
    ->middleware('throttle:60,1')
    ->name('balance.available');

// ───────────────────────────────────────────────────────────────────────────────
// WEBHOOKS — COFFE PAY + PLUGGOU
// ───────────────────────────────────────────────────────────────────────────────
Route::prefix('webhooks')->group(function () {

    // CoffePay (Payin + Payout)
    Route::post('/coffepay', [WebhookCoffePayController::class, 'handle'])
        ->middleware('throttle:120,1')
        ->name('webhooks.coffepay');

    // 🔥 Novo Webhook PLUGGOU — PIX IN (pixin)
    Route::post('/pixin', WebhookPluggouController::class)
        ->name('webhooks.pluggou.pixin');

});
