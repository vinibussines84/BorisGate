<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendWebhookWithdrawCreatedJob;
use App\Models\User;
use App\Models\Withdraw;
use App\Services\Pix\KeyValidator;
use App\Services\Withdraw\WithdrawService;
use App\Services\Lumnis\LumnisCashoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class WithdrawOutController extends Controller
{
    public function __construct(
        private readonly WithdrawService      $withdrawService,
        private readonly LumnisCashoutService $lumnis
    ) {}

    public function store(Request $request)
    {
        try {

            /* ============================================================
             * 🔐 Autenticação via Headers
             * ============================================================ */
            $authKey   = $request->header('X-Auth-Key');
            $secretKey = $request->header('X-Secret-Key');

            if (!$authKey || !$secretKey) {
                return $this->error('Headers ausentes.');
            }

            $user = User::where('authkey', $authKey)
                ->where('secretkey', $secretKey)
                ->first();

            if (!$user) {
                return $this->error('Credenciais inválidas.');
            }

            /* ============================================================
             * 🔤 Normalizar key_type
             * ============================================================ */
            $request->merge(['key_type' => strtolower($request->input('key_type'))]);

            /* ============================================================
             * 📞 Normalizar telefone
             * ============================================================ */
            if ($request->input('key_type') === 'phone') {

                $phone = preg_replace('/\D/', '', $request->input('key'));

                if (str_starts_with($phone, '55')) {
                    $phone = substr($phone, 2);
                }

                $request->merge(['key' => $phone]);
            }

            /* ============================================================
             * 🧾 Validação
             * ============================================================ */
            $data = $request->validate([
                'amount'       => ['required', 'numeric', 'min:0.01'],
                'key'          => ['required', 'string'],
                'key_type'     => ['required', Rule::in(['cpf', 'cnpj', 'email', 'phone', 'evp', 'copypaste'])],
                'description'  => ['nullable', 'string', 'max:255'],
                'external_id'  => ['nullable', 'string', 'max:64'],

                // Details obrigatórios para Lumnis
                'details' => ['required', 'array'],
                'details.name' => ['required', 'string', 'max:80'],
                'details.document' => ['required', 'string', 'max:20'],
            ]);

            /* ============================================================
             * 💸 Valor mínimo
             * ============================================================ */
            $gross = (float) $data['amount'];

            if ($gross < 5) {
                return $this->error('Valor mínimo para saque é R$ 5,00.');
            }

            /* ============================================================
             * 🔎 Validar chave PIX
             * ============================================================ */
            if (!KeyValidator::validate($data['key'], strtoupper($data['key_type']))) {
                return $this->error('Chave PIX inválida.');
            }

            if (!$user->tax_out_enabled) {
                return $this->error('Cashout desabilitado.');
            }

            /* ============================================================
             * 💰 Cálculo de taxas
             * ============================================================ */
            $fee = round(($user->tax_out_fixed ?? 0) + ($gross * ($user->tax_out_percent ?? 0) / 100), 2);
            $net = round($gross - $fee, 2);

            if ($net <= 0) {
                return $this->error('Valor líquido inválido.');
            }

            /* ============================================================
             * 🔢 External ID + Idempotência
             * ============================================================ */
            $externalId = $data['external_id'] ??
                'WD_' . now()->timestamp . '_' . random_int(1000, 9999);

            if (Withdraw::where('user_id', $user->id)
                ->where('external_id', $externalId)
                ->exists()) {
                return $this->error('External ID duplicado.');
            }

            $internalRef = 'withdraw_' . now()->timestamp . '_' . random_int(1000, 9999);

            /* ============================================================
             * 🧾 Criar saque local
             * ============================================================ */
            try {
                $withdraw = $this->withdrawService->create(
                    $user,
                    $gross,
                    $net,
                    $fee,
                    [
                        'key'         => $data['key'],
                        'key_type'    => strtolower($data['key_type']),
                        'external_id' => $externalId,
                        'internal_ref'=> $internalRef,
                        'provider'    => 'lumnis',
                        'details'     => $data['details'],
                    ]
                );
            } catch (\Throwable $e) {
                return $this->error($e->getMessage());
            }

            /* ============================================================
             * 🚀 Criar saque na Lumnis
             * ============================================================ */
            $payload = [
                "amount"       => (int) round($gross * 100),
                "key"          => $data['key'],
                "key_type"     => strtoupper($data['key_type']),
                "description"  => $data['description'] ?? '',
                "external_ref" => $externalId,
                "postback"     => route('lumnis.withdraw'),

                // 🔥 MANTÉM EXATAMENTE COMO VEIO NO BODY
                "details"      => [
                    "name"     => $data['details']['name'],
                    "document" => preg_replace('/\D/', '', $data['details']['document']),
                ],
            ];

            $resp = $this->lumnis->createWithdrawal($payload);

            /* ============================================================
             * ❌ Erro do provedor
             * ============================================================ */
            if (!$resp['success']) {
                $this->withdrawService->refundLocal($withdraw, 'provider_error');
                return $this->error($resp['message'] ?? 'Erro ao criar saque na Lumnis.');
            }

            /* ============================================================
             * 📌 Obter referência
             * ============================================================ */
            $providerRef = data_get($resp, 'data.id')
                ?? data_get($resp, 'data.identifier')
                ?? null;

            if (!$providerRef) {
                $this->withdrawService->refundLocal($withdraw, 'missing_provider_id');
                return $this->error('Não foi possível obter referência da Lumnis.');
            }

            /* ============================================================
             * 🔄 Normalizar status
             * ============================================================ */
            $providerStatus = strtoupper(data_get($resp, 'data.status', 'PENDING'));

            $status = match ($providerStatus) {
                'PAID', 'COMPLETED', 'SUCCESS' => 'paid',
                'FAILED', 'ERROR', 'CANCELED', 'CANCELLED' => 'failed',
                'PROCESSING', 'SENDING', 'SENT', 'PENDING' => 'processing',
                default => 'pending',
            };

            /* ============================================================
             * 💾 Atualizar saque local
             * ============================================================ */
            $this->withdrawService->updateProviderReference(
                $withdraw,
                $providerRef,
                $status,
                $resp
            );

            /* ============================================================
             * 🌐 Webhook OUT
             * ============================================================ */
            if ($user->webhook_enabled && $user->webhook_out_url) {
                SendWebhookWithdrawCreatedJob::dispatch(
                    $user->id,
                    $withdraw->id,
                    $status,
                    $providerRef
                );
            }

            /* ============================================================
             * 🟢 Sucesso
             * ============================================================ */
            return response()->json([
                'success' => true,
                'message' => 'Saque solicitado com sucesso!',
                'data' => [
                    'id'            => $withdraw->id,
                    'external_id'   => $withdraw->external_id,
                    'amount'        => $withdraw->gross_amount,
                    'liquid_amount' => $withdraw->amount,
                    'pix_key'       => $withdraw->pixkey,
                    'pix_key_type'  => $withdraw->pixkey_type,
                    'status'        => $status,
                    'reference'     => $providerRef,
                    'provider'      => 'lumnis',
                ],
            ]);

        } catch (\Throwable $e) {

            Log::error('🚨 Erro ao criar saque', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error('Erro interno ao processar saque.');
        }
    }

    private function error(string $message)
    {
        return response()->json([
            'success' => false,
            'error'   => $message,
        ]);
    }
}
