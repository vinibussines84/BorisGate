<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Withdraw;
use App\Services\PodPay\PodPayService;
use App\Enums\TransactionStatus;
use App\Jobs\SendWebhookPixCreatedJob;

class TransactionPixController extends Controller
{
    public function store(Request $request, PodPayService $podpay)
    {
        // 🔐 Auth headers
        $auth   = $request->header('X-Auth-Key');
        $secret = $request->header('X-Secret-Key');
        if (!$auth || !$secret) {
            return response()->json(['success' => false, 'error' => 'Missing authentication headers.'], 401);
        }

        $user = User::where('authkey', $auth)->where('secretkey', $secret)->first();
        if (!$user) {
            return response()->json(['success' => false, 'error' => 'Invalid credentials.'], 401);
        }

        // 🧾 Validação básica (sem overhead)
        $data = $request->validate([
            'amount'      => ['required', 'numeric', 'min:0.01'],
            'external_id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9\-_]+$/'],
            'name'        => ['sometimes', 'string', 'max:100'],
            'email'       => ['sometimes', 'email', 'max:120'],
            'phone'       => ['sometimes', 'string', 'max:20'],
            'document'    => ['sometimes', 'string', 'max:20'],
        ]);

        $amount = (float) $data['amount'];
        if ($amount > 3000) {
            return response()->json(['success' => false, 'error' => 'Max PIX amount R$3000.'], 422);
        }

        // 🔁 Evita duplicidade
        if (Transaction::where('user_id', $user->id)->where('external_reference', $data['external_id'])->exists()) {
            return response()->json(['success' => false, 'error' => 'External ID already exists.'], 409);
        }

        // ⚙️ Dados do cliente (fallbacks rápidos)
        $cpf = preg_replace('/\D/', '', $data['document'] ?? $user->cpf_cnpj ?? '');
        $name = $data['name'] ?? $user->name ?? 'Client';
        $email = $data['email'] ?? $user->email ?? 'no-reply@client.com';
        $phone = preg_replace('/\D/', '', $data['phone'] ?? $user->phone ?? '');

        // 🧮 Cria transação local (leve)
        $tx = Transaction::create([
            'user_id'            => $user->id,
            'direction'          => Transaction::DIR_IN,
            'status'             => TransactionStatus::PENDENTE,
            'currency'           => 'BRL',
            'method'             => 'pix',
            'provider'           => 'PodPay',
            'amount'             => $amount,
            'fee'                => $this->fee($user, $amount),
            'external_reference' => $data['external_id'],
        ]);

        // 🚀 Cria cobrança na PodPay
        try {
            $body = $podpay->createPixTransaction([
                "amount"        => (int) round($amount * 100),
                "currency"      => "BRL",
                "paymentMethod" => "pix",
                "pix"           => ["expiresInDays" => 1],
                "customer"      => [
                    "name" => $name, "email" => $email, "phone" => $phone,
                    "document" => ["number" => $cpf, "type" => "cpf"]
                ],
                "externalRef"   => $data['external_id'],
                "postbackUrl"   => route('webhooks.podpay'),
                "items"         => [[
                    "title" => "Pix Payment",
                    "unitPrice" => (int) round($amount * 100),
                    "quantity" => 1,
                    "tangible" => false,
                ]]
            ])['body'] ?? [];

            $qr = data_get($body, 'pix.qrcode');
            $id = data_get($body, 'id');
            if (!$qr || !$id) throw new \Exception('Invalid response');

            $tx->update([
                'txid'                    => $id,
                'provider_transaction_id' => $id,
                'provider_payload'        => ['qr_code_text' => $qr, 'provider_raw' => $body],
            ]);
        } catch (\Throwable) {
            $tx->update(['status' => TransactionStatus::FALHADO]);
            return response()->json(['success' => false, 'error' => 'Failed to create PIX transaction.'], 500);
        }

        // 📡 Webhook (não bloqueia resposta)
        if ($user->webhook_enabled && $user->webhook_in_url) {
            SendWebhookPixCreatedJob::dispatch($user->id, $tx->id);
        }

        // ✅ Retorno final rápido
        return response()->json([
            'success'      => true,
            'transaction_id' => $tx->id,
            'external_id'  => $data['external_id'],
            'status'       => $tx->status,
            'amount'       => $amount,
            'fee'          => $tx->fee,
            'txid'         => $tx->txid,
            'qr_code_text' => $tx->provider_payload['qr_code_text'] ?? null,
        ]);
    }

    // ⚡ Consulta por external_id
    public function statusByExternal(Request $r, string $ext)
    {
        $u = User::where('authkey', $r->header('X-Auth-Key'))
            ->where('secretkey', $r->header('X-Secret-Key'))
            ->first();
        if (!$u) return response()->json(['success' => false, 'error' => 'Unauthorized.'], 401);

        $tx = Transaction::where('external_reference', $ext)->where('user_id', $u->id)->first();
        if ($tx) {
            return response()->json([
                'success' => true,
                'type' => 'pix_in',
                'data' => [
                    'id' => $tx->id,
                    'status' => $tx->status,
                    'amount' => $tx->amount,
                    'fee' => $tx->fee,
                    'txid' => $tx->txid,
                    'provider_payload' => $tx->provider_payload,
                ]
            ]);
        }

        $w = Withdraw::where('external_id', $ext)->where('user_id', $u->id)->first();
        return $w ? response()->json([
            'success' => true,
            'type' => 'pix_out',
            'data' => [
                'id' => $w->id,
                'status' => $w->status,
                'amount' => $w->amount,
                'pix_key' => $w->pixkey,
                'pix_key_type' => $w->pixkey_type,
                'provider_ref' => $w->provider_reference,
            ]
        ]) : response()->json(['success' => false, 'error' => 'Not found.'], 404);
    }

    // 🔧 Helpers leves
    private function fee(User $u, float $a): float
    {
        if (!($u->tax_in_enabled ?? false)) return 0.0;
        return round(($u->tax_in_fixed ?? 0) + ($a * ($u->tax_in_percent ?? 0) / 100), 2);
    }
}
