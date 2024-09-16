<?php

declare(strict_types=1);

namespace Dayploy\OpenTelemetrySymfony;

use function assert;
use OpenTelemetry\Context\Propagation\PropagationSetterInterface;
use Symfony\Component\HttpFoundation\Response;

final class ResponsePropagationSetter implements PropagationSetterInterface
{
    public static function instance(): self
    {
        static $instance;

        return $instance ??= new self();
    }

    public function keys($carrier): array
    {
        assert($carrier instanceof Response);

        return $carrier->headers->keys();
    }

    public function set(&$carrier, string $key, string $value): void
    {
        assert($carrier instanceof Response);

        $carrier->headers->set($key, $value);
    }
}
