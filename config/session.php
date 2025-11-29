<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Session Driver
    |--------------------------------------------------------------------------
    |
    | No modo local, "file" é leve e simples.
    | Em produção, "database" ou "redis" é mais confiável.
    |
    */

    'driver' => env('SESSION_DRIVER', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Session Lifetime
    |--------------------------------------------------------------------------
    |
    | Tempo em minutos que a sessão permanece ativa.
    | O padrão é 120 min (2h).
    |
    */

    'lifetime' => (int) env('SESSION_LIFETIME', 120),
    'expire_on_close' => env('SESSION_EXPIRE_ON_CLOSE', false),

    /*
    |--------------------------------------------------------------------------
    | Session Encryption
    |--------------------------------------------------------------------------
    |
    | Define se os dados da sessão devem ser criptografados.
    | Normalmente "false" é suficiente, pois cookies já são protegidos.
    |
    */

    'encrypt' => env('SESSION_ENCRYPT', false),

    /*
    |--------------------------------------------------------------------------
    | Session File Location
    |--------------------------------------------------------------------------
    */

    'files' => storage_path('framework/sessions'),

    /*
    |--------------------------------------------------------------------------
    | Session Database Connection / Table
    |--------------------------------------------------------------------------
    */

    'connection' => env('SESSION_CONNECTION'),
    'table' => env('SESSION_TABLE', 'sessions'),

    /*
    |--------------------------------------------------------------------------
    | Cache Store (para Redis, Dynamo, etc.)
    |--------------------------------------------------------------------------
    */

    'store' => env('SESSION_STORE'),

    /*
    |--------------------------------------------------------------------------
    | Session Sweeping Lottery
    |--------------------------------------------------------------------------
    |
    | Define a frequência em que sessões expiradas são limpas automaticamente.
    | [2, 100] => 2% de chance a cada request.
    |
    */

    'lottery' => [2, 100],

    /*
    |--------------------------------------------------------------------------
    | Nome do Cookie de Sessão
    |--------------------------------------------------------------------------
    |
    | O nome do cookie que armazena a sessão.
    | Mantém coerência entre app principal e painel Filament.
    |
    */

    'cookie' => env('SESSION_COOKIE', Str::slug(env('APP_NAME', 'EquitPay')).'_session'),

    /*
    |--------------------------------------------------------------------------
    | Caminho do Cookie
    |--------------------------------------------------------------------------
    |
    | Sempre "/" para ser válido em todas as rotas (incluindo /xota).
    |
    */

    'path' => env('SESSION_PATH', '/'),

    /*
    |--------------------------------------------------------------------------
    | Domínio do Cookie
    |--------------------------------------------------------------------------
    |
    | ⚙️ IMPORTANTE:
    | - Em produção, defina ".seudominio.com" para abranger subdomínios.
    | - Em localhost, use "localhost".
    |
    | Exemplo:
    |   SESSION_DOMAIN=.jcbank.online
    |   SESSION_DOMAIN=localhost
    |
    */

    'domain' => env('SESSION_DOMAIN', null),

    /*
    |--------------------------------------------------------------------------
    | HTTPS Only Cookies
    |--------------------------------------------------------------------------
    |
    | Em localhost (HTTP): false
    | Em produção (HTTPS): true
    |
    */

    'secure' => env('SESSION_SECURE_COOKIE', false),

    /*
    |--------------------------------------------------------------------------
    | HTTP Access Only
    |--------------------------------------------------------------------------
    |
    | Impede que scripts JavaScript acessem o cookie.
    |
    */

    'http_only' => env('SESSION_HTTP_ONLY', true),

    /*
    |--------------------------------------------------------------------------
    | Same-Site Cookies
    |--------------------------------------------------------------------------
    |
    | "lax" é o mais seguro e compatível na maioria dos casos.
    | Use "none" apenas se houver subdomínios ou iframe cross-site.
    |
    */

    'same_site' => env('SESSION_SAME_SITE', 'lax'),

    /*
    |--------------------------------------------------------------------------
    | Partitioned Cookies
    |--------------------------------------------------------------------------
    |
    | Mantém desativado, a menos que use contexts de cross-site complexos.
    |
    */

    'partitioned' => env('SESSION_PARTITIONED_COOKIE', false),

];
