<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->char('public_id', 26)->nullable()->unique()->after('id'));
        DB::table('users')->orderBy('id')->each(fn ($user) => DB::table('users')->where('id', $user->id)->update(['public_id' => (string) Str::ulid()]));
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropUnique(['public_id']));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('public_id'));
    }
};
