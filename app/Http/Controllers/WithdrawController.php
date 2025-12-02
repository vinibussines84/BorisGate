<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Withdraw;
use App\Services\ReflowPay\ReflowPayCashoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use App\Enums\TransactionStatus;

class WithdrawController extends Controller
{
    public function __construct(
        private readonly ReflowPayCashoutService $reflow
    ) {}

    /*======================================================================
     *  ✅ API LIST — aprimorado com paginação, filtros e busca
     *======================================================================*/
    public function apiIndex(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Não autenticado.'], 401);
        }

        $search   = trim($request->query('search', ''));
        $statusIn = strtoupper($request->query('status', 'ALL'));
        $page     = max(1, (int) $request->query('page', 1));
        $perPage  = min(50, max(5, (int) $request->query('perPage', 10)));
        $offset   = ($page - 1) * $perPage;

        $alias = [
            'PAID'      => ['paid', 'paga', 'approved', 'confirmed', 'completed'],
            'PENDING'   => ['pending', 'pendente', 'processing', 'created', 'under_review'],
            'FAILED'    => ['failed', 'falha', 'error', 'denied', 'canceled', 'cancelled', 'rejected'],
        ];

        $q = Withdraw::query()
            ->selectRaw("
                id,
                'SAQUE' as _kind,
                false as credit,
                gross_amount as amount,
                fee_amount as fee,
                status,
                description,
                pixkey as txid,
                COALESCE(
                    JSON_UNQUOTE(JSON_EXTRACT(meta, '$.e2e')),
                    JSON_UNQUOTE(JSON_EXTRACT(meta, '$.endtoend'))
                ) as e2e_id,
                created_at,
                processed_at as paid_at
            ")
            ->where('user_id', $user->id);

        if ($statusIn !== 'ALL' && isset($alias[$statusIn])) {
            $q->whereIn('status', $alias[$statusIn]);
        }

        if ($search !== '') {
            $like = "%{$search}%";
            $q->where(function ($w) use ($like) {
                $w->where('id', 'LIKE', $like)
                    ->orWhere('pixkey', 'LIKE', $like)
                    ->orWhere('description', 'LIKE', $like)
                    ->orWhereRaw("JSON_EXTRACT(meta, '$.e2e') LIKE ?", [$like])
                    ->orWhereRaw("JSON_EXTRACT(meta, '$.endtoend') LIKE ?", [$like]);
            });
        }

        $total = (clone $q)->count();

        $rows = $q->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($perPage)
            ->get()
            ->map(function ($t) {
                $statusEnum = TransactionStatus::fromLoose($t->status);
                return [
                    'id'            => $t->id,
                    '_kind'         => $t->_kind,
                    'credit'        => false,
                    'amount'        => (float) $t->amount,
                    'fee'           => round((float) $t->fee, 2),
                    'net'           => round((float) $t->amount, 2),
                    'status'        => $statusEnum->value,
                    'status_label'  => $statusEnum->label(),
                    'txid'          => $t->txid,
                    'e2e'           => $t->e2e_id,
                    'description'   => $t->description,
                    'created_at'    => optional($t->created_at)->toIso8601String(),
                    'paid_at'       => optional($t->paid_at)->toIso8601String(),
                ];
            });

        $totalsQuery = Withdraw::where('user_id', $user->id);
        $totals = [
            'sum_all'          => (float) $totalsQuery->sum('amount'),
            'count_all'        => (int)   $totalsQuery->count(),
            'count_processing' => (int)   $totalsQuery->whereIn('status', ['pending', 'processing'])->count(),
        ];

        return response()->json([
            'success' => true,
            'page'    => $page,
            'perPage' => $perPage,
            'count'   => $rows->count(),
            'totalItems' => $total,
            'data' => $rows->values(),
            'meta' => [
                'current_page' => $page,
                'last_page'    => ceil($total / $perPage),
                'per_page'     => $perPage,
                'total'        => $total,
                'from'         => $offset + 1,
                'to'           => $offset + $rows->count(),
                'totals'       => $totals,
            ],
        ]);
    }

    /*======================================================================
     *  ✅ PANEL LIST (Inertia)
     *======================================================================*/
    public function index(Request $request)
    {
        $user = $request->user();

        $status = $request->query('status');
        $search = $request->query('search');
        $page = max(1, (int) $request->query('page', 1));

        $apiResponse = $this->apiIndex($request)->getData(true);

        return Inertia::render('Saque/Index', [
            'user'          => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
            'filters'       => compact('status', 'search'),
            'withdraws'     => $apiResponse['data'] ?? [],
            'meta'          => $apiResponse['meta'] ?? [],
            'statusOptions' => ['pending', 'processing', 'approved', 'paid', 'failed', 'rejected'],
            'pixkeyTypes'   => ['cpf', 'cnpj', 'email', 'phone', 'randomkey'],
            'page'          => $page,
        ]);
    }

    /*======================================================================
     *  CREATE (Inertia)
     *======================================================================*/
    public function create(Request $request)
    {
        $user = $request->user();

        if (empty($user->pin)) {
            return redirect()->route('setup.pin')
                ->with('warning', 'Configure seu PIN antes de solicitar saques.');
        }

        return Inertia::render('Saques/Solicitar', [
            'user' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
            ],
            'amount_available' => (float) ($user->amount_available ?? 0),
            'pixkeyTypes'      => ['cpf', 'cnpj', 'email', 'phone', 'randomkey'],
        ]);
    }

    /*======================================================================
     *  STORE / API STORE
     *======================================================================*/
    public function store(Request $request)
    {
        return $this->handleStore($request, 'panel');
    }

    public function apiStore(Request $request)
    {
        return $this->handleStore($request, 'api');
    }

    private function handleStore(Request $request, string $source)
    {
        $user = $request->user();
        $ip   = $request->ip();

        $withdrawLimitKey = "withdraw-request:{$user->id}";
        if (RateLimiter::tooManyAttempts($withdrawLimitKey, 1)) {
            $seconds = RateLimiter::availableIn($withdrawLimitKey);
            $minutes = ceil($seconds / 60);
            return $this->respond($source, "Você só pode solicitar outro saque em {$minutes} minuto(s).", null, 429);
        }

        try {
            $data = $this->validateStoreRequest($request);
            $this->guardPinOnly($user->pin, $data['pin']);
            RateLimiter::clear("withdraw-pin:{$user->id}");

            if (Withdraw::where('idempotency_key', $data['idempotency_key'])->exists()) {
                return $this->respond($source, 'Saque já registrado.');
            }

            $withdraw = DB::transaction(function () use ($user, $data, $source, $ip) {
                [$gross, $fee, $net] = $this->resolveGrossFeeNet($user, $data['amount']);
                $u = User::lockForUpdate()->findOrFail($user->id);

                if ($u->amount_available < $gross) {
                    throw ValidationException::withMessages(['amount' => 'Saldo insuficiente.']);
                }

                $u->amount_available -= $gross;
                $u->save();

                return Withdraw::create([
                    'user_id'         => $u->id,
                    'amount'          => $net,
                    'gross_amount'    => $gross,
                    'fee_amount'      => $fee,
                    'description'     => $data['description'] ?? null,
                    'pixkey'          => $data['pixkey'],
                    'pixkey_type'     => $data['pixkey_type'],
                    'idempotency_key' => $data['idempotency_key'],
                    'provider'        => 'reflowpay',
                    'status'          => 'pending',
                    'meta'            => ['source' => $source, 'ip' => $ip],
                ]);
            });

            $this->sendToReflow($user, $withdraw);

            RateLimiter::hit($withdrawLimitKey, 300);
            return $this->respond($source, 'Saque enviado para processamento.', $withdraw, 201);

        } catch (ValidationException $e) {
            return $this->respond($source, $e->errors(), null, 422);
        } catch (\Throwable $e) {
            Log::error('[WithdrawController] Erro inesperado', [
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ]);
            return $this->respond($source, 'Erro ao registrar saque.', null, 500);
        }
    }

    /*======================================================================
     *  ENVIO REFLOWPAY
     *======================================================================*/
    private function sendToReflow(User $user, Withdraw $withdraw): void
    {
        if (in_array($withdraw->status, ['paid', 'failed', 'canceled', 'rejected'], true)) return;

        $withdraw->update(['status' => 'processing']);

        $payload = [
            'value'      => intval($withdraw->gross_amount * 100),
            'pixKeyType' => $this->pixKeyTypeToEnum($withdraw->pixkey_type),
            'pixKey'     => $withdraw->pixkey,
            'cpfCnpj'    => preg_replace('/\D+/', '', $user->cpf ?? $user->document ?? ''),
            'person' => [
                'name'  => $user->name,
                'email' => $user->email,
                'phone' => preg_replace('/\D+/', '', $user->phone ?? ''),
            ],
            'orderId' => $withdraw->idempotency_key,
        ];

        Log::info('[ReflowPay Cashout] Payload enviado', [
            'withdraw_id' => $withdraw->id,
            'payload'     => $payload,
        ]);

        $resp = $this->reflow->createCashout($payload);
        Log::info('[ReflowPay Cashout] Response', $resp);

        if (!isset($resp['transactionId'])) {
            $this->revertFailedWithdraw($withdraw);
            throw new \RuntimeException('Erro ao comunicar com ReflowPay: ' . json_encode($resp));
        }

        $withdraw->update([
            'provider_reference' => $resp['transactionId'] ?? null,
            'provider_message'   => 'Enviado à ReflowPay',
            'status'             => 'processing',
            'meta'               => array_merge($withdraw->meta ?? [], ['reflow_response' => $resp]),
        ]);
    }

    private function pixKeyTypeToEnum(string $type): int
    {
        return match ($type) {
            'cpf'       => 0,
            'cnpj'      => 1,
            'email'     => 2,
            'phone'     => 3,
            'randomkey' => 4,
            default     => 4,
        };
    }

    /*======================================================================
     *  UTILITÁRIOS
     *======================================================================*/
    private function resolveGrossFeeNet(User $user, float $amount): array
    {
        $gross = round($amount, 2);
        $fee = 10.00;
        $net = round($gross - $fee, 2);

        if ($gross < 20) {
            throw ValidationException::withMessages(['amount' => 'O valor mínimo para saque é R$ 20,00.']);
        }

        if ($net <= 0) {
            throw ValidationException::withMessages(['amount' => 'Valor líquido inválido após taxa de R$10,00.']);
        }

        return [$gross, $fee, $net];
    }

    private function validateStoreRequest(Request $request): array
    {
        return $request->validate([
            'amount'          => ['required', 'numeric', 'min:20', 'max:1000000'],
            'pixkey'          => ['required', 'string', 'max:140'],
            'pixkey_type'     => ['required', Rule::in(['cpf', 'cnpj', 'email', 'phone', 'randomkey'])],
            'description'     => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['required', 'string', 'max:100'],
            'pin'             => ['required', 'regex:/^\d{4,8}$/'],
        ]);
    }

    private function guardPinOnly(?string $hash, string $pin): void
    {
        if (!$hash || !Hash::check($pin, $hash)) {
            RateLimiter::hit("withdraw-pin:" . auth()->id(), 60);
            throw ValidationException::withMessages(['pin' => 'PIN incorreto.']);
        }
    }

    private function revertFailedWithdraw(Withdraw $withdraw): void
    {
        DB::transaction(function () use ($withdraw) {
            $u = User::lockForUpdate()->findOrFail($withdraw->user_id);
            $u->amount_available += ($withdraw->gross_amount ?? ($withdraw->amount + $withdraw->fee_amount));
            $u->save();
            $withdraw->update(['status' => 'failed']);
        });
    }

    private function respond(string $source, $message, ?Withdraw $withdraw = null, int $status = 200)
    {
        if ($source === 'api') {
            return response()->json([
                'success'  => $status < 400,
                'message'  => is_string($message) ? $message : null,
                'errors'   => is_array($message) ? $message : null,
                'withdraw' => $withdraw ? $this->mapWithdraw($withdraw) : null,
            ], $status);
        }

        if ($status >= 400) {
            return back()->withErrors(['general' => is_string($message) ? $message : 'Erro.']);
        }

        return redirect()->route('saques.index')->with('success', $message);
    }
}
