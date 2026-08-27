<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name', 120);
            $table->string('slug', 80)->unique();
            $table->string('status', 24)->default('active')->index();
            $table->string('default_locale', 5)->default('bn');
            $table->char('currency', 3)->default('BDT');
            $table->json('settings')->nullable();
            $table->string('stripe_id')->nullable()->index();
            $table->string('pm_type')->nullable();
            $table->string('pm_last_four', 4)->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tenant_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 32)->default('staff');
            $table->timestamps();
            $table->unique(['tenant_id', 'user_id']);
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->ulid('public_id')->unique();
            $table->string('name', 120)->index();
            $table->string('mobile_number', 20);
            $table->text('address')->nullable();
            $table->string('created_via', 16)->default('manual');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'mobile_number']);
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->ulid('public_id')->unique();
            $table->string('name', 120)->index();
            $table->string('mobile_number', 20)->nullable();
            $table->string('employee_type', 24)->default('karigar');
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'mobile_number']);
        });

        Schema::create('garments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->ulid('public_id')->unique();
            $table->string('name', 100);
            $table->string('slug', 100);
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'slug']);
        });

        Schema::create('garment_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('garment_id')->constrained()->cascadeOnDelete();
            $table->ulid('public_id')->unique();
            $table->string('name', 100);
            $table->string('slug', 100);
            $table->string('unit', 12)->default('inch');
            $table->unsignedSmallInteger('display_order');
            $table->string('svg_asset_ref', 255)->nullable();
            $table->boolean('required')->default(true);
            $table->timestamps();
            $table->unique(['garment_id', 'slug']);
            $table->unique(['garment_id', 'display_order']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->ulid('public_id')->unique();
            $table->string('order_number', 40);
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('garment_id')->constrained()->restrictOnDelete();
            $table->foreignId('karigar_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('status', 32)->default('measuring');
            $table->string('created_via', 16)->default('manual');
            $table->timestamp('promised_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->unsignedBigInteger('paid_minor')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'order_number']);
            $table->index(['tenant_id', 'status', 'promised_at', 'id']);
            $table->index(['tenant_id', 'customer_id', 'id']);
        });

        Schema::create('order_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('garment_part_id')->constrained()->restrictOnDelete();
            $table->decimal('value', 8, 2);
            $table->string('unit', 12);
            $table->string('entered_via', 16)->default('manual');
            $table->timestamp('entered_at');
            $table->timestamps();
            $table->unique(['order_id', 'garment_part_id']);
            $table->index(['tenant_id', 'order_id']);
        });

        Schema::create('order_status_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('status', 32);
            $table->string('actor_type', 40);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('created_at');
            $table->index(['tenant_id', 'order_id', 'created_at', 'id']);
        });

        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('key', 100);
            $table->string('operation', 100);
            $table->string('request_hash', 64);
            $table->unsignedSmallInteger('response_status');
            $table->json('response_body');
            $table->timestamp('expires_at')->index();
            $table->timestamps();
            $table->unique(['tenant_id', 'key', 'operation']);
        });

        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('url', 2048);
            $table->text('signing_secret');
            $table->json('events');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('webhook_endpoint_id')->constrained()->cascadeOnDelete();
            $table->ulid('event_id');
            $table->string('event_name', 100);
            $table->json('payload');
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('next_retry_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['webhook_endpoint_id', 'event_id', 'attempt']);
        });

        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->unsignedBigInteger('price_minor');
            $table->char('currency', 3)->default('BDT');
            $table->string('billing_interval', 16);
            $table->json('feature_limits');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->default('default');
            $table->string('stripe_id')->nullable()->unique();
            $table->string('stripe_status')->nullable()->index();
            $table->string('stripe_price')->nullable();
            $table->integer('quantity')->nullable();
            $table->string('provider', 30)->default('stripe');
            $table->string('provider_customer_id')->nullable()->index();
            $table->string('provider_subscription_id')->nullable()->unique();
            $table->string('status', 30)->index();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('grace_ends_at')->nullable();
            $table->timestamps();
        });
        Schema::create('subscription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('stripe_id')->unique();
            $table->string('stripe_product');
            $table->string('stripe_price');
            $table->integer('quantity')->nullable();
            $table->timestamps();
            $table->index(['subscription_id', 'stripe_price']);
        });
    }

    public function down(): void
    {
        foreach (['subscription_items', 'subscriptions', 'plans', 'webhook_deliveries', 'webhook_endpoints', 'idempotency_keys', 'order_status_events', 'order_measurements', 'orders', 'garment_parts', 'garments', 'employees', 'customers', 'tenant_memberships', 'tenants'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
