<?php

declare(strict_types=1);

namespace Dayploy\OpenTelemetrySymfony;

use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\Context\Context;
use function OpenTelemetry\Instrumentation\hook;
use OpenTelemetry\SemConv\TraceAttributes;
use PDOStatement;

final class DoctrineInstrumentation
{
    public static function register(): void
    {
        $instrumentation = new CachedInstrumentation('dayploy.opentelemetry_symfony.doctrine');

        hook(
            PDOStatement::class,
            'execute',
            pre: static function (
                PDOStatement $connection,
                array $params,
                string $class,
                string $function,
                ?string $filename,
                ?int $lineno,
            ) use ($instrumentation): array {
                $builder = $instrumentation
                    ->tracer()
                    ->spanBuilder(\sprintf('Doctrine statement execute'))
                    ->setSpanKind(SpanKind::KIND_INTERNAL)
                    ->setAttribute(TraceAttributes::CODE_FILEPATH, $filename)
                    ->setAttribute('db.query', $connection->queryString)
                ;

                $parent = Context::getCurrent();
                $span = $builder
                    ->setParent($parent)
                    ->startSpan();

                $context = $span->storeInContext($parent);
                Context::storage()->attach($context);

                return $params;
            },
            post: static function (
                PDOStatement $connection,
                array $params,
                bool $result,
                ?\Throwable $exception
            ): void {
                $scope = Context::storage()->scope();
                if (null === $scope) {
                    return;
                }

                $scope->detach();
                $span = Span::fromContext($scope->context());

                if (null !== $exception) {
                    $span->recordException($exception, [
                        TraceAttributes::EXCEPTION_ESCAPED => true,
                    ]);
                }

                $span->end();
            }
        );
    }
}
