<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_roles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('role_key', 32);
            $t->json('permissions');
            $t->timestamps();
            $t->unique(['tenant_id', 'role_key']);
        });

        DB::table('tenant_memberships')->where('role', 'admin')->update(['role' => 'owner']);
        DB::table('tenant_memberships')->where('role', 'staff')->update(['role' => 'receptionist']);
    }

    public function down(): void
    {
        DB::table('tenant_memberships')->where('role', 'owner')->update(['role' => 'admin']);
        DB::table('tenant_memberships')->whereNotIn('role', ['admin', 'manager'])->update(['role' => 'staff']);
        Schema::dropIfExists('tenant_roles');
    }
};
