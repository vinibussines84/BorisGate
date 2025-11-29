<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class WebAuthnRegistrationController extends Controller
{
    /**
     * base64url encode (binário -> string)
     */
    private function b64url(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    /**
     * base64url decode (string -> binário)
     */
    private function b64urlToBin(string $b64url): string
    {
        $b64 = strtr($b64url, '-_', '+/');
        $pad = strlen($b64) % 4;
        if ($pad) {
            $b64 .= str_repeat('=', 4 - $pad);
        }
        return base64_decode($b64);
    }

    /**
     * OPTIONS: retorna o objeto publicKey para navigator.credentials.create()
     * Requer usuário autenticado.
     * ⚠️ Ajustado para priorizar/forçar Face ID/Touch ID (autenticador de plataforma).
     */
    public function options(Request $request): JsonResponse
    {
        if (! Auth::check()) {
            return response()->json(['message' => 'Não autenticado.'], 401);
        }

        $user   = $request->user();
        // rpId = host (sem esquema/porta). Se tiver fixo em config, prefira.
        $rpId   = config('webauthn.rp_id') ?: parse_url($request->getSchemeAndHttpHost(), PHP_URL_HOST);
        $rpName = config('app.name', 'App');

        // challenge aleatório (32 bytes)
        $challenge = random_bytes(32);

        // Guardamos o que será verificado no verify()
        session([
            'webauthn.registration.challenge' => $this->b64url($challenge),
            'webauthn.registration.user_id'   => (string) $user->getAuthIdentifier(),
            'webauthn.registration.rp_id'     => $rpId,
            'webauthn.registration.origin'    => $request->getSchemeAndHttpHost(),
        ]);

        // excludeCredentials com base no que já estiver salvo no usuário
        $exclude = [];
        $existingCreds = is_string($user->webauthn_credentials ?? null)
            ? json_decode($user->webauthn_credentials, true) ?: []
            : ($user->webauthn_credentials ?? []);

        foreach ($existingCreds as $cred) {
            if (! empty($cred['id'])) {
                $exclude[] = [
                    'type' => 'public-key',
                    'id'   => $cred['id'], // id em base64url
                    // opcionalmente poderíamos informar transports => ['internal']
                ];
            }
        }

        $publicKey = [
            'challenge' => $this->b64url($challenge),
            'rp'        => ['id' => $rpId, 'name' => $rpName],
            'user'      => [
                // O navegador espera bytes; enviamos base64url e convertemos no front.
                'id'          => $this->b64url((string) $user->getAuthIdentifier()),
                'name'        => $user->email ?? ('user-'.$user->getAuthIdentifier()),
                'displayName' => $user->name ?? ($user->email ?? 'Usuário'),
            ],
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7],   // ES256
                ['type' => 'public-key', 'alg' => -257], // RS256
            ],
            'timeout'     => 60000,
            'attestation' => 'none',

            // 🔴 Forçar autenticador do dispositivo e biometria/senha do aparelho
            'authenticatorSelection' => [
                'authenticatorAttachment' => 'platform',   // Face ID / Touch ID (dispositivo)
                'residentKey'             => 'preferred',  // pode usar 'required' se quiser DPK
                'requireResidentKey'      => false,
                'userVerification'        => 'required',   // obriga Face ID/Touch ID
            ],

            // Evita duplicar mesma credencial
            'excludeCredentials' => $exclude,

            // (Opcional – WebAuthn L3; alguns navegadores já entendem)
            'hints' => ['client-device'], // sugere “usar este dispositivo”
        ];

        return response()->json(['publicKey' => $publicKey]);
    }

    /**
     * VERIFY: recebe a attestation do navegador e vincula a credencial ao usuário.
     * (Stub seguro — não faz verificação criptográfica; plugar lib depois)
     */
    public function verify(Request $request): JsonResponse
    {
        if (! Auth::check()) {
            return response()->json(['message' => 'Não autenticado.'], 401);
        }

        $request->validate([
            'id'                         => 'required|string',
            'rawId'                      => 'required|string',
            'type'                       => 'required|in:public-key',
            'response'                   => 'required|array',
            'response.clientDataJSON'    => 'required|string',
            'response.attestationObject' => 'required|string',
        ]);

        $expectedChallenge = session('webauthn.registration.challenge');
        $expectedUserId    = session('webauthn.registration.user_id');
        $expectedRpId      = session('webauthn.registration.rp_id');
        $expectedOrigin    = session('webauthn.registration.origin');

        if (! $expectedChallenge || ! $expectedUserId || ! $expectedRpId || ! $expectedOrigin) {
            return response()->json(['message' => 'Desafio expirado.'], 422);
        }

        $user = $request->user();

        // >>> Ponto de integração com biblioteca WebAuthn (validação real):
        // - Validar clientDataJSON (type == "webauthn.create", challenge, origin)
        // - Validar attestationObject (rpIdHash, AAGUID, public key, counter, etc.)
        // - Extrair publicKey (COSE) e signCount
        //
        // Enquanto a validação real não está plugada: persistimos credentialId.

        $credentialIdB64url = $request->input('rawId'); // credentialId (base64url)
        $transports         = $request->input('transports', ['internal']); // plataforma

        // Evita duplicar a credencial
        $creds = is_string($user->webauthn_credentials ?? null)
            ? json_decode($user->webauthn_credentials, true) ?: []
            : ($user->webauthn_credentials ?? []);

        $already = false;
        foreach ($creds as $c) {
            if (($c['id'] ?? null) === $credentialIdB64url) {
                $already = true;
                break;
            }
        }

        if (! $already) {
            $creds[] = [
                'id'         => $credentialIdB64url,
                'publicKey'  => null, // preencher quando validar de verdade
                'signCount'  => 0,
                'transports' => $transports, // ['internal']
                'added_at'   => now()->toIso8601String(),
            ];
        }

        // Se a coluna existir, persistimos no usuário; senão, ao menos marcamos em sessão.
        if (Schema::hasColumn('users', 'webauthn_credentials')) {
            $user->webauthn_credentials = json_encode($creds);
            $user->webauthn_enabled     = true;
            $user->last_passkey_at      = now();
            $user->save();
        } else {
            session(['webauthn.enabled' => true, 'webauthn.last_cred' => $credentialIdB64url]);
        }

        // Limpa material sensível de sessão
        session()->forget([
            'webauthn.registration.challenge',
            'webauthn.registration.user_id',
            'webauthn.registration.rp_id',
            'webauthn.registration.origin',
        ]);

        return response()->json(['success' => true]);
    }
}
