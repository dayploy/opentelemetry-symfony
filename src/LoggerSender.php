<?php

declare(strict_types=1);

namespace Dayploy\OpenTelemetrySymfony;

use function OpenTelemetry\Instrumentation\hook;
use Psr\Log\LoggerInterface;

final class LoggerSender
{
    const ATTRIBUTE = 'app.haslog';
    private static array $hasWarningById = [];

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
                    $pid = getmypid();
                    static::$hasWarningById[$pid] = true;

                    return [];
                },
            );
        }
    }

    public static function get(): bool
    {
        $pid = getmypid();
        if (!key_exists($pid, static::$hasWarningById)) {
            return false;
        }

        return static::$hasWarningById[$pid];
    }

    public static function reset(): void
    {
        $pid = getmypid();
        static::$hasWarningById[$pid] = false;
    }
}
