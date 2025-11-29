<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;

class EnsureRpnetAuthenticated
{
    public function handle(Request $request, Closure $next)
    {
        // 1) Exige usuário logado no app (guard web)
        if (! Auth::guard('web')->check()) {
            return $this->toLogin($request, 'Faça login para continuar.');
        }

        $token   = session('rpnet_token');
        $expires = session('rpnet_token_expiration');

        // 2) Se não há token em sessão, tenta restaurar do cookie rpnet_remember
        if (! $token) {
            $restored = $this->tryRestoreFromRememberCookie($request);
            if ($restored) {
                // re-leia após restauração
                $token   = session('rpnet_token');
                $expires = session('rpnet_token_expiration');
            }
        }

        // 3) Continua faltando token → derruba
        if (! $token) {
            return $this->forceLogoutToLogin($request, 'Sua sessão RPNet não existe mais. Faça login novamente.');
        }

        // 4) Token expirado? Tenta restaurar do cookie (de novo) caso ainda válido
        if ($expires && now()->greaterThan($expires)) {
            $restored = $this->tryRestoreFromRememberCookie($request, force: true);
            if ($restored) {
                $token   = session('rpnet_token');
                $expires = session('rpnet_token_expiration');
            }

            if ($expires && now()->greaterThan($expires)) {
                return $this->forceLogoutToLogin($request, 'Sua sessão RPNet expirou. Faça login novamente.');
            }
        }

        return $next($request);
    }

    /**
     * Tenta restaurar dados RPNet a partir do cookie "rpnet_remember".
     * Não faz Auth::login — apenas repovoa a sessão RPNet.
     *
     * Estrutura esperada (base64(JSON)):
     *  { uid: string|int, t: string (token), exp: int (epoch seg) }
     */
    protected function tryRestoreFromRememberCookie(Request $request, bool $force = false): bool
    {
        $cookie = $request->cookie('rpnet_remember');
        if (! $cookie) {
            return false;
        }

        $decodedJson = base64_decode($cookie, true);
        if ($decodedJson === false) {
            // cookie inválido
            return false;
        }

        $data = json_decode($decodedJson, true);
        if (! is_array($data)) {
            return false;
        }

        $uid = $data['uid'] ?? null;
        $tok = $data['t']   ?? null;
        $exp = $data['exp'] ?? null;

        if (! $uid || ! $tok || ! $exp || ! is_numeric($exp)) {
            return false;
        }

        // Confere se o cookie ainda está dentro da janela de validade
        $expCarbon = Carbon::createFromTimestamp((int) $exp);
        if (now()->greaterThan($expCarbon)) {
            return false;
        }

        // Garante que o cookie pertence ao usuário atualmente autenticado
        $currentUser = Auth::guard('web')->user();
        if (! $currentUser) {
            return false;
        }
        if ((string) $currentUser->getAuthIdentifier() !== (string) $uid) {
            // Cookie de outro usuário → ignore
            return false;
        }

        // (Opcional/ideal) Validar token com a RPNet aqui antes de aceitar
        // ex.: if (! Rpnet::validate($tok)) return false;

        // Reconstrói sessão mínima RPNet
        session([
            'rpnet_token'            => $tok,
            'rpnet_token_expiration' => $expCarbon,
            'rpnet_login'            => [
                'id'   => $currentUser->getAuthIdentifier(),
                'name' => $currentUser->name ?? $currentUser->email ?? 'User',
            ],
            // 'rpnet_balance' pode ser carregado on-demand via endpoint
        ]);

        return true;
    }

    /**
     * Logout completo e redireciona para login — compatível com Inertia.
     */
    protected function forceLogoutToLogin(Request $request, string $message)
    {
        // Limpa dados RPNet
        session()->forget(['rpnet_token', 'rpnet_token_expiration', 'rpnet_login', 'rpnet_balance']);

        // Logout (guard web)
        Auth::guard('web')->logout();

        // Invalida sessão, regenera token e limpa "intended"
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        session()->forget('url.intended');

        // API / JSON
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success'  => false,
                'message'  => $message,
                'redirect' => route('login'),
            ], 401);
        }

        // Inertia GET → 409 + X-Inertia-Location (evita loop/throbber)
        if ($request->isMethod('GET') && $request->header('X-Inertia')) {
            return response('', 409)->header('X-Inertia-Location', route('login'));
        }

        // Fallback: redirect tradicional
        return redirect()->route('login')->with('status', $message);
    }

    protected function toLogin(Request $request, string $message)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['success' => false, 'message' => $message], 401);
        }

        if ($request->isMethod('GET') && $request->header('X-Inertia')) {
            return response('', 409)->header('X-Inertia-Location', route('login'));
        }

        return redirect()->route('login')->with('status', $message);
    }
}
