<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Observability;

use Atlas\Platform\Observability\Telemetry;
use PHPUnit\Framework\TestCase;

final class TelemetryTest extends TestCase
{
    protected function tearDown(): void
    {
        Telemetry::configure('none', 'atlas-app', 'http://otel-collector:4318');

        parent::tearDown();
    }

    public function test_telemetry_is_disabled_when_exporter_is_none(): void
    {
        Telemetry::configure('none', 'atlas-app', 'http://otel-collector:4318');

        $this->assertFalse(Telemetry::isEnabled());
    }

    public function test_telemetry_is_enabled_for_otlp_exporter(): void
    {
        Telemetry::configure('otlp', 'atlas-app', 'http://otel-collector:4318');

        $this->assertTrue(Telemetry::isEnabled());
    }

    public function test_service_name_is_configurable(): void
    {
        Telemetry::configure('none', 'custom-service', 'http://otel-collector:4318');

        $this->assertSame('custom-service', Telemetry::serviceName());
    }
}
