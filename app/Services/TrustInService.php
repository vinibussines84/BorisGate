<?php
declare(strict_types=1);

namespace App\Services;

final class TrustInService
{
    public function __construct()
    {
        // injete dependências aqui, se precisar
    }

    // exemplo de método
    public function ping(): string
    {
        return 'trust-in ok';
    }
}
