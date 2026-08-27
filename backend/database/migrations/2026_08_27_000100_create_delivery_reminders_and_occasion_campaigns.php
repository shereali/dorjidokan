<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Delivery reminders: one per order that carries a promised delivery date.
        Schema::create('delivery_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->ulid('public_id')->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->timestamp('scheduled_at');           // when the reminder fires
            $table->string('channel', 20)->default('sms');
            $table->string('status', 20)->default('scheduled'); // scheduled | sent | skipped | failed
            $table->string('provider_reference', 190)->nullable();
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status', 'scheduled_at']);
            $table->unique(['tenant_id', 'order_id']);
        });

        // Extend occasion campaigns with scheduling and segmentation metadata.
        Schema::table('notification_campaigns', function (Blueprint $table) {
            $table->string('occasion', 80)->nullable()->after('name');
            $table->timestamp('scheduled_at')->nullable()->after('queued_at');
        });
    }

    public function down(): void
    {
        Schema::table('notification_campaigns', function (Blueprint $table) {
            $table->dropColumn(['occasion', 'scheduled_at']);
        });
        Schema::dropIfExists('delivery_reminders');
    }
};
