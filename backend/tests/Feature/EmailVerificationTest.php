<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function link(User $user, ?string $hash = null): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addHour(), ['user' => $user->public_id, 'hash' => $hash ?? sha1($user->email)]);
    }

    public function test_onboarding_sends_a_verification_email_and_starts_unverified(): void
    {
        Notification::fake();
        Plan::create(['code' => 'starter', 'name' => 'Starter', 'price_minor' => 0, 'currency' => 'BDT', 'billing_interval' => 'month', 'feature_limits' => [], 'active' => true]);

        $this->withHeader('Origin', 'http://localhost')->postJson('/api/v1/onboarding', ['business_name' => 'Verify Me', 'slug' => 'verify-me', 'name' => 'Owner', 'email' => 'owner@verify.test', 'password' => 'VerySecret123!', 'password_confirmation' => 'VerySecret123!', 'locale' => 'en'])
            ->assertCreated()->assertJsonPath('data.user.email_verified', false)->assertJsonPath('data.user.role', 'owner');

        $user = User::where('email', 'owner@verify.test')->firstOrFail();
        $this->assertFalse($user->hasVerifiedEmail());
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_signed_link_marks_the_email_verified_and_redirects_to_the_app(): void
    {
        $user = User::factory()->unverified()->create();

        $this->get($this->link($user))->assertRedirect(rtrim(config('app.frontend_url'), '/').'/login?verified=1');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_tampered_or_unsigned_links_are_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $this->get($this->link($user, sha1('someone-else@example.com')))->assertForbidden();
        $this->get("/api/v1/auth/verify-email/{$user->public_id}/".sha1($user->email))->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_resend_notifies_unverified_users_only(): void
    {
        Notification::fake();
        $unverified = User::factory()->unverified()->create();
        $verified = User::factory()->create();

        Sanctum::actingAs($unverified, ['*']);
        $this->postJson('/api/v1/auth/email/resend')->assertStatus(202)->assertJsonPath('data.sent', true);
        Notification::assertSentTo($unverified, VerifyEmailNotification::class);

        Sanctum::actingAs($verified, ['*']);
        $this->postJson('/api/v1/auth/email/resend')->assertOk()->assertJsonPath('data.already_verified', true);
        Notification::assertNotSentTo($verified, VerifyEmailNotification::class);
    }

    public function test_resend_requires_authentication_and_is_throttled(): void
    {
        $this->postJson('/api/v1/auth/email/resend')->assertUnauthorized();

        Notification::fake();
        Sanctum::actingAs(User::factory()->unverified()->create(), ['*']);
        foreach (range(1, 3) as $i) {
            $this->postJson('/api/v1/auth/email/resend')->assertStatus(202);
        }
        $this->postJson('/api/v1/auth/email/resend')->assertStatus(429);
    }

    public function test_a_password_reset_also_proves_the_mailbox(): void
    {
        $user = User::factory()->unverified()->create();
        $tenant = Tenant::create(['name' => 'T', 'slug' => 't', 'status' => 'active']);
        $user->tenants()->attach($tenant, ['role' => 'owner']);
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/v1/auth/reset-password', ['email' => $user->email, 'token' => $token, 'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!'])->assertOk();

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_me_reports_the_verification_state(): void
    {
        $tenant = Tenant::create(['name' => 'T', 'slug' => 't2', 'status' => 'active']);
        $user = User::factory()->unverified()->create();
        $user->tenants()->attach($tenant, ['role' => 'owner']);
        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/v1/auth/me', ['X-Tenant' => 't2'])->assertOk()->assertJsonPath('data.email_verified', false);
    }
}
