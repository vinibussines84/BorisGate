<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Controllers principais
use App\Http\Controllers\Api\TransactionPixController;
use App\Http\Controllers\Api\WithdrawOutController;
use App\Http\Controllers\Api\BalanceController;
use App\Http\Controllers\Api\WebhookCoffePayController;
use App\Http\Controllers\Api\Webhooks\WebhookPluggouController;
use App\Http\Controllers\Api\Webhooks\WebhookPluggouPixOutController;

// Novos Controllers CN
use App\Http\Controllers\Api\Webhooks\WebhookCnInController;
use App\Http\Controllers\Api\Webhooks\WebhookCnOutController;

// Novo Controller — ColdFy
use App\Http\Controllers\Api\Webhooks\WebhookColdFyController;
use App\Http\Controllers\Api\Webhooks\WebhookColdFyOutController;

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
// WEBHOOKS — COFFE PAY + PLUGGOU + CN + COLDFY
// ───────────────────────────────────────────────────────────────────────────────
Route::prefix('webhooks')->group(function () {

    // CoffePay (Payin + Payout)
    Route::post('/coffepay', [WebhookCoffePayController::class, 'handle'])
        ->middleware('throttle:120,1')
        ->name('webhooks.coffepay');

    // PLUGGOU — PIX IN
    Route::post('/pixin', WebhookPluggouController::class)
        ->name('webhooks.pluggou.pixin');

    // PLUGGOU — PIX OUT
    Route::post('/pixout', [WebhookPluggouPixOutController::class, '__invoke'])
        ->name('webhooks.pluggou.pixout');

    // CN — PIX IN
    Route::post('/in/cn', [WebhookCnInController::class, 'handle'])
        ->name('webhooks.cn.in');

    // CN — PIX OUT
    Route::post('/out/cn', [WebhookCnOutController::class, 'handle'])
        ->name('webhooks.cn.out');

    // ───────────────────────────────────────────────
    // COLDFY — HEALTHCHECK (GET obrigatório!)
    // ───────────────────────────────────────────────
    Route::get('/coldfy', fn() => response()->json(['ok' => true], 200));

    // COLDFY — PIX IN (pagamentos recebidos)
    Route::post('/coldfy', [WebhookColdFyController::class, 'handle'])
        ->middleware('throttle:120,1')
        ->name('webhooks.coldfy');

    // COLDFY — PIX OUT (saques / cashouts)
    Route::post('/coldfy/out', [WebhookColdFyOutController::class, 'handle'])
        ->middleware('throttle:120,1')
        ->name('webhooks.coldfy.out');
});
