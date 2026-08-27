<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuthRequest;
use App\Http\Requests\PasswordRecoveryRequest;
use App\Http\Requests\TwoFactorRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class AuthController extends Controller
{
    public function forgotPassword(PasswordRecoveryRequest $request): JsonResponse
    {
        $data = $request->validated();
        $tenant = Tenant::where('slug', $data['tenant'])->first();
        $user = User::where('email', $data['email'])->first();
        if ($tenant && $user && $user->tenants()->whereKey($tenant->id)->exists()) {
            Password::sendResetLink(['email' => $data['email']]);
        }

        return response()->json(['data' => ['message' => 'If that workshop account exists, a reset link has been sent.'], 'meta' => (object) [], 'errors' => []]);
    }

    public function resetPassword(PasswordRecoveryRequest $request): JsonResponse
    {
        $data = $request->validated();
        $tenant = Tenant::where('slug', $data['tenant'])->first();
        $user = User::where('email', $data['email'])->first();
        if (! $tenant || ! $user || ! $user->tenants()->whereKey($tenant->id)->exists()) {
            return $this->invalidReset();
        }
        $status = Password::reset($request->only('email', 'password', 'password_confirmation', 'token'), function (User $account, string $password) {
            $account->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
        });

        return $status === Password::PASSWORD_RESET
            ? response()->json(['data' => ['message' => 'Password reset. You can now sign in.'], 'meta' => (object) [], 'errors' => []])
            : $this->invalidReset();
    }

    public function login(AuthRequest $request): JsonResponse
    {
        $d = $request->validated();
        $tenant = Tenant::where('slug', $d['tenant'])->where('status', 'active')->first();
        $user = User::where('email', $d['email'])->first();
        if (! $tenant || ! $user || ! Hash::check($d['password'], $user->password) || ! $user->tenants()->whereKey($tenant->id)->exists()) {
            return response()->json(['data' => null, 'meta' => (object) [], 'errors' => [['code' => 'invalid_credentials', 'message' => 'The tenant or credentials are invalid.']]], 422);
        }
        if ($user->two_factor_confirmed_at && ! $this->verifyTwoFactor($user, $d['two_factor_code'] ?? '')) {
            return response()->json(['data' => null, 'meta' => ['two_factor_required' => true], 'errors' => [['code' => 'two_factor_required', 'message' => 'Enter the current authenticator or recovery code.']]], 422);
        }
        $membership = $user->tenants()->whereKey($tenant->id)->first();
        abort_unless($request->hasSession(), 400, 'This login endpoint requires a stateful SPA request.');
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response()->json(['data' => ['authenticated' => true, 'tenant' => ['id' => $tenant->public_id, 'name' => $tenant->name, 'slug' => $tenant->slug, 'locale' => $tenant->default_locale], 'user' => ['name' => $user->name, 'email' => $user->email, 'role' => $membership->pivot->role, 'is_super_admin' => $user->is_super_admin, 'two_factor_confirmed' => (bool) $user->two_factor_confirmed_at]], 'meta' => (object) [], 'errors' => []]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['data' => ['message' => 'Signed out.'], 'meta' => (object) [], 'errors' => []]);
    }

    public function twoFactorStatus(Request $request): JsonResponse
    {
        return response()->json(['data' => ['enabled' => (bool) $request->user()->two_factor_confirmed_at, 'pending' => (bool) $request->user()->two_factor_secret && ! $request->user()->two_factor_confirmed_at], 'meta' => (object) [], 'errors' => []]);
    }

    public function setupTwoFactor(Request $request, Google2FA $totp): JsonResponse
    {
        $secret = $totp->generateSecretKey();
        $request->user()->forceFill(['two_factor_secret' => $secret, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();

        return response()->json(['data' => ['secret' => $secret, 'otpauth_url' => $totp->getQRCodeUrl(config('app.name'), $request->user()->email, $secret)], 'meta' => (object) [], 'errors' => []]);
    }

    public function confirmTwoFactor(TwoFactorRequest $request, Google2FA $totp): JsonResponse
    {
        $user = $request->user();
        if (! $user->two_factor_secret || ! $totp->verifyKey($user->two_factor_secret, $request->validated('code'), 1)) {
            return response()->json(['data' => null, 'meta' => (object) [], 'errors' => [['code' => 'invalid_two_factor_code', 'message' => 'The authenticator code is invalid.']]], 422);
        }
        $plainCodes = collect(range(1, 8))->map(fn () => Str::lower(Str::random(10).'-'.Str::random(10)))->all();
        $user->forceFill(['two_factor_recovery_codes' => array_map(fn ($code) => Hash::make($code), $plainCodes), 'two_factor_confirmed_at' => now()])->save();

        return response()->json(['data' => ['enabled' => true, 'recovery_codes' => $plainCodes], 'meta' => (object) [], 'errors' => []]);
    }

    public function disableTwoFactor(TwoFactorRequest $request): JsonResponse
    {
        if (! Hash::check($request->validated('password'), $request->user()->password)) {
            return response()->json(['data' => null, 'meta' => (object) [], 'errors' => [['code' => 'invalid_password', 'message' => 'The password is invalid.']]], 422);
        }
        $request->user()->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();

        return response()->json(['data' => ['enabled' => false], 'meta' => (object) [], 'errors' => []]);
    }

    private function verifyTwoFactor(User $user, string $code): bool
    {
        if ($code !== '' && app(Google2FA::class)->verifyKey($user->two_factor_secret, $code, 1)) {
            return true;
        }
        foreach ($user->two_factor_recovery_codes ?? [] as $index => $hash) {
            if (Hash::check($code, $hash)) {
                $codes = $user->two_factor_recovery_codes;
                unset($codes[$index]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

                return true;
            }
        }

        return false;
    }

    private function invalidReset(): JsonResponse
    {
        return response()->json(['data' => null, 'meta' => (object) [], 'errors' => [['code' => 'invalid_reset_token', 'message' => 'The reset link is invalid or expired.']]], 422);
    }
}
