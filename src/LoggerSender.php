<?php

declare(strict_types=1);

namespace Dayploy\OpenTelemetrySymfony;

use function OpenTelemetry\Instrumentation\hook;
use Psr\Log\LoggerInterface;

final class LoggerSender
{
    const ATTRIBUTE = 'app.haslog';
    public static bool $sendTraces = false;

    public static function register(): void
    {
        $methods = [
            'emergency',
            'alert',
            'critical',
            'error',
            'warning',
        ];

        foreach ($methods as $method) {
            hook(
                LoggerInterface::class,
                $method,
                pre: static function (
                    LoggerInterface $bus,
                    array $params,
                    string $class,
                    string $function,
                    ?string $filename,
                    ?int $lineno,
                ): array {
                    static::$sendTraces = true;

                    return [];
                },
            );
        }
    }

    public static function get(): bool
    {
        return static::$sendTraces;
    }

    public static function reset(): void
    {
        static::$sendTraces = false;
    }
}
