<?php

namespace App\Http\Middleware;

use Illuminate\Cookie\Middleware\EncryptCookies as Middleware;

class EncryptCookies extends Middleware
{
    /**
     * Cookies que **não** devem ser criptografados.
     */
    protected $except = [
        'XSRF-TOKEN', // necessário para Livewire/Filament ler o CSRF no browser
    ];
}
