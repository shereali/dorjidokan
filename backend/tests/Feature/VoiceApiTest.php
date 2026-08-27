<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VoiceApiTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $slug): array
    {
        $tenant = Tenant::create(['name' => $slug, 'slug' => $slug, 'status' => 'active']);
        $user = User::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'admin']);

        return [$tenant, $user];
    }

    public function test_voice_registration_is_idempotent_and_tenant_scoped(): void
    {
        [$a,$user] = $this->tenant('alpha');
        $this->tenant('beta');
        Sanctum::actingAs($user, ['voice:write']);
        $headers = ['X-Tenant' => 'alpha', 'Idempotency-Key' => 'customer-register-0001', 'Accept-Language' => 'bn'];
        $payload = ['name' => 'Hasan', 'mobile_number' => '01700000000'];
        $first = $this->postJson('/api/v1/voice/customers/register', $payload, $headers)->assertCreated();
        $first->assertJsonPath('data.confirmation', 'Hasan সফলভাবে নিবন্ধিত হয়েছে।');
        $this->postJson('/api/v1/voice/customers/register', $payload, $headers)->assertStatus(201)->assertExactJson($first->json());
        $this->postJson('/api/v1/voice/customers/register', ['name' => 'Rahim', 'mobile_number' => '01800000000'], [...$headers, 'Idempotency-Key' => 'customer-register-0002', 'Accept-Language' => 'en'])
            ->assertCreated()->assertJsonPath('data.confirmation', 'Rahim registered successfully.');
        $this->assertDatabaseCount('customers', 2);
        $this->getJson('/api/v1/voice/search?query=01700000000', ['X-Tenant' => 'beta'])->assertNotFound();
    }

    public function test_token_without_voice_ability_is_rejected(): void
    {
        [$tenant,$user] = $this->tenant('alpha');
        Sanctum::actingAs($user, ['orders:read']);
        $this->getJson('/api/v1/voice/search?query=Hasan', ['X-Tenant' => $tenant->slug])->assertForbidden();
    }
}
