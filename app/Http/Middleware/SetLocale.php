<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

/**
 * 🌍 Middleware responsável por definir o idioma ativo
 * com base na sessão do usuário.
 *
 * Ele é executado em todas as rotas web (já configurado em bootstrap/app.php)
 * e garante que o idioma selecionado nas bandeiras (🇧🇷 / 🇨🇳)
 * permaneça ativo em toda a navegação.
 */
class SetLocale
{
    /**
     * Manipula a requisição e aplica o idioma da sessão.
     */
    public function handle(Request $request, Closure $next)
    {
        // Obtém o idioma atual salvo na sessão (padrão: pt)
        $locale = session('locale', 'pt');

        // Garante que apenas idiomas suportados sejam aplicados
        $supported = ['pt', 'zh'];

        if (!in_array($locale, $supported)) {
            $locale = 'pt';
        }

        // Define o idioma da aplicação
        App::setLocale($locale);

        // Continua o fluxo da requisição
        return $next($request);
    }
}
