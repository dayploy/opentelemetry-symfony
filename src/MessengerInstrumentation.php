<?php

declare(strict_types=1);

namespace Dayploy\OpenTelemetrySymfony;

use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use function OpenTelemetry\Instrumentation\hook;
use OpenTelemetry\SemConv\TraceAttributes;
use Psr\Http\Message\MessageInterface;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\AmazonSqsTransport;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\RoutableMessageBus;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\TraceableMessageBus;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;

final class MessengerInstrumentation
{
    const NO_TRACES_CLIENTS = [
        AmazonSqsTransport::class,
        TraceableMessageBus::class,
        RoutableMessageBus::class,
    ];

    public static function register(): void
    {
        $instrumentation = new CachedInstrumentation('dayploy.opentelemetry_symfony.messenger');

        hook(
            MessageBusInterface::class,
            'dispatch',
            pre: static function (
                MessageBusInterface $bus,
                array $params,
                string $class,
                string $function,
                ?string $filename,
                ?int $lineno,
            ) use ($instrumentation): array {
                foreach (static::NO_TRACES_CLIENTS as $noTrace) {
                    if ($bus instanceof $noTrace) {
                        return [];
                    }
                }

                /** @var object|Envelope $message */
                $message = $params[0];

                $messageClass = \get_class($message);
                $prefix = 'Dispatch';

                if ($message instanceof Envelope) {
                    /** @var MessageInterface */
                    $internalMessage = $message->getMessage();
                    $messageClass = $internalMessage::class;

                    if (count($message->all(ReceivedStamp::class)) > 0) {
                        // consuming message
                        $prefix = 'Consume';
                    }
                }

                $builder = $instrumentation
                    ->tracer()
                    ->spanBuilder(\sprintf('%s %s', $prefix, $messageClass))
                    ->setSpanKind(SpanKind::KIND_INTERNAL)
                    ->setAttribute(TraceAttributes::CODE_FILEPATH, $filename)
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
                MessageBusInterface $bus,
                array $params,
                ?Envelope $result,
                ?\Throwable $exception
            ): void {
                foreach (static::NO_TRACES_CLIENTS as $noTrace) {
                    if ($bus instanceof $noTrace) {
                        return;
                    }
                }

                $scope = Context::storage()->scope();
                if (null === $scope) {
                    return;
                }

                $scope->detach();
                $span = Span::fromContext($scope->context());

                /** @var object|Envelope $message */
                $message = $params[0];
                if ($message instanceof Envelope) {
                    if (count($message->all(ReceivedStamp::class)) > 0) {
                        // consuming message
                        $span->setAttribute(LoggerSender::ATTRIBUTE, LoggerSender::get());
                        LoggerSender::reset();
                    }
                }

                if (null !== $exception) {
                    $span->recordException($exception, [
                        TraceAttributes::EXCEPTION_ESCAPED => true,
                    ]);
                    $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
                }

                $span->end();
            }
        );

        hook(
            SenderInterface::class,
            'send',
            pre: static function (
                SenderInterface $bus,
                array $params,
                string $class,
                string $function,
                ?string $filename,
                ?int $lineno,
            ) use ($instrumentation): array {
                foreach (static::NO_TRACES_CLIENTS as $noTrace) {
                    if ($bus instanceof $noTrace) {
                        return [];
                    }
                }

                /** @var Envelope $envelope */
                $envelope = $params[0];
                $messageClass = \get_class($envelope->getMessage());

                /** @psalm-suppress ArgumentTypeCoercion */
                $builder = $instrumentation
                    ->tracer()
                    ->spanBuilder(\sprintf('Sender %s', $messageClass))
                    ->setSpanKind(SpanKind::KIND_INTERNAL)
                    ->setAttribute(TraceAttributes::CODE_FUNCTION, $function)
                    ->setAttribute(TraceAttributes::CODE_NAMESPACE, $class)
                    ->setAttribute(TraceAttributes::CODE_FILEPATH, $filename)
                    ->setAttribute(TraceAttributes::CODE_LINENO, $lineno)
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
                SenderInterface $sender,
                array $params,
                ?Envelope $result,
                ?\Throwable $exception
            ): void {
                foreach (static::NO_TRACES_CLIENTS as $noTrace) {
                    if ($sender instanceof $noTrace) {
                        return;
                    }
                }

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
                    $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
                }

                $span->end();
            }
        );
    }
}
