<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [

            // ⚙️ Metadados simples
            'app' => [
                'name' => config('app.name'),
                'env'  => config('app.env'),
            ],

            // 👤 Usuário autenticado (somente campos seguros)
            'auth' => [
                'user' => fn () => $request->user()
                    ? [
                        'id'             => $request->user()->id,
                        'name'           => $request->user()->name,
                        'nome_completo'  => $request->user()->nome_completo,
                        'email'          => $request->user()->email,
                        
                        // CPF mascarado — NÃO expõe o documento real
                        'cpf'  => maskCpfCnpj($request->user()->cpf_cnpj ?? null),
                    ]
                    : null,
            ],

            // ✉️ Mensagens flash
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
            ],

            // 🌐 Idioma da aplicação
            'locale' => app()->getLocale(),
        ]);
    }
}

/**
 * Função auxiliar para mascarar CPF/CNPJ
 */
if (! function_exists('maskCpfCnpj')) {
    function maskCpfCnpj(?string $doc)
    {
        if (!$doc) {
            return null;
        }

        // CPF: 11 dígitos
        if (strlen($doc) === 11) {
            return substr($doc, 0, 3) . '.***.***-' . substr($doc, -2);
        }

        // CNPJ: 14 dígitos
        if (strlen($doc) === 14) {
            return '**.***.***/****-' . substr($doc, -2);
        }

        return 'Documento inválido';
    }
}
