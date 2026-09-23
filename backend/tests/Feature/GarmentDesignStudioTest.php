<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GarmentDesignStudioTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_admin_can_manage_garment_studio_design_options_and_loose_mapping(): void
    {
        $tenant = Tenant::create(['name' => 'Atelier Studio Tenant', 'slug' => 'atelier-studio', 'status' => 'active', 'currency' => 'BDT']);
        $admin = User::factory()->create(['email' => 'admin@atelier.test']);
        $admin->tenants()->attach($tenant, ['role' => 'admin']);

        app(TenantContext::class)->set($tenant);
        Sanctum::actingAs($admin, ['*']);

        $headers = ['X-Tenant' => 'atelier-studio'];

        // 1. Create a garment with loose allowances
        $res = $this->postJson('/api/v1/garments', [
            'name' => 'Shirt (শার্ট)',
            'category' => 'gents',
            'group_name' => 'Shirt / Fatua / Safari',
            'base_making_minor' => 50000,
            'master_rate_minor' => 6000,
            'karigar_rate_minor' => 10000,
            'description' => 'প্রিমিয়াম ফরমাল কটন শার্ট কাটিং',
            'loose_allowances' => [
                ['part_name' => 'বুক (Chest)', 'allowance_value' => '+২.৫ ইঞ্চি লুজ'],
            ],
            'parts' => [
                ['name' => 'লম্বা (Length)', 'unit' => 'inch', 'required' => true],
                ['name' => 'বুক (Chest)', 'unit' => 'inch', 'required' => true],
                ['name' => 'হাতা (Sleeve)', 'unit' => 'inch', 'required' => true],
            ],
        ], $headers);

        $res->assertCreated();
        $garmentId = $res->json('data.garment.public_id');
        $this->assertNotEmpty($garmentId);
        $this->assertEquals('প্রিমিয়াম ফরমাল কটন শার্ট কাটিং', $res->json('data.garment.description'));
        $this->assertCount(1, $res->json('data.garment.loose_allowances'));

        // 2. Add design options to garment (Collar style and Extra Folding style with extra charge)
        $optRes = $this->postJson("/api/v1/garments/{$garmentId}/design-options", [
            'name' => 'কলার স্টাইল',
            'type' => 'select',
            'values' => [
                ['name' => 'শার্ট কলার', 'extra_price_minor' => 0, 'is_default' => true],
                ['name' => 'চাইনিজ কলার', 'extra_price_minor' => 0, 'is_default' => false],
            ],
        ], $headers);
        $optRes->assertCreated();
        $this->assertEquals('কলার স্টাইল', $optRes->json('data.design_option.name'));
        $this->assertCount(2, $optRes->json('data.design_option.values'));

        $chkRes = $this->postJson("/api/v1/garments/{$garmentId}/design-options", [
            'name' => 'অতিরিক্ত সেলাই ও স্টাইল',
            'type' => 'checkbox',
            'values' => [
                ['name' => 'হাতা+পকেটে ফোল্ডিং ডিজাইন', 'extra_price_minor' => 10000, 'is_default' => false],
            ],
        ], $headers);
        $chkRes->assertCreated();
        $this->assertEquals(10000, $chkRes->json('data.design_option.values.0.extra_price_minor'));

        // 3. Test 1-Click Clone Endpoint
        $cloneRes = $this->postJson("/api/v1/garments/{$garmentId}/clone", [], $headers);
        $cloneRes->assertCreated();
        $clonedGarment = $cloneRes->json('data.garment');
        $this->assertStringContainsString('(কপি)', $clonedGarment['name']);
        $this->assertCount(3, $clonedGarment['parts']);
        $this->assertCount(2, $clonedGarment['design_options']);

        // 4. Test 1-Click Copy Design Endpoint to another garment
        $panjabiRes = $this->postJson('/api/v1/garments', [
            'name' => 'পাঞ্জাবী',
            'category' => 'gents',
            'base_making_minor' => 45000,
            'parts' => [
                ['name' => 'লম্বা (Length)', 'unit' => 'inch', 'required' => true],
            ],
        ], $headers);
        $panjabiId = $panjabiRes->json('data.garment.public_id');

        $copyRes = $this->postJson("/api/v1/garments/{$panjabiId}/copy-design", [
            'source_garment_id' => $garmentId,
        ], $headers);
        $copyRes->assertOk();
        $this->assertCount(2, $copyRes->json('data.garment.design_options'));

        // 5. Test Reorder Endpoint
        $reorderRes = $this->postJson('/api/v1/garments/reorder', [
            'garment_ids' => [$panjabiId, $garmentId],
        ], $headers);
        $reorderRes->assertOk();
        $this->assertCount(3, $reorderRes->json('data.garments'));
    }
}
