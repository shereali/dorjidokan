<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->unsignedBigInteger('damage_charge_minor')->default(0)->after('deposit_minor');
            $table->unsignedBigInteger('deposit_refunded_minor')->default(0)->after('damage_charge_minor');
            $table->unsignedBigInteger('settlement_due_minor')->default(0)->after('deposit_refunded_minor');
            $table->string('settlement_note', 500)->nullable()->after('settlement_due_minor');
        });
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->dropColumn(['damage_charge_minor', 'deposit_refunded_minor', 'settlement_due_minor', 'settlement_note']);
        });
    }
};
