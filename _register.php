<?php

declare(strict_types=1);

use Dayploy\OpenTelemetrySymfony\HttpClientInstrumentation;
use Dayploy\OpenTelemetrySymfony\MessengerInstrumentation;
use Dayploy\OpenTelemetrySymfony\SymfonyInstrumentation;
use OpenTelemetry\SDK\Sdk;

if (!class_exists(Sdk::class)) {
    return;
}

if (extension_loaded('opentelemetry') === false) {
    trigger_error('The opentelemetry extension must be loaded in order to autoload the OpenTelemetry Symfony instrumentation', E_USER_WARNING);

    return;
}

SymfonyInstrumentation::register();
MessengerInstrumentation::register();
HttpClientInstrumentation::register();
