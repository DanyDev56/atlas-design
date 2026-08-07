<?php

declare(strict_types=1);

namespace Atlas\Platform\Observability;

use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Sdk;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SemConv\ResourceAttributes;

final class Telemetry
{
    private static bool $bootstrapped = false;

    private static string $tracesExporter = 'none';

    private static string $serviceName = 'atlas-app';

    private static string $otlpEndpoint = 'http://otel-collector:4318';

    public static function configure(
        string $tracesExporter,
        string $serviceName,
        string $otlpEndpoint,
    ): void {
        self::$tracesExporter = $tracesExporter;
        self::$serviceName = $serviceName;
        self::$otlpEndpoint = $otlpEndpoint;
    }

    public static function isEnabled(): bool
    {
        return self::$tracesExporter === 'otlp';
    }

    public static function serviceName(): string
    {
        return self::$serviceName;
    }

    public static function bootstrap(): void
    {
        if (! self::isEnabled() || self::$bootstrapped) {
            return;
        }

        $endpoint = rtrim(self::$otlpEndpoint, '/').'/v1/traces';

        $transport = (new OtlpHttpTransportFactory())->create($endpoint, 'application/json');
        $exporter = new SpanExporter($transport);

        $resource = ResourceInfoFactory::emptyResource()->merge(
            ResourceInfo::create(Attributes::create([
                ResourceAttributes::SERVICE_NAME => self::$serviceName,
            ])),
        );

        $tracerProvider = TracerProvider::builder()
            ->addSpanProcessor(new SimpleSpanProcessor($exporter))
            ->setResource($resource)
            ->build();

        Sdk::builder()
            ->setTracerProvider($tracerProvider)
            ->setAutoShutdown(true)
            ->buildAndRegisterGlobal();

        self::$bootstrapped = true;
    }
}
