<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class ApiController extends Controller
{
    /**
     * Exibe a página API com as credenciais e taxas do usuário autenticado.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        return Inertia::render('Api', [
            'user' => [
                'id'        => $user->id,
                'name'      => $user->nome_completo ?? $user->name ?? null,
                'email'     => $user->email,
                'authkey'   => $user->authkey,
                'secretkey' => $user->secretkey,

                // 🧾 Taxas (tipagem explícita para o front)
                'tax_in_enabled'   => (bool) ($user->tax_in_enabled ?? false),
                'tax_out_enabled'  => (bool) ($user->tax_out_enabled ?? false),
                'tax_in_fixed'     => isset($user->tax_in_fixed) ? (float) $user->tax_in_fixed : 0.0,
                'tax_in_percent'   => isset($user->tax_in_percent) ? (float) $user->tax_in_percent : 0.0,
                'tax_out_fixed'    => isset($user->tax_out_fixed) ? (float) $user->tax_out_fixed : 0.0,
                'tax_out_percent'  => isset($user->tax_out_percent) ? (float) $user->tax_out_percent : 0.0,

                // 🔔 Webhooks (alinha com UserWebhookResource)
                'webhook_enabled'  => (bool) ($user->webhook_enabled ?? false),
                'webhook_in_url'   => $user->webhook_in_url ?? null,
                'webhook_out_url'  => $user->webhook_out_url ?? null,
            ],
        ]);
    }
}
