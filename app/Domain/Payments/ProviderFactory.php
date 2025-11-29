<?php

namespace App\Domain\Payments;

use App\Domain\Payments\Contracts\InboundPaymentsProvider;
use App\Models\Provider;

final class ProviderFactory
{
    public static function makeInbound(Provider $provider): InboundPaymentsProvider
    {
        $class = $provider->service_class;

        if (!class_exists($class)) {
            throw new \RuntimeException("Service class não encontrada: {$class}");
        }

        $instance = app()->make($class, [
            'config' => (array) $provider->config ?: [],
        ]);

        if (!$instance instanceof InboundPaymentsProvider) {
            throw new \RuntimeException("{$class} não implementa InboundPaymentsProvider.");
        }

        return $instance;
    }
}
