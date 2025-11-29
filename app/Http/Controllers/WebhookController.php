<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WebhookLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class WebhookController extends Controller
{
    /**
     * Exibe a página principal de gerenciamento de webhooks.
     */
    public function index()
    {
        $user = Auth::user();

        // Monta os webhooks configurados
        $webhooks = collect([
            [
                'id' => 'in',
                'type' => 'transacoes',
                'url' => $user->webhook_in_url,
                'created_at' => $user->created_at,
            ],
            [
                'id' => 'out',
                'type' => 'saques',
                'url' => $user->webhook_out_url,
                'created_at' => $user->created_at,
            ],
        ])
        ->filter(fn($w) => filled($w['url']))
        ->values();

        return Inertia::render('Webhooks/Index', [
            'webhooks' => $webhooks,
        ]);
    }

    /**
     * Salva ou atualiza o webhook do usuário autenticado.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'url'  => ['required', 'url', 'max:255'],
            'type' => ['required', 'string', 'in:transacoes,saques,todos'],
        ]);

        $user = Auth::user();

        switch ($validated['type']) {
            case 'transacoes': // IN
                $user->update([
                    'webhook_in_url' => $validated['url'],
                    'webhook_enabled' => true,
                ]);
                break;

            case 'saques': // OUT
                $user->update([
                    'webhook_out_url' => $validated['url'],
                    'webhook_enabled' => true,
                ]);
                break;

            case 'todos': // Ambos
                $user->update([
                    'webhook_in_url' => $validated['url'],
                    'webhook_out_url' => $validated['url'],
                    'webhook_enabled' => true,
                ]);
                break;
        }

        return redirect()
            ->route('webhooks.index')
            ->with('success', '✔️ Webhook salvo com sucesso!');
    }

    /**
     * Retorna os logs dos webhooks enviados do usuário atual (JSON).
     */
    public function logs()
    {
        return WebhookLog::where('user_id', Auth::id())
            ->orderByDesc('id')
            ->limit(100)
            ->get();
    }

    /**
     * Reenvia manualmente um webhook salvo no log.
     */
    public function resend($logId)
    {
        $user = Auth::user();

        $log = WebhookLog::where('user_id', $user->id)->findOrFail($logId);

        // Define a URL com base no tipo
        $url = match ($log->type) {
            'out' => $user->webhook_out_url,
            default => $user->webhook_in_url,
        };

        if (!$url) {
            return response()->json([
                'success' => false,
                'message' => 'Nenhum webhook configurado para este tipo.',
            ], 404);
        }

        try {
            $payload = is_array($log->payload)
                ? $log->payload
                : (json_decode($log->payload, true) ?? []);

            $response = Http::asJson()->post($url, $payload);

            $newLog = WebhookLog::create([
                'user_id'       => $user->id,
                'type'          => $log->type,
                'url'           => $url,
                'payload'       => $payload,
                'status'        => $response->successful() ? 'success' : 'error',
                'response_code' => $response->status(),
                'response_body' => $response->body() ?? '',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Webhook reenviado com sucesso!',
                'log'     => $newLog,
            ]);
        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Erro ao reenviar webhook: ' . $e->getMessage(),
            ], 500);
        }
    }
}
