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

class WithdrawController extends Controller
{
    public function __construct(
        private readonly ReflowPayCashoutService $reflow
    ) {}

    /*======================================================================
     *  API LIST
     *======================================================================*/
    public function apiIndex(Request $request)
    {
        $user = $request->user();

        [$status, $search, $origin] = [
            $request->string('status')->toString(),
            $request->string('search')->toString(),
            strtolower($request->string('origin')->toString()),
        ];

        $query = $this->baseQuery($user->id, $status, $search, $origin);
        $paginated = $query->paginate(10)->withQueryString();
        $items = $paginated->getCollection()->map(fn(Withdraw $w) => $this->mapWithdraw($w));

        $totalsQuery = Withdraw::where('user_id', $user->id);
        $totals = [
            'sum_all'          => (float) $totalsQuery->sum('amount'),
            'count_all'        => (int)   $totalsQuery->count(),
            'count_processing' => (int)   $totalsQuery->whereIn('status', ['pending', 'processing'])->count(),
        ];

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
                'from'         => $paginated->firstItem(),
                'to'           => $paginated->lastItem(),
                'totals'       => $totals,
            ],
        ]);
    }

    /*======================================================================
     *  PANEL LIST
     *======================================================================*/
    public function index(Request $request)
    {
        $user = $request->user();

        [$status, $search, $origin] = [
            $request->string('status')->toString(),
            $request->string('search')->toString(),
            strtolower($request->string('origin')->toString()),
        ];

        $query = $this->baseQuery($user->id, $status, $search, $origin);
        $withdraws = $query->paginate(10)->withQueryString()
            ->through(fn(Withdraw $w) => $this->mapWithdraw($w));

        return Inertia::render('Saque/Index', [
            'user'          => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
            'filters'       => compact('status', 'search', 'origin'),
            'withdraws'     => $withdraws,
            'statusOptions' => ['pending', 'processing', 'approved', 'paid', 'failed', 'rejected'],
            'pixkeyTypes'   => ['cpf', 'cnpj', 'email', 'phone', 'randomkey'],
        ]);
    }

    /*======================================================================
     *  CREATE (form Inertia)
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
        $fee = 10.00; // taxa fixa obrigatória

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
        $data = $request->validate([
            'amount'          => ['required', 'numeric', 'min:20', 'max:1000000'],
            'pixkey'          => ['required', 'string', 'max:140'],
            'pixkey_type'     => ['required', Rule::in(['cpf', 'cnpj', 'email', 'phone', 'randomkey'])],
            'description'     => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['required', 'string', 'max:100'],
            'pin'             => ['required', 'regex:/^\d{4,8}$/'],
        ]);

        return $data;
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

    private function baseQuery(int $userId, ?string $status, ?string $search, ?string $origin)
    {
        return Withdraw::query()
            ->where('user_id', $userId)
            ->when($status, fn($q) => $q->where('status', $status))
            ->when($search, fn($q) => $q->where(function ($q) use ($search) {
                $s = "%$search%";
                $q->where('description', 'like', $s)
                  ->orWhere('pixkey', 'like', $s)
                  ->orWhere('idempotency_key', 'like', $s);
            }))
            ->when($origin && Schema::hasColumn('withdraws', 'meta'),
                fn($q) => $q->where('meta->source', $origin))
            ->latest();
    }

    private function mapWithdraw(Withdraw $w): array
    {
        return [
            'id'                => $w->id,
            'amount'            => (float) $w->amount,
            'fee_amount'        => (float) $w->fee_amount,
            'gross_amount'      => (float) $w->gross_amount,
            'description'       => $w->description,
            'pixkey'            => $this->maskPixKey($w->pixkey, $w->pixkey_type),
            'pixkey_type'       => $w->pixkey_type,
            'status'            => $w->status,
            'idempotency_key'   => $w->idempotency_key,
            'created_at'        => $w->created_at,
            'provider_reference'=> $w->provider_reference,
            'meta'              => $w->meta,
        ];
    }

    private function maskPixKey(string $v, string $type): string
    {
        return match ($type) {
            'cpf'   => substr($v, 0, 3) . '.***.***-' . substr($v, -2),
            'cnpj'  => substr($v, 0, 2) . '.***.***/****-' . substr($v, -2),
            'email' => preg_replace('/(^.{2}).*@/', '$1***@', $v),
            'phone' => '(**) *****-' . substr(preg_replace('/\D+/', '', $v), -4),
            default => substr($v, 0, 4) . '****' . substr($v, -4),
        };
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
