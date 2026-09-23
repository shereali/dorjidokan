<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('garments', function (Blueprint $table) {
            $table->text('description')->nullable()->after('karigar_rate_minor');
            $table->json('loose_allowances')->nullable()->after('description');
            $table->integer('display_order')->default(0)->after('loose_allowances');
            $table->string('illustration_url', 255)->nullable()->after('display_order');
        });

        Schema::create('garment_design_options', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 32)->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('garment_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('type', 20)->default('select'); // select, checkbox, radio
            $table->integer('display_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'garment_id']);
        });

        Schema::create('garment_design_values', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 32)->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('garment_design_option_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedBigInteger('extra_price_minor')->default(0);
            $table->boolean('is_default')->default(false);
            $table->integer('display_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'garment_design_option_id'], 'gdo_val_tenant_opt_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('garment_design_values');
        Schema::dropIfExists('garment_design_options');
        Schema::table('garments', function (Blueprint $table) {
            $table->dropColumn(['description', 'loose_allowances', 'display_order', 'illustration_url']);
        });
    }
};
