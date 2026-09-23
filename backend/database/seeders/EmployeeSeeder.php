<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'heritage-tailors')->firstOrFail();
        app(TenantContext::class)->set($tenant);

        $karigars = [
            ['name' => 'Master Jahangir Alam', 'phone' => '01712345678', 'type' => 'master_cutter'],
            ['name' => 'Md. Faruk Hossain', 'phone' => '01812345678', 'type' => 'karigar'],
            ['name' => 'Abul Kashem', 'phone' => '01912345678', 'type' => 'karigar'],
            ['name' => 'Sirajul Islam (Pant Master)', 'phone' => '01612345678', 'type' => 'karigar'],
            ['name' => 'Anwar Hossain', 'phone' => '01512345678', 'type' => 'karigar'],
            ['name' => 'Mizanur Rahman', 'phone' => '01798765432', 'type' => 'finisher'],
        ];

        foreach ($karigars as $k) {
            Employee::firstOrCreate(
                ['tenant_id' => $tenant->id, 'mobile_number' => $k['phone']],
                [
                    'public_id' => (string) Str::ulid(),
                    'name' => $k['name'],
                    'employee_type' => $k['type'],
                    'active' => true,
                ]
            );
        }
    }
}
