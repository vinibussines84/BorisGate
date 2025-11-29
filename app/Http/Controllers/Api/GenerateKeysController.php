<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\User;

class GenerateKeysController extends Controller
{
    public function store(Request $request)
    {
        $user = auth()->user();

        // 🔒 Se o usuário não tem 2FA ativo, redireciona
        if (!$user->google2fa_enabled) {
            return response()->json([
                'success' => false,
                'requires_2fa' => true,
                'redirect' => route('setup.2fa'),
                'message' => 'Você precisa ativar o Google Authenticator antes de gerar chaves.'
            ], 403);
        }

        // ✅ Gera novas chaves
        $user->authkey    = strtoupper(Str::random(10));
        $user->partner_id = 'PRT-' . strtoupper(Str::random(10));
        $user->secret_key = 'sk_live_' . Str::random(32);
        $user->save();

        return response()->json([
            'success'    => true,
            'message'    => 'Chaves geradas e salvas com sucesso.',
            'auth_key'   => $user->authkey,
            'partner_id' => $user->partner_id,
            'secret_key' => $user->secret_key,
        ]);
    }
}
