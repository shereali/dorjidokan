<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->ulid('public_id')->unique();
            $table->foreignId('customer_id')->unique()->constrained()->cascadeOnDelete();
            $table->integer('balance')->default(0);
            $table->integer('total_earned')->default(0);
            $table->integer('total_redeemed')->default(0);
            $table->timestamps();
            $table->index(['tenant_id', 'balance']);
        });
        Schema::create('loyalty_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->ulid('public_id')->unique();
            $table->foreignId('loyalty_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 10)->default('earn'); // earn | redeem
            $table->integer('points');
            $table->string('reason', 190);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'customer_id', 'created_at']);
        });

        // Tenant loyalty settings: points earned per 100 currency units spent.
        Schema::table('tenants', function (Blueprint $table) {
            $table->json('loyalty_settings')->nullable()->after('settings');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('loyalty_settings');
        });
        Schema::dropIfExists('loyalty_points');
        Schema::dropIfExists('loyalty_accounts');
    }
};
