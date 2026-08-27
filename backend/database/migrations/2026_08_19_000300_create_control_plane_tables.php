<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('is_super_admin')->default(false)->index();
            $t->timestamp('two_factor_confirmed_at')->nullable();
        });
        Schema::create('usage_counters', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('metric', 80);
            $t->string('period', 20);
            $t->unsignedBigInteger('value')->default(0);
            $t->timestamps();
            $t->unique(['tenant_id', 'metric', 'period']);
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action', 120);
            $t->nullableMorphs('subject');
            $t->json('context')->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->timestamp('created_at');
            $t->index(['tenant_id', 'created_at', 'id']);
        });
        Schema::create('legacy_import_mappings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('source_table', 100);
            $t->string('source_id', 120);
            $t->string('target_type', 160);
            $t->unsignedBigInteger('target_id');
            $t->string('checksum', 64);
            $t->timestamps();
            $t->unique(['tenant_id', 'source_table', 'source_id']);
            $t->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_import_mappings');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('usage_counters');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['is_super_admin', 'two_factor_confirmed_at']));
    }
};
