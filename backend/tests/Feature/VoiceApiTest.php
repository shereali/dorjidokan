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

    public function test_admin_can_mint_voice_service_token(): void
    {
        [$tenant,$user] = $this->tenant('alpha');
        Sanctum::actingAs($user, ['app:read', 'app:write']);
        $response = $this->postJson('/api/v1/voice-tokens', [], ['X-Tenant' => $tenant->slug])->assertOk();
        $token = $response->json('data.token');
        $this->assertIsString($token);
        $this->assertStringContainsString('|', $token);
        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $user->id, 'abilities' => json_encode(['voice:write'])]);
        $this->assertDatabaseHas('audit_logs', ['tenant_id' => $tenant->id, 'action' => 'voice_token.created']);
    }

    public function test_voice_search_uses_fuzzy_name_matching(): void
    {
        [$tenant,$user] = $this->tenant('alpha');
        Sanctum::actingAs($user, ['voice:write']);
        $headers = ['X-Tenant' => $tenant->slug, 'Accept-Language' => 'en'];
        $this->postJson('/api/v1/voice/customers/register', ['name' => 'Mohammad Rahim', 'mobile_number' => '01710000000'], [...$headers, 'Idempotency-Key' => 'fuzzy-register-000001'])->assertCreated();
        $this->postJson('/api/v1/voice/customers/register', ['name' => 'Karim Uddin', 'mobile_number' => '01720000000'], [...$headers, 'Idempotency-Key' => 'fuzzy-register-000002'])->assertCreated();

        // Misspelled/spoken name still resolves via edit distance.
        $this->getJson('/api/v1/voice/search?query=Mohmmad%20Rahim', $headers)->assertOk()->assertJsonPath('data.matches.0.customer.name', 'Mohammad Rahim');
        // Exact mobile still wins.
        $this->getJson('/api/v1/voice/search?query=01720000000', $headers)->assertOk()->assertJsonPath('data.matches.0.customer.name', 'Karim Uddin');
    }

    public function test_voice_search_requires_bearer_token_not_session(): void
    {
        [$tenant,$user] = $this->tenant('alpha');
        // Session-only auth (no current access token) must be rejected for voice.
        $this->actingAs($user)->getJson('/api/v1/voice/search?query=Hasan', ['X-Tenant' => $tenant->slug])->assertForbidden();
    }
}
