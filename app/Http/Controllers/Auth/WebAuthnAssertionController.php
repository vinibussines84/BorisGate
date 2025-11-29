<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cookie;
use App\Models\User;
use Illuminate\Support\Carbon;

class WebAuthnAssertionController extends Controller
{
    private function b64url(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    /**
     * Retorna options para autenticar com Passkey (Face ID / Touch ID).
     * Recebe "user" (e-mail ou CPF). Guest pode chamar.
     * Força autenticador de plataforma e verificação do usuário.
     */
    public function options(Request $request): JsonResponse
    {
        $request->validate([
            'user' => 'required|string',
        ]);

        $identity = trim((string) $request->input('user'));

        // Encontrar usuário por e-mail ou cpf_cnpj
        $query = User::query();
        if (str_contains($identity, '@')) {
            $query->where('email', $identity);
        } else {
            $digits = preg_replace('/\D+/', '', $identity);
            $query->where('cpf_cnpj', $digits);
        }

        /** @var User|null $user */
        $user = $query->first();
        if (! $user) {
            return response()->json(['message' => 'Usuário não encontrado.'], 404);
        }

        // Credenciais previamente salvas (somente plataforma -> transports 'internal')
        $allow = [];
        $creds = is_string($user->webauthn_credentials ?? null)
            ? json_decode($user->webauthn_credentials, true) ?: []
            : ($user->webauthn_credentials ?? []);

        foreach ($creds as $cred) {
            if (! empty($cred['id'])) {
                $allow[] = [
                    'type'       => 'public-key',
                    'id'         => $cred['id'],          // base64url
                    'transports' => ['internal'],         // 🔴 restringe a plataforma (evita usb/ble/nfc)
                ];
            }
        }

        // rpId e origin esperados
        $rpId   = config('webauthn.rp_id') ?: parse_url($request->getSchemeAndHttpHost(), PHP_URL_HOST);
        $origin = $request->getSchemeAndHttpHost();

        // challenge aleatório
        $challenge = random_bytes(32);
        session([
            'webauthn.assert.challenge' => $this->b64url($challenge),
            'webauthn.assert.user_id'   => (string) $user->getAuthIdentifier(),
            'webauthn.assert.rp_id'     => $rpId,
            'webauthn.assert.origin'    => $origin,
        ]);

        $publicKey = [
            'challenge'        => $this->b64url($challenge),
            'timeout'          => 60000,
            'rpId'             => $rpId,
            'userVerification' => 'required',     // 🔴 força Face ID/Touch ID
            'allowCredentials' => $allow,         // pode ser vazio: o SO sugere a conta

            // (Opcional – WebAuthn L3; alguns navegadores já entendem)
            'hints'            => ['client-device'], // sugere “usar este dispositivo”
        ];

        return response()->json(['publicKey' => $publicKey]);
    }

    /**
     * Verifica a assertion (stub). NÃO usa Auth::login().
     * Em produção: validar assinatura (authenticatorData + clientDataJSON + signature),
     * checar challenge/origin/rpIdHash e atualizar signCount.
     *
     * Ao final, grava cookie "rpnet_remember" para o middleware restaurar a sessão RPNet.
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'id'                         => 'required|string',
            'rawId'                      => 'required|string',
            'type'                       => 'required|in:public-key',
            'response'                   => 'required|array',
            'response.clientDataJSON'    => 'required|string',
            'response.authenticatorData' => 'required|string',
            'response.signature'         => 'required|string',
            'response.userHandle'        => 'nullable|string',
        ]);

        $expectedChallenge = session('webauthn.assert.challenge');
        $userId            = session('webauthn.assert.user_id');
        $expectedRpId      = session('webauthn.assert.rp_id');
        $expectedOrigin    = session('webauthn.assert.origin');

        if (! $expectedChallenge || ! $userId || ! $expectedRpId || ! $expectedOrigin) {
            return response()->json(['message' => 'Desafio expirado.'], 422);
        }

        /** @var User|null $user */
        $user = User::find($userId);
        if (! $user) {
            return response()->json(['message' => 'Usuário não encontrado.'], 404);
        }

        // 🔐 PONTO DE INTEGRAÇÃO (validação real WebAuthn):
        // - Validar clientDataJSON.type == "webauthn.get"
        // - Validar challenge/origin/rpIdHash
        // - Verificar assinatura (signature) com a publicKey salva
        // - Atualizar signCount da credencial correspondente
        // (Aqui aceitamos como stub para focar no fluxo de sessão RPNet)

        // === Emissão do cookie "rpnet_remember" ===
        // Pode gerar/renovar o token da RPNet aqui; se não tiver agora, use o que houver na sessão (ou null).
        $rpnetToken = session('rpnet_token'); // ou gere via serviço RPNet
        $days       = 30;                     // validade do cookie
        $minutes    = $days * 24 * 60;

        $payload = [
            'uid' => (string) $user->getAuthIdentifier(),
            't'   => $rpnetToken,                         // pode ser null; middleware tenta restaurar/renovar
            'exp' => now()->addDays($days)->timestamp,
        ];

        Cookie::queue(cookie(
            'rpnet_remember',
            base64_encode(json_encode($payload)),
            $minutes,
            '/',
            null,
            true,   // secure
            true,   // httpOnly
            false,  // raw
            'Lax'   // SameSite
        ));

        // (Opcional) já popular a sessão RPNet para pular o fallback do middleware
        if ($rpnetToken) {
            session([
                'rpnet_token'            => $rpnetToken,
                'rpnet_token_expiration' => Carbon::createFromTimestamp($payload['exp']),
                'rpnet_login'            => [
                    'id'   => $user->getAuthIdentifier(),
                    'name' => $user->name ?? $user->email ?? 'User',
                ],
            ]);
        }

        // Limpa material sensível de sessão
        session()->forget([
            'webauthn.assert.challenge',
            'webauthn.assert.user_id',
            'webauthn.assert.rp_id',
            'webauthn.assert.origin',
        ]);

        return response()->json([
            'success'  => true,
            'redirect' => url('/rpnet/connect'), // sua rota pública para iniciar/renovar sessão RPNet
        ]);
    }
}
