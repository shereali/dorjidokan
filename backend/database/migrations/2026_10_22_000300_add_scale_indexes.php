<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            // Reports, dashboard revenue and "created today/this month" counters filter on created_at per workshop.
            $t->index(['tenant_id', 'created_at'], 'orders_tenant_created_idx');
        });
        Schema::table('audit_logs', function (Blueprint $t) {
            // Super admin audit filter by event prefix.
            $t->index(['action', 'id'], 'audit_logs_action_id_idx');
        });
        Schema::table('tenants', function (Blueprint $t) {
            $t->index('created_at', 'tenants_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', fn (Blueprint $t) => $t->dropIndex('tenants_created_idx'));
        Schema::table('audit_logs', fn (Blueprint $t) => $t->dropIndex('audit_logs_action_id_idx'));
        Schema::table('orders', fn (Blueprint $t) => $t->dropIndex('orders_tenant_created_idx'));
    }
};
