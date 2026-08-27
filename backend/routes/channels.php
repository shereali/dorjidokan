<?php

use App\Models\Order;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
Broadcast::channel('tenants.{tenantPublicId}.orders.{orderPublicId}', function ($user, string $tenantPublicId, string $orderPublicId) {
    $tenant = app(TenantContext::class)->get();

    return hash_equals($tenant->public_id, $tenantPublicId) && $user->tenants()->whereKey($tenant->id)->exists() && Order::where('public_id', $orderPublicId)->exists();
});
