<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('garments', function (Blueprint $table) {
            $table->string('category', 24)->default('gents')->after('slug');
            $table->string('group_name', 100)->nullable()->after('category');
            $table->unsignedBigInteger('base_making_minor')->default(0)->after('group_name');
            $table->unsignedBigInteger('master_rate_minor')->default(0)->after('base_making_minor');
            $table->unsignedBigInteger('karigar_rate_minor')->default(0)->after('master_rate_minor');
        });
    }

    public function down(): void
    {
        Schema::table('garments', function (Blueprint $table) {
            $table->dropColumn(['category', 'group_name', 'base_making_minor', 'master_rate_minor', 'karigar_rate_minor']);
        });
    }
};
