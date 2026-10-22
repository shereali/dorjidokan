<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class MonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_reports_every_dependency_without_internal_detail(): void
    {
        $response = $this->getJson('/api/v1/health')->assertOk();

        $response->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.database', 'ok')
            ->assertJsonPath('checks.cache', 'ok')
            ->assertJsonPath('checks.storage', 'ok')
            ->assertJsonStructure(['status', 'checks' => ['database', 'cache', 'storage', 'queue'], 'time']);
        $this->assertStringNotContainsString('sqlite', strtolower($response->getContent()));
    }

    public function test_health_degrades_when_the_queue_backlog_is_too_large(): void
    {
        config(['queue.default' => 'database', 'monitoring.queue_backlog_warning' => 2]);
        foreach (range(1, 3) as $i) {
            \DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->timestamp, 'created_at' => now()->timestamp]);
        }

        $this->getJson('/api/v1/health')->assertOk()->assertJsonPath('status', 'degraded')->assertJsonPath('checks.queue', 'degraded');
    }

    public function test_metrics_endpoint_is_disabled_without_a_token_and_protected_with_one(): void
    {
        config(['monitoring.metrics_token' => null]);
        $this->getJson('/api/v1/metrics')->assertNotFound();

        config(['monitoring.metrics_token' => 'secret-scrape-token']);
        $this->getJson('/api/v1/metrics')->assertUnauthorized();
        $this->getJson('/api/v1/metrics', ['Authorization' => 'Bearer wrong'])->assertUnauthorized();
    }

    public function test_metrics_expose_prometheus_gauges(): void
    {
        config(['monitoring.metrics_token' => 'secret-scrape-token']);
        Tenant::create(['name' => 'A', 'slug' => 'a', 'status' => 'active']);
        Tenant::create(['name' => 'B', 'slug' => 'b', 'status' => 'suspended']);

        $body = $this->get('/api/v1/metrics', ['Authorization' => 'Bearer secret-scrape-token'])->assertOk()->getContent();

        $this->assertStringContainsString('tailors_workshops{status="active"} 1', $body);
        $this->assertStringContainsString('tailors_workshops{status="suspended"} 1', $body);
        $this->assertStringContainsString('tailors_orders_created_24h 0', $body);
        $this->assertStringContainsString('tailors_failed_jobs ', $body);
        $this->assertStringContainsString('# TYPE tailors_queue_pending_jobs gauge', $body);
    }

    public function test_every_response_carries_a_request_id_and_valid_incoming_ids_are_kept(): void
    {
        $generated = $this->getJson('/api/v1/health')->headers->get('X-Request-Id');
        $this->assertNotEmpty($generated);

        $this->getJson('/api/v1/health', ['X-Request-Id' => 'trace-abcdef12'])->assertHeader('X-Request-Id', 'trace-abcdef12');
        $this->assertNotSame('bad id with spaces!', $this->getJson('/api/v1/health', ['X-Request-Id' => 'bad id with spaces!'])->headers->get('X-Request-Id'));
    }

    public function test_slow_requests_are_logged_with_route_and_duration(): void
    {
        config(['monitoring.slow_request_ms' => 1]);
        Log::spy();
        Log::shouldReceive('withContext')->zeroOrMoreTimes();

        $this->getJson('/api/v1/health');

        Log::shouldHaveReceived('warning')->withArgs(fn ($message, $context) => $message === 'slow_request' && ($context['route'] ?? null) === 'api/v1/health' && isset($context['duration_ms']))->atLeast()->once();
    }

    public function test_browser_errors_are_collected_truncated_and_validated(): void
    {
        Log::spy();

        $this->postJson('/api/v1/client-errors', ['message' => str_repeat('x', 900), 'stack' => str_repeat('s', 9000), 'url' => 'http://localhost/orders', 'kind' => 'vue'])->assertStatus(202);
        Log::shouldHaveReceived('error')->withArgs(fn ($message, $context) => $message === 'client_error' && strlen($context['message']) === 500 && strlen($context['stack']) === 4000 && $context['kind'] === 'vue')->once();

        $this->postJson('/api/v1/client-errors', [])->assertUnprocessable();
        $this->postJson('/api/v1/client-errors', ['message' => 'x', 'kind' => 'nonsense'])->assertUnprocessable();
    }

    public function test_orders_in_the_last_day_are_counted(): void
    {
        config(['monitoring.metrics_token' => 't']);
        $this->assertSame(0, Order::withoutGlobalScopes()->count());

        $this->get('/api/v1/metrics', ['Authorization' => 'Bearer t'])->assertSee('tailors_orders_created_24h 0');
    }
}
