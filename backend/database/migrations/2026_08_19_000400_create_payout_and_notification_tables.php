<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', fn (Blueprint $table) => $table->boolean('marketing_consent')->default(false)->index());
        Schema::create('payout_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->ulid('public_id')->unique();
            $table->string('batch_number', 40);
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->date('period_from');
            $table->date('period_to');
            $table->unsignedBigInteger('total_minor');
            $table->string('payment_method', 24);
            $table->string('reference', 120)->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at');
            $table->timestamps();
            $table->unique(['tenant_id', 'batch_number']);
            $table->index(['tenant_id', 'employee_id', 'paid_at']);
        });
        Schema::create('payout_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payout_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_entry_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->timestamps();
        });
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->ulid('public_id')->unique();
            $table->string('event', 80);
            $table->string('channel', 20);
            $table->string('name', 120);
            $table->text('body');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'event', 'channel']);
        });
        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->ulid('public_id')->unique();
            $table->foreignId('notification_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 80);
            $table->string('channel', 20);
            $table->string('recipient', 190);
            $table->text('body');
            $table->string('status', 20)->default('queued');
            $table->string('provider_reference', 190)->nullable();
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status', 'created_at']);
        });
        Schema::create('notification_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->ulid('public_id')->unique();
            $table->string('name', 120);
            $table->string('channel', 20);
            $table->text('body');
            $table->string('status', 20)->default('queued');
            $table->unsignedInteger('recipient_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('queued_at');
            $table->timestamps();
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_campaigns');
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notification_templates');
        Schema::dropIfExists('payout_items');
        Schema::dropIfExists('payout_batches');
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn('marketing_consent'));
    }
};
