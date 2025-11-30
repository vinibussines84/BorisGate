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
// PIX — CASH IN (Lumnis - atual)
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
// PIX — CASH IN (PodPay - NOVO)
// ───────────────────────────────────────────────
Route::post('/v1/transaction/pix', [PodPayTransactionController::class, 'store'])
    ->name('v1.transaction.pix.store');


// ───────────────────────────────────────────────
// WITHDRAW — CASH OUT
// ───────────────────────────────────────────────
Route::post('/withdraw/out', [WithdrawOutController::class, 'store'])
    ->name('withdraw.out.store');


// ───────────────────────────────────────────────
// TRUSTPAY OUT
// ───────────────────────────────────────────────
Route::post('/trustpay/out', [TrustPayOutController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('trustpay.out');


// ───────────────────────────────────────────────
// BALANCE
// ───────────────────────────────────────────────
Route::get('/v1/balance/available', [BalanceController::class, 'available'])
    ->middleware('throttle:60,1')
    ->name('balance.available');


// ───────────────────────────────────────────────────────────────────────────────
// WEBHOOKS
// ───────────────────────────────────────────────────────────────────────────────
Route::prefix('webhooks')->name('webhooks.')->group(function () {

    Route::post('/veltrax', VeltraxWebhookController::class)->name('veltrax');

    Route::post('/gateway', [GatewayWebhookController::class, 'handle'])
        ->middleware('throttle:120,1')
        ->name('gateway');

    Route::post('/trustpay/paid', [TrustPayWebhookController::class, 'handle'])
        ->middleware('throttle:120,1')
        ->name('trustpay.paid');

    Route::post('/trustout/payout', [TrustPayOutController::class, 'webhookPayout'])
        ->middleware('throttle:120,1')
        ->name('trustout.payout');

    Route::post('/trustpay/payout', [TrustPayOutController::class, 'webhookPayout'])
        ->middleware('throttle:120,1')
        ->name('trustpay.payout');

    Route::post('/cashtime', [CashtimeWebhookController::class, 'handle'])
        ->middleware('throttle:120,1')
        ->name('cashtime');

    Route::post('/rapdyn', [RapdynWebhookController::class, 'handle'])
        ->middleware('throttle:120,1')
        ->name('rapdyn');

    Route::post('/cass', [CassWebhookController::class, 'handle'])
        ->middleware('throttle:120,1')
        ->name('cass');

    Route::post('/pluggou', PluggouWebhookController::class)
        ->middleware('throttle:120,1')
        ->name('pluggou');

    Route::post('/pluggou/payout', PluggouPayoutWebhookController::class)
        ->middleware('throttle:120,1')
        ->name('pluggou.payout');

    Route::post('/reflowpay', ReflowPayWebhookController::class)
        ->middleware('throttle:120,1')
        ->name('reflowpay');

    Route::post('/reflowpay/cashout', ReflowPayCashoutWebhookController::class)
        ->middleware('throttle:120,1')
        ->name('reflowpay.cashout');

    Route::post('/lumnis', LumnisWebhookController::class)
        ->middleware('throttle:120,1')
        ->name('lumnis');

    Route::post('/lumnis/withdraw', LumnisWithdrawController::class)
        ->middleware('throttle:120,1')
        ->name('lumnis.withdraw');

    // 🚀 PodPay — Payin (PIX) (sem limite)
    Route::post('/podpay', PodPayWebhookController::class)
        ->name('podpay');

    // 🚀 PodPay — Payout (Withdraw) (sem limite)
    Route::post('/podpay/withdraw', PodPayWithdrawWebhookController::class)
        ->name('podpay.withdraw');
});
