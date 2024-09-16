<?php

declare(strict_types=1);

namespace Dayploy\OpenTelemetrySymfony;

use function assert;
use OpenTelemetry\Context\Propagation\PropagationGetterInterface;
use Symfony\Component\HttpFoundation\Request;

final class RequestPropagationGetter implements PropagationGetterInterface
{
    public static function instance(): self
    {
        static $instance;

        return $instance ??= new self();
    }

    public function keys($carrier): array
    {
        assert($carrier instanceof Request);

        return $carrier->headers->keys();
    }

    public function get($carrier, string $key) : ?string
    {
        assert($carrier instanceof Request);

        return $carrier->headers->get($key);
    }
}
