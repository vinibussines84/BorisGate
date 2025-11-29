<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    // Confia em todos os proxies (Cloudflare/Nginx/ELB). Ajuste se quiser restringir.
    protected $proxies = '*';

    // Aceita todos os cabeçalhos X-Forwarded-* padrão
    protected $headers = Request::HEADER_X_FORWARDED_ALL;
}
