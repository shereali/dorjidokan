<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with separate model-based seeders.
     */
    public function run(): void
    {
        $this->call([
            PlanSeeder::class,
            TenantSeeder::class,
            UserSeeder::class,
            CustomerSeeder::class,
            GarmentSeeder::class,
            EmployeeSeeder::class,
            SupplierSeeder::class,
            InventorySeeder::class,
            OrderSeeder::class,
            OperationsSeeder::class,
            AccountingSeeder::class,
            NotificationSeeder::class,
        ]);
    }
}
