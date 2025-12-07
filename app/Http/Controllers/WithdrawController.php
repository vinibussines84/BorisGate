<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Withdraw;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use App\Enums\TransactionStatus;

class WithdrawController extends Controller
{
    /*======================================================================
     *  ✅ API LIST — compatível com MySQL e SQLite
     *======================================================================*/
    public function apiIndex(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Não autenticado.'
            ], 401);
        }

        $search   = trim($request->query('search', ''));
        $statusIn = strtoupper($request->query('status', 'ALL'));
        $page     = max(1, (int)$request->query('page', 1));
        $perPage  = min(50, max(5, (int)$request->query('perPage', 10)));
        $offset   = ($page - 1) * $perPage;

        $alias = [
            'PAID'    => ['paid', 'paga', 'approved', 'confirmed', 'completed'],
            'PENDING' => ['pending', 'pendente', 'processing', 'created', 'under_review'],
            'FAILED'  => ['failed', 'falha', 'error', 'denied', 'canceled', 'cancelled', 'rejected'],
        ];

        /* Detecta driver do BD */
        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            $e2eSql      = "json_extract(meta, '$.e2e')";
            $endtoendSql = "json_extract(meta, '$.endtoend')";
        } else {
            $e2eSql      = "JSON_UNQUOTE(JSON_EXTRACT(meta, '$.e2e'))";
            $endtoendSql = "JSON_UNQUOTE(JSON_EXTRACT(meta, '$.endtoend'))";
        }

        /* QUERY principal */
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
                COALESCE($e2eSql, $endtoendSql) as e2e_id,
                created_at,
                processed_at as paid_at
            ")
            ->where('user_id', $user->id);

        /* Filtro por status */
        if ($statusIn !== 'ALL' && isset($alias[$statusIn])) {
            $q->whereIn('status', $alias[$statusIn]);
        }

        /* Filtro de busca */
        if ($search !== '') {
            $like = "%{$search}%";
            $q->where(function ($w) use ($like, $driver) {
                $w->where('id', 'LIKE', $like)
                    ->orWhere('pixkey', 'LIKE', $like)
                    ->orWhere('description', 'LIKE', $like);

                if ($driver === 'sqlite') {
                    $w->orWhereRaw("json_extract(meta, '$.e2e') LIKE ?", [$like])
                      ->orWhereRaw("json_extract(meta, '$.endtoend') LIKE ?", [$like]);
                } else {
                    $w->orWhereRaw("JSON_EXTRACT(meta, '$.e2e') LIKE ?", [$like])
                      ->orWhereRaw("JSON_EXTRACT(meta, '$.endtoend') LIKE ?", [$like]);
                }
            });
        }

        $total = (clone $q)->count();

        /* Ordenação */
        $rows = $q->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($perPage)
            ->get()
            ->map(function ($t) {
                $statusEnum = TransactionStatus::fromLoose($t->status);

                return [
                    'id'           => $t->id,
                    '_kind'        => $t->_kind,
                    'credit'       => false,
                    'amount'       => (float)$t->amount,  // bruto
                    'fee'          => round((float)$t->fee, 2),
                    'net'          => round((float)$t->amount - $t->fee, 2), // líquido
                    'status'       => $statusEnum->value,
                    'status_label' => $statusEnum->label(),
                    'txid'         => $t->txid,
                    'e2e'          => $t->e2e_id,
                    'description'  => $t->description,
                    'created_at'   => optional($t->created_at)->toIso8601String(),
                    'paid_at'      => optional($t->paid_at)->toIso8601String(),
                ];
            });

        /* ============================================================
         *  🔥 TOTAIS CORRIGIDOS — AQUI ESTAVA O SEU BUG!
         * ============================================================ */

        $totalsBase = Withdraw::where('user_id', $user->id);

        $paidStatuses = ['paid', 'approved', 'confirmed', 'completed'];

        $totals = [
            // ✅ total bruto pago — o correto para "Total Withdrawn"
            'sum_paid'         => (float)$totalsBase->clone()->whereIn('status', $paidStatuses)->sum('gross_amount'),

            // quantidade total de saques
            'count_all'        => (int)$totalsBase->clone()->count(),

            // quantidade em processamento
            'count_processing' => (int)$totalsBase->clone()->whereIn('status', ['pending', 'processing'])->count(),
        ];

        return response()->json([
            'success'    => true,
            'page'       => $page,
            'perPage'    => $perPage,
            'count'      => $rows->count(),
            'totalItems' => $total,
            'data'       => $rows->values(),
            'meta'       => [
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
     *  LISTAGEM VIA INERTIA
     *======================================================================*/
    public function index(Request $request)
    {
        $user = $request->user();

        $status = $request->query('status');
        $search = $request->query('search');
        $page   = max(1, (int)$request->query('page', 1));

        $apiResponse = $this->apiIndex($request)->getData(true);

        return Inertia::render('Saque/Index', [
            'user'          => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email
            ],
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
            'amount_available' => (float)($user->amount_available ?? 0),
            'pixkeyTypes'      => ['cpf', 'cnpj', 'email', 'phone', 'randomkey'],
        ]);
    }

    /*======================================================================
     *  STORE - API & Painel
     *======================================================================*/
    public function store(Request $request) { return $this->handleStore($request, 'panel'); }
    public function apiStore(Request $request) { return $this->handleStore($request, 'api'); }

    private function handleStore(Request $request, string $source)
    {
        $user = $request->user();
        $ip   = $request->ip();

        $limitKey = "withdraw-request:{$user->id}";
        if (RateLimiter::tooManyAttempts($limitKey, 1)) {
            $seconds = RateLimiter::availableIn($limitKey);
            return $this->respond($source, "Você só pode solicitar outro saque em " . ceil($seconds / 60) . " minuto(s).", null, 429);
        }

        try {
            /* validação */
            $data = $this->validateStoreRequest($request);
            $this->guardPinOnly($user->pin, $data['pin']);
            RateLimiter::clear("withdraw-pin:{$user->id}");

            /* idempotency */
            if (Withdraw::where('idempotency_key', $data['idempotency_key'])->exists()) {
                return $this->respond($source, 'Saque já registrado.');
            }

            /* criação */
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
                    'provider'        => 'internal',
                    'status'          => 'pending',
                    'meta'            => ['source' => $source, 'ip' => $ip],
                ]);
            });

            RateLimiter::hit($limitKey, 300);

            return $this->respond($source, 'Saque registrado com sucesso.', $withdraw, 201);

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
     *  UTILITÁRIOS
     *======================================================================*/
    private function resolveGrossFeeNet(User $user, float $amount): array
    {
        $gross = round($amount, 2);
        $fee   = 10.00;
        $net   = round($gross - $fee, 2);

        if ($gross < 20) {
            throw ValidationException::withMessages([
                'amount' => 'O valor mínimo para saque é R$ 20,00.'
            ]);
        }

        if ($net <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Valor líquido inválido após taxa fixa de R$10,00.'
            ]);
        }

        return [$gross, $fee, $net];
    }

    private function validateStoreRequest(Request $request): array
    {
        return $request->validate([
            'amount'          => ['required', 'numeric', 'min:20', 'max:1000000'],
            'pixkey'          => ['required', 'string', 'max140'],
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
            throw ValidationException::withMessages([
                'pin' => 'PIN incorreto.'
            ]);
        }
    }

    private function respond(string $source, $message, ?Withdraw $withdraw = null, int $status = 200)
    {
        if ($source === 'api') {
            return response()->json([
                'success'  => $status < 400,
                'message'  => is_string($message) ? $message : null,
                'errors'   => is_array($message) ? $message : null,
                'withdraw' => $withdraw,
            ], $status);
        }

        if ($status >= 400) {
            return back()->withErrors([
                'general' => is_string($message) ? $message : 'Erro.'
            ]);
        }

        return redirect()->route('saques.index')
            ->with('success', $message);
    }
}
