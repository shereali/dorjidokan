<?php

namespace Database\Seeders;

use App\Models\Supplier;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'heritage-tailors')->firstOrFail();
        app(TenantContext::class)->set($tenant);

        $suppliers = [
            ['name' => 'Raymond Fabric Importers & Co.', 'phone' => '01711223344', 'address' => 'Shop 14, Islampur Cloth Market, Old Dhaka'],
            ['name' => 'Bismillah Buttons & Accessories', 'phone' => '01811223344', 'address' => 'Gawsia Market, 2nd Floor, Dhaka'],
            ['name' => 'Nawab & Sons Pure Silk Mills', 'phone' => '01911223344', 'address' => 'Silk City, Rajshahi & Dhanmondi, Dhaka'],
            ['name' => 'Italian Wool & Suiting Warehouse', 'phone' => '01611223344', 'address' => 'Chawkbazar, Dhaka'],
        ];

        foreach ($suppliers as $s) {
            Supplier::firstOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $s['name']],
                [
                    'public_id' => (string) Str::ulid(),
                    'mobile_number' => $s['phone'],
                    'address' => $s['address'],
                ]
            );
        }
    }
}
