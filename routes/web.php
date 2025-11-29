<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Inertia\Inertia;

// Models
use App\Models\User;

// Controllers (web)
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\UsersController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\MedController;
use App\Http\Controllers\WithdrawController;
use App\Http\Controllers\PixLimitesController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\DadosContaController;
use App\Http\Controllers\KycQuickCheckController;
use App\Http\Controllers\CobrancaController;
use App\Http\Controllers\ApproverLogController;
use App\Http\Controllers\BalanceController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\MetricsController;

// Controllers (Auth)
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PinController;

// API internas autenticadas
use App\Http\Controllers\Api\SessionTransactionsController;
use App\Http\Controllers\Api\DashboardFlowController;
use App\Http\Controllers\Api\DashboardIndicatorsController;
use App\Http\Controllers\Api\MeSummaryController;
use App\Http\Controllers\Api\ListPixController;

// API públicas
use App\Http\Controllers\Api\TransactionPixController;

// Auth Requests
use App\Http\Requests\Auth\LoginRequest;

// CSRF
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Csrf;

/*
|--------------------------------------------------------------------------
| Redirecionamento inicial
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login.page');
})->name('home');


/*
|--------------------------------------------------------------------------
| 🔐 Autenticação (grupo WEB completo)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {

    Route::get('/login', fn () => Inertia::render('Auth/Login'))
        ->name('login.page');

    Route::post('/login', function (LoginRequest $request) {

        $request->authenticate();
        $request->session()->regenerate();

        // suporte perfeito para Inertia SSR e SPA
        return to_route('dashboard', [], 303);

    })->middleware(['web', 'throttle:login'])
      ->name('login');

    Route::get('/register', [RegisteredUserController::class, 'create'])
        ->name('register.page');

    Route::post('/register', [RegisteredUserController::class, 'store'])
        ->name('register');

    Route::get('/forgot-password', fn () => Inertia::render('Auth/FForgotPassword'))
        ->name('password.request');
});

/*
|--------------------------------------------------------------------------
| 🚫 Usuário bloqueado
|--------------------------------------------------------------------------
*/
Route::get('/bloqueado', fn () =>
    Inertia::render('Usuario/Bloqueado', [
        'title' => 'Manutenção',
        'message' => 'Manutenção Temporária.',
    ])
)->name('usuario.bloqueado');


/*
|--------------------------------------------------------------------------
| 🔐 Logout
|--------------------------------------------------------------------------
*/
Route::post('/logout', function (Request $request) {
    Auth::guard('web')->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return to_route('login.page');
})->middleware('auth')->name('logout');


/*
|--------------------------------------------------------------------------
| 📌 KYC Quick Check
|--------------------------------------------------------------------------
*/
Route::post('/kyc/quick-check', [KycQuickCheckController::class, 'check'])
    ->middleware('throttle:10,1')
    ->name('kyc.quick-check');


/*
|--------------------------------------------------------------------------
| 🔒 Painel autenticado
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'check.user.status', 'ensure.active', 'throttle:60,1'])
    ->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // 🔔 Notificações (ADICIONADO)
    Route::get('/notifications', [\App\Http\Controllers\NotificationController::class, 'index'])
        ->name('notifications.index');

    // PIN Setup
    Route::get('/setup/pin',  [PinController::class, 'edit'])->name('setup.pin');
    Route::post('/setup/pin', [PinController::class, 'store'])->name('setup.pin.store');

    // Dashboard API (web)
    Route::get('/api/balances', [DashboardController::class, 'balances'])
        ->name('api.balances');

    // Pluggou
    Route::get('/balance/available', [BalanceController::class, 'get'])
        ->name('balance.available');

    // Páginas simples
    Route::get('/extrato', fn () => Inertia::render('Extrato/Index'))->name('extrato');
    Route::get('/transferencia', fn () => Inertia::render('Transferencia/Index'))->name('transferencia');
    Route::get('/pagamentos', fn () => Inertia::render('Pagamentos'))->name('pagamentos');
    Route::get('/med', [MedController::class, 'index'])->name('med');

    // Produtos / vendas
    Route::get('/produtos', fn () => Inertia::render('Produtos/Index'))->name('produtos.index');
    Route::get('/vendas', fn () => Inertia::render('Vendas/Index'))->name('vendas.index');

    // Cobranças
    Route::get('/cobranca', [CobrancaController::class, 'index'])->name('cobranca.index');
    Route::post('/cobranca', [CobrancaController::class, 'store'])->name('cobranca.store');

    // Saques
    Route::get('/saques', [WithdrawController::class, 'index'])->name('saques.index');
    Route::get('/saques/solicitar', [WithdrawController::class, 'create'])->name('saques.create');
    Route::post('/saques', [WithdrawController::class, 'store'])->name('saques.store');
    Route::post('/saques/{withdraw}/aprovar', [WithdrawController::class, 'approve'])->name('saques.approve');
    Route::post('/saques/{withdraw}/cancelar', [WithdrawController::class, 'cancel'])->name('saques.cancel');

    // Limites PIX
    Route::get('/pix/limites', [PixLimitesController::class, 'index'])
        ->name('pix.limites');

    // Perfil e senha
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::put('/password', [PasswordController::class, 'update'])->name('password.update');

    // Configurações
    Route::get('/dados-conta', [DadosContaController::class, 'index'])->name('dados.conta');
    Route::get('/configuracoes', fn () => Inertia::render('Configuracoes/Index'))->name('configuracoes');

    // Documentação API
    Route::get('/api', [ApiController::class, 'index'])->name('api');
    Route::get('/api/docs', fn () => Inertia::render('Api/Docs', [
        'title' => 'Documentação da API',
        'version' => config('app.api_version', 'v1'),
        'base_url' => rtrim(config('app.url'), '/') . '/api',
    ]))->name('api.docs');

    // AproverLog
    Route::get('/aproverlog', [ApproverLogController::class, 'index'])->name('aproverlog.index');
    Route::post('/aproverlog/{id}/approve', [ApproverLogController::class, 'approve'])->name('aproverlog.approve');
    Route::post('/aproverlog/{id}/reject', [ApproverLogController::class, 'reject'])->name('aproverlog.reject');

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    */
    Route::prefix('webhooks')->group(function () {
        Route::get('/', [WebhookController::class, 'index'])->name('webhooks.index');
        Route::post('/store', [WebhookController::class, 'store'])->name('webhooks.store');
        Route::get('/logs', [WebhookController::class, 'logs'])->name('webhooks.logs');

        Route::post('/resend/{id}', [WebhookController::class, 'resend'])
            ->name('webhooks.resend')
            ->withoutMiddleware([Csrf::class]);

        Route::delete('/{type}', [WebhookController::class, 'destroy'])->name('webhooks.destroy');
    });
});


/*
|--------------------------------------------------------------------------
| 👑 Admin
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'check.user.status', 'ensure.active', 'admin', 'throttle:60,1'])
    ->group(function () {
        Route::get('/admin', [AdminDashboardController::class, 'index'])->name('admin.index');
        Route::get('/admin/usuarios', [UsersController::class, 'index'])->name('admin.users.index');
    });


/*
|--------------------------------------------------------------------------
| 🌐 API públicas
|--------------------------------------------------------------------------
*/
Route::prefix('api')->group(function () {
    Route::post('/transaction/pix', [TransactionPixController::class, 'store'])
        ->withoutMiddleware([Csrf::class])
        ->middleware('throttle:30,1')
        ->name('api.transaction.pix.store');
});


/*
|--------------------------------------------------------------------------
| 🧩 API internas autenticadas (React SPA)
|--------------------------------------------------------------------------
*/
Route::prefix('api')
    ->middleware(['web', 'auth', 'check.user.status', 'ensure.active'])
    ->group(function () {

    Route::get('/me/transactions', [SessionTransactionsController::class, 'index'])->name('api.me.transactions');
    Route::get('/me/summary', MeSummaryController::class)->name('api.me.summary');

    Route::get('/session/transactions', [SessionTransactionsController::class, 'index'])->name('api.session.transactions');

    Route::get('/dashboard/daily-flow', [DashboardFlowController::class, 'dailyFlow'])->name('api.dashboard.daily-flow');
    Route::get('/dashboard/indicators', [DashboardIndicatorsController::class, 'index'])->name('api.dashboard.indicators');

    Route::get('/list/pix', [ListPixController::class, 'index'])->name('api.list.pix');

    Route::get('/withdraws', [WithdrawController::class, 'apiIndex'])->name('api.withdraws.index');
    Route::post('/withdraws', [WithdrawController::class, 'apiStore'])->name('api.withdraws.store');
    Route::post('/withdraws/{withdraw}/approve', [WithdrawController::class, 'apiApprove'])->name('api.withdraws.approve');
    Route::post('/withdraws/{withdraw}/cancel', [WithdrawController::class, 'apiCancel'])->name('api.withdraws.cancel');

    Route::get('/charges/summary', [CobrancaController::class, 'summary'])->name('api.charges.summary');
    Route::get('/charges', [CobrancaController::class, 'list'])->name('api.charges.index');
    Route::get('/charges/{cobranca}', [CobrancaController::class, 'show'])->name('api.charges.show');

    Route::get('/metrics/month', [MetricsController::class, 'month'])->name('api.metrics.month');
    Route::put('/metrics/goal', [MetricsController::class, 'updateGoal'])->name('api.metrics.goal');
    Route::get('/metrics/paid-feed', [MetricsController::class, 'paidFeed'])->name('api.metrics.paid-feed');
});


/*
|--------------------------------------------------------------------------
| 🚨 Fallback Final
|--------------------------------------------------------------------------
*/
Route::fallback(function (Request $request) {
    return $request->expectsJson() || $request->is('api/*')
        ? response()->json(['message' => 'Not Found'], 404)
        : Inertia::render('Errors/NotFound', [
            'status' => 404,
            'url' => $request->fullUrl(),
        ])->toResponse($request)->setStatusCode(404);
})->name('fallback.notfound');
