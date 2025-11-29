<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Controllers
use App\Http\Controllers\Api\TransactionPixController;
use App\Http\Controllers\Api\WithdrawOutController;

use App\Http\Controllers\Webhooks\VeltraxWebhookController;
use App\Http\Controllers\Webhooks\GatewayWebhookController;

use App\Http\Controllers\Api\TrustPayWebhookController;
use App\Http\Controllers\Api\TrustPayOutController;
use App\Http\Controllers\Api\CashtimeWebhookController;
use App\Http\Controllers\Api\RapdynWebhookController;
use App\Http\Controllers\Api\CassWebhookController;

// ➕ ReflowPay — PAYIN & PAYOUT Webhooks
use App\Http\Controllers\Api\Webhooks\ReflowPayWebhookController;
use App\Http\Controllers\Api\Webhooks\ReflowPayCashoutWebhookController;

use App\Http\Controllers\Api\Webhooks\PluggouWebhookController;
use App\Http\Controllers\Api\Webhooks\PluggouPayoutWebhookController;

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
// PIX
// ───────────────────────────────────────────────
Route::prefix('pix')->name('api.pix.')->group(function () {
    // vazio por enquanto
});

// Criar transação Pix (CashIn)
Route::post('/transaction/pix', [TransactionPixController::class, 'store'])
    ->middleware('throttle:30,1')
    ->name('transaction.pix.store');

// Buscar transação por TXID
Route::get('/transaction/pix/{txid}', [TransactionPixController::class, 'showByTxid'])
    ->where('txid', '[A-Za-z0-9]+')
    ->middleware('throttle:60,1')
    ->name('transaction.pix.show');

// Saque (CashOut)
Route::post('/withdraw/out', [WithdrawOutController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('withdraw.out.store');

// TrustPay OUT
Route::post('/trustpay/out', [TrustPayOutController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('trustpay.out');

// ───────────────────────────────────────────────────────────────────────────────
// WEBHOOKS
// ───────────────────────────────────────────────────────────────────────────────
Route::prefix('webhooks')->name('webhooks.')->group(function () {

    // ────────────────────────────
    // VELTRAX
    // ────────────────────────────
    Route::post('/veltrax', VeltraxWebhookController::class)
        ->name('veltrax');

    // ────────────────────────────
    // GATEWAY GENÉRICO
    // ────────────────────────────
    Route::post('/gateway', [GatewayWebhookController::class, 'handle'])
        ->middleware('throttle:120,1')
        ->name('gateway');

    // ────────────────────────────
    // TRUSTPAY PAYIN
    // ────────────────────────────
    Route::post('/trustpay/paid', [TrustPayWebhookController::class, 'handle'])
        ->middleware('throttle:120,1')
        ->name('trustpay.paid');

    // ────────────────────────────
    // TRUSTPAY PAYOUT
    // ────────────────────────────
    Route::post('/trustout/payout', [TrustPayOutController::class, 'webhookPayout'])
        ->middleware('throttle:120,1')
        ->name('trustout.payout');

    // Alias (mesmo destino)
    Route::post('/trustpay/payout', [TrustPayOutController::class, 'webhookPayout'])
        ->middleware('throttle:120,1')
        ->name('trustpay.payout');

    // ────────────────────────────
    // CASHTIME PAYIN
    // ────────────────────────────
    Route::post('/cashtime', [CashtimeWebhookController::class, 'handle'])
        ->middleware('throttle:120,1')
        ->name('cashtime');

    // ────────────────────────────
    // RAPDYN PAYIN
    // ────────────────────────────
    Route::post('/rapdyn', [RapdynWebhookController::class, 'handle'])
        ->middleware('throttle:120,1')
        ->name('rapdyn');

    // ────────────────────────────
    // CASS PAGAMENTOS PAYIN
    // ────────────────────────────
    Route::post('/cass', [CassWebhookController::class, 'handle'])
        ->middleware('throttle:120,1')
        ->name('cass');

    // ────────────────────────────
    // PLUGGOU PAYIN
    // ────────────────────────────
    Route::post('/pluggou', PluggouWebhookController::class)
        ->middleware('throttle:120,1')
        ->name('pluggou');

    // ────────────────────────────
    // PLUGGOU PAYOUT
    // ────────────────────────────
    Route::post('/pluggou/payout', PluggouPayoutWebhookController::class)
        ->middleware('throttle:120,1')
        ->name('pluggou.payout');

    // ────────────────────────────
    // REFLOWPAY PAYIN (CashIn)
    // ────────────────────────────
    Route::post('/reflowpay', ReflowPayWebhookController::class)
        ->middleware('throttle:120,1')
        ->name('reflowpay');

    // ────────────────────────────
    // REFLOWPAY PAYOUT (CashOut)
    // ────────────────────────────
    Route::post('/reflowpay/cashout', ReflowPayCashoutWebhookController::class)
        ->middleware('throttle:120,1')
        ->name('reflowpay.cashout');
});
