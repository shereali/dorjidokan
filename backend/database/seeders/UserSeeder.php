<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'heritage-tailors')->firstOrFail();

        $admin = User::firstOrCreate(
            ['email' => 'admin@tailors.test'],
            [
                'name' => 'Al-Haj Master Rafiq',
                'password' => Hash::make('ChangeMe123!'),
                'is_super_admin' => true,
                'email_verified_at' => now(),
            ]
        );
        $admin->tenants()->syncWithoutDetaching([$tenant->id => ['role' => 'owner']]);

        $manager = User::firstOrCreate(
            ['email' => 'manager@tailors.test'],
            [
                'name' => 'Tariqul Islam (Master Cutter)',
                'password' => Hash::make('ChangeMe123!'),
                'is_super_admin' => false,
                'email_verified_at' => now(),
            ]
        );
        $manager->tenants()->syncWithoutDetaching([$tenant->id => ['role' => 'manager']]);

        $staff = User::firstOrCreate(
            ['email' => 'staff@tailors.test'],
            [
                'name' => 'Sumon Receptionist',
                'password' => Hash::make('ChangeMe123!'),
                'is_super_admin' => false,
                'email_verified_at' => now(),
            ]
        );
        $staff->tenants()->syncWithoutDetaching([$tenant->id => ['role' => 'receptionist']]);
        foreach (['master' => 'Kamal Uddin (Master Cutter)', 'karigar' => 'Jashim Karigar', 'accountant' => 'Nasrin Accounts'] as $role => $name) {
            $demo = User::firstOrCreate(['email' => "{$role}@tailors.test"], ['name' => $name, 'password' => Hash::make('ChangeMe123!'), 'is_super_admin' => false, 'email_verified_at' => now()]);
            $demo->tenants()->syncWithoutDetaching([$tenant->id => ['role' => $role]]);
        }
    }
}
