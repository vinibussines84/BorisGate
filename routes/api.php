<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Controllers principais
use App\Http\Controllers\Api\TransactionPixController;
use App\Http\Controllers\Api\WithdrawOutController;
use App\Http\Controllers\Api\BalanceController;

// PodPay
use App\Http\Controllers\Api\PodPayTransactionController;
use App\Http\Controllers\Api\Webhooks\PodPayWebhookController;
use App\Http\Controllers\Api\Webhooks\PodPayWithdrawWebhookController;

use App\Http\Controllers\Webhooks\VeltraxWebhookController;
use App\Http\Controllers\Webhooks\GatewayWebhookController;

use App\Http\Controllers\Api\TrustPayWebhookController;
use App\Http\Controllers\Api\TrustPayOutController;
use App\Http\Controllers\Api\CashtimeWebhookController;
use App\Http\Controllers\Api\RapdynWebhookController;
use App\Http\Controllers\Api\CassWebhookController;

// Webhooks ReflowPay
use App\Http\Controllers\Api\Webhooks\ReflowPayWebhookController;
use App\Http\Controllers\Api\Webhooks\ReflowPayCashoutWebhookController;

// Webhooks Pluggou
use App\Http\Controllers\Api\Webhooks\PluggouWebhookController;
use App\Http\Controllers\Api\Webhooks\PluggouPayoutWebhookController;

// Webhooks Lumnis
use App\Http\Controllers\Api\Webhooks\LumnisWebhookController;
use App\Http\Controllers\Api\Webhooks\LumnisWithdrawController;


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
// PIX — CASH IN (Lumnis)
// ───────────────────────────────────────────────

Route::post('/transaction/pix', [TransactionPixController::class, 'store'])
    ->name('transaction.pix.store');

Route::get('/v1/transaction/status/{txid}', [TransactionPixController::class, 'showByTxid'])
    ->where('txid', '[A-Za-z0-9]+')
    ->name('transaction.pix.status.txid');

Route::get('/v1/transaction/status/external/{externalId}', [TransactionPixController::class, 'statusByExternal'])
    ->where('externalId', '[A-Za-z0-9\-_]+')
    ->name('transaction.pix.status.external');

Route::get('/transaction/pix/{txid}', [TransactionPixController::class, 'showByTxid'])
    ->where('txid', '[A-Za-z0-9]+')
    ->name('transaction.pix.show');


// ───────────────────────────────────────────────
// PIX — CASH IN (PodPay)
// ───────────────────────────────────────────────

Route::post('/v1/transaction/pix', [PodPayTransactionController::class, 'store'])
    ->name('v1.transaction.pix.store');


// ───────────────────────────────────────────────
// WITHDRAW — CASH OUT (SEM LIMITE)
// ───────────────────────────────────────────────

Route::post('/withdraw/out', [WithdrawOutController::class, 'store'])
    ->name('withdraw.out.store');


// ───────────────────────────────────────────────
// TRUSTPAY OUT
// ───────────────────────────────────────────────

Route::post('/trustpay/out', [TrustPayOutController::class, 'store'])
    ->name('trustpay.out');


// ───────────────────────────────────────────────
// BALANCE
// ───────────────────────────────────────────────

Route::get('/v1/balance/available', [BalanceController::class, 'available'])
    ->name('balance.available');


// ───────────────────────────────────────────────────────────────────────────────
// WEBHOOKS (TODOS SEM LIMITE)
// ───────────────────────────────────────────────────────────────────────────────
Route::prefix('webhooks')->name('webhooks.')->group(function () {

    // VELTRAX
    Route::post('/veltrax', VeltraxWebhookController::class)->name('veltrax');

    // Gateway Genérico
    Route::post('/gateway', [GatewayWebhookController::class, 'handle'])
        ->name('gateway');

    // TrustPay Payin
    Route::post('/trustpay/paid', [TrustPayWebhookController::class, 'handle'])
        ->name('trustpay.paid');

    // TrustPay Payout
    Route::post('/trustout/payout', [TrustPayOutController::class, 'webhookPayout'])
        ->name('trustout.payout');

    Route::post('/trustpay/payout', [TrustPayOutController::class, 'webhookPayout'])
        ->name('trustpay.payout');

    // Cashtime Payin
    Route::post('/cashtime', [CashtimeWebhookController::class, 'handle'])
        ->name('cashtime');

    // Rapdyn Payin
    Route::post('/rapdyn', [RapdynWebhookController::class, 'handle'])
        ->name('rapdyn');

    // CASS Pagamentos Payin
    Route::post('/cass', [CassWebhookController::class, 'handle'])
        ->name('cass');

    // Pluggou Payin
    Route::post('/pluggou', PluggouWebhookController::class)
        ->name('pluggou');

    // Pluggou Payout
    Route::post('/pluggou/payout', PluggouPayoutWebhookController::class)
        ->name('pluggou.payout');

    // ReflowPay Payin
    Route::post('/reflowpay', ReflowPayWebhookController::class)
        ->name('reflowpay');

    // ReflowPay Payout
    Route::post('/reflowpay/cashout', ReflowPayCashoutWebhookController::class)
        ->name('reflowpay.cashout');

    // Lumnis Payin
    Route::post('/lumnis', LumnisWebhookController::class)
        ->name('lumnis');

    // Lumnis Payout
    Route::post('/lumnis/withdraw', LumnisWithdrawController::class)
        ->name('lumnis.withdraw');

    // PodPay — Payout (CASHOUT)
    Route::post('/podpay/withdraw', PodPayWithdrawWebhookController::class)
        ->name('podpay.withdraw');

});
