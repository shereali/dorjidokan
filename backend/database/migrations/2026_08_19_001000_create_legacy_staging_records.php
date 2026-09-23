<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_staging_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('source_table', 80);
            $table->string('source_id', 120);
            $table->unsignedBigInteger('source_company_id')->nullable()->index();
            $table->json('payload');
            $table->char('checksum', 64);
            $table->string('transform_status', 24)->default('staged')->index();
            $table->text('transform_note')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'source_table', 'source_id']);
            $table->index(['tenant_id', 'source_table', 'transform_status'], 'leg_stg_tenant_src_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_staging_records');
    }
};
