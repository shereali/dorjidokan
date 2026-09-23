<?php

namespace Database\Seeders;

use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'heritage-tailors')->firstOrFail();
        app(TenantContext::class)->set($tenant);

        $inventoryCatalog = [
            ['name' => 'Italian Wool 150s (Midnight Blue)', 'sku' => 'FAB-IW-001', 'cost' => 180000, 'unit' => 'meter', 'stock' => 85, 'reorder' => 20],
            ['name' => 'English Tweed Wool (Charcoal Grey)', 'sku' => 'FAB-TW-002', 'cost' => 140000, 'unit' => 'meter', 'stock' => 45, 'reorder' => 15],
            ['name' => 'Egyptian Giza Cotton (Crisp White)', 'sku' => 'FAB-GC-003', 'cost' => 65000, 'unit' => 'meter', 'stock' => 180, 'reorder' => 30],
            ['name' => 'Pure Rajshahi Raw Silk (Golden Cream)', 'sku' => 'FAB-RS-004', 'cost' => 120000, 'unit' => 'yard', 'stock' => 60, 'reorder' => 15],
            ['name' => 'Linen Luxury Suiting (Olive Green)', 'sku' => 'FAB-LN-005', 'cost' => 95000, 'unit' => 'yard', 'stock' => 50, 'reorder' => 10],
            ['name' => 'Brocade Jacquard Bridal Sherwani Cloth', 'sku' => 'FAB-BJ-006', 'cost' => 220000, 'unit' => 'yard', 'stock' => 35, 'reorder' => 10],
            ['name' => 'Superfine Tropical Wool (Jet Black)', 'sku' => 'FAB-TW-007', 'cost' => 160000, 'unit' => 'meter', 'stock' => 110, 'reorder' => 25],
            ['name' => 'Mother of Pearl Premium Buttons (Pack of 100)', 'sku' => 'TRM-MOP-01', 'cost' => 45000, 'unit' => 'pack', 'stock' => 25, 'reorder' => 5],
            ['name' => 'German Basting & Canvas Lining Interfacing', 'sku' => 'TRM-LIN-02', 'cost' => 25000, 'unit' => 'meter', 'stock' => 140, 'reorder' => 20],
            ['name' => 'YKK Premium Brass Trouser Zippers (7-inch)', 'sku' => 'TRM-YKK-03', 'cost' => 1800, 'unit' => 'piece', 'stock' => 300, 'reorder' => 50],
            ['name' => 'Golden Zari Embroidery Thread Spool', 'sku' => 'TRM-ZRI-04', 'cost' => 12000, 'unit' => 'spool', 'stock' => 40, 'reorder' => 10],
        ];

        foreach ($inventoryCatalog as $inv) {
            $item = InventoryItem::firstOrCreate(
                ['tenant_id' => $tenant->id, 'sku' => $inv['sku']],
                [
                    'public_id' => (string) Str::ulid(),
                    'name' => $inv['name'],
                    'unit' => $inv['unit'],
                    'reorder_level' => $inv['reorder'],
                    'active' => true,
                ]
            );

            if ($item->wasRecentlyCreated) {
                InventoryMovement::create([
                    'tenant_id' => $tenant->id,
                    'inventory_item_id' => $item->id,
                    'type' => 'in',
                    'quantity' => $inv['stock'],
                    'unit_cost_minor' => $inv['cost'],
                    'reason' => 'Opening Stock Balance',
                    'occurred_at' => now(),
                ]);
            }
        }
    }
}
