<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->ulid('public_id')->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('garment_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->unsignedBigInteger('making_cost_minor')->default(0);
            $table->unsignedBigInteger('design_cost_minor')->default(0);
            $table->string('group_name', 120)->nullable();
            $table->string('legacy_source_id', 120)->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'legacy_source_id']);
            $table->index(['tenant_id', 'order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
