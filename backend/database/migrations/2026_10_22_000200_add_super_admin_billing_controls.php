<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $t) {
            $t->string('status_reason', 255)->nullable();
            $t->timestamp('status_changed_at')->nullable();
            $t->unsignedBigInteger('status_changed_by')->nullable();
        });
        Schema::table('subscriptions', function (Blueprint $t) {
            $t->timestamp('current_period_end')->nullable();
        });
        Schema::table('plans', function (Blueprint $t) {
            $t->text('description')->nullable();
            $t->unsignedSmallInteger('trial_days')->default(14);
            $t->unsignedSmallInteger('sort_order')->default(0);
        });
        Schema::create('subscription_payments', function (Blueprint $t) {
            $t->id();
            $t->string('public_id', 26)->unique();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('subscription_id')->nullable()->index();
            $t->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedBigInteger('amount_minor');
            $t->string('currency', 3)->default('BDT');
            $t->string('method', 30);
            $t->string('reference', 120)->nullable();
            $t->string('status', 20)->default('paid');
            $t->unsignedTinyInteger('months')->default(1);
            $t->timestamp('period_start')->nullable();
            $t->timestamp('period_end')->nullable();
            $t->timestamp('paid_at')->index();
            $t->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('note', 255)->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
        Schema::table('plans', fn (Blueprint $t) => $t->dropColumn(['description', 'trial_days', 'sort_order']));
        Schema::table('subscriptions', fn (Blueprint $t) => $t->dropColumn('current_period_end'));
        Schema::table('tenants', fn (Blueprint $t) => $t->dropColumn(['status_reason', 'status_changed_at', 'status_changed_by']));
    }
};
