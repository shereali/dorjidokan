<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\DeliveryReminder;
use App\Models\Employee;
use App\Models\Garment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderMeasurement;
use App\Models\OrderStatusEvent;
use App\Models\Payment;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'heritage-tailors')->firstOrFail();
        app(TenantContext::class)->set($tenant);

        $customers = Customer::where('tenant_id', $tenant->id)->get()->keyBy('mobile_number');
        $garments = Garment::with('parts')->where('tenant_id', $tenant->id)->get()->keyBy('name');
        $employees = Employee::where('tenant_id', $tenant->id)->get()->keyBy('name');

        $panjabi = $garments['Panjabi (পাঞ্জাবি)'] ?? $garments->first();
        $suit = $garments['Formal 2-Piece Suit (স্যুট - ২ পিস)'] ?? $garments->first();
        $shirt = $garments['Executive Shirt (শার্ট)'] ?? $garments->first();
        $trouser = $garments['Formal Trouser/Pant (প্যান্ট)'] ?? $garments->first();
        $sherwani = $garments['Royal Sherwani (শেরওয়ানি)'] ?? $garments->first();
        $waistcoat = $garments['Waistcoat / Koti (ওয়েস্টকোট / কটি)'] ?? $garments['Mujib Coat (মুজিব কোট)'] ?? $garments->first();

        $ordersData = [
            [
                'num' => 'HT-2026-001',
                'customer' => $customers['01711000101'],
                'garment' => $suit,
                'karigar' => $employees['Master Jahangir Alam'],
                'status' => 'in_progress',
                'urgent' => true,
                'total' => 1450000,
                'paid' => 800000,
                'promise' => now()->addDays(2),
                'notes' => 'English Tweed Double-Breasted Suit with Peak Lapel.',
                'measurements' => [
                    'Coat Length (কোট লম্বা)' => 30.5, 'Chest (বুক)' => 42.0, 'Waist (কোমর)' => 38.0,
                    'Shoulder (কাঁধ)' => 18.5, 'Sleeve Length (হাতা)' => 25.0, 'Cross Back (পিঠ)' => 17.5,
                    'Pant Length (প্যান্ট লম্বা)' => 41.0, 'Pant Waist (প্যান্ট কোমর)' => 36.0, 'Hip/Seat (হিপ)' => 42.0,
                    'Thigh (রান)' => 26.0, 'Bottom Hem (মোহরি)' => 15.5,
                ],
            ],
            [
                'num' => 'HT-2026-002',
                'customer' => $customers['01678000404'],
                'garment' => $sherwani,
                'karigar' => $employees['Master Jahangir Alam'],
                'status' => 'in_progress',
                'urgent' => true,
                'total' => 2200000,
                'paid' => 1500000,
                'promise' => now()->addDays(4),
                'notes' => 'Royal Cream Jacquard Sherwani with Hand Zari Work.',
                'measurements' => [
                    'Length (লম্বা)' => 44.0, 'Chest (বুক)' => 41.0, 'Waist (কোমর)' => 37.0,
                    'Hip (হিপ)' => 43.0, 'Shoulder (কাঁধ)' => 18.0, 'Sleeve (হাতা)' => 25.5,
                    'Collar/Band (ব্যান্ড কলার)' => 16.5,
                ],
            ],
            [
                'num' => 'HT-2026-003',
                'customer' => $customers['01819000202'],
                'garment' => $panjabi,
                'karigar' => $employees['Md. Faruk Hossain'],
                'status' => 'ready',
                'urgent' => false,
                'total' => 185000,
                'paid' => 185000,
                'promise' => now()->startOfDay()->addHours(17),
                'notes' => 'Pure Silk Kabli Style Panjabi with matching buttons.',
                'measurements' => [
                    'Length (লম্বা)' => 42.5, 'Chest (বুক)' => 40.0, 'Waist (কোমর)' => 38.0,
                    'Sleeve (হাতা)' => 25.0, 'Collar/Neck (কলার)' => 16.0, 'Cuff (কফ)' => 9.5,
                    'Shoulder/Teera (তীরা)' => 18.0, 'Bottom/Gher (ঘের)' => 28.0,
                ],
            ],
            [
                'num' => 'HT-2026-004',
                'customer' => $customers['01914000303'],
                'garment' => $shirt,
                'karigar' => $employees['Anwar Hossain'],
                'status' => 'ready',
                'urgent' => false,
                'total' => 95000,
                'paid' => 50000,
                'promise' => now()->startOfDay()->addHours(15),
                'notes' => 'Egyptian Giza Cotton Slim-fit Shirt with French Cuff.',
                'measurements' => [
                    'Length (লম্বা)' => 30.0, 'Chest (বুক)' => 39.0, 'Waist (কোমর)' => 35.0,
                    'Collar (কলার)' => 15.5, 'Sleeve (হাতা)' => 24.5, 'Shoulder (তীরা)' => 17.5,
                    'Cuff (কফ)' => 9.0,
                ],
            ],
            [
                'num' => 'HT-2026-005',
                'customer' => $customers['01720000606'],
                'garment' => $trouser,
                'karigar' => $employees['Sirajul Islam (Pant Master)'],
                'status' => 'delivered',
                'urgent' => false,
                'total' => 110000,
                'paid' => 110000,
                'promise' => now()->subDays(2),
                'notes' => 'Jet Black Tropical Wool Pleated Formal Trouser.',
                'measurements' => [
                    'Length (লম্বা)' => 40.5, 'Waist (কোমর)' => 34.0, 'Hip (হিপ)' => 40.0,
                    'Thigh (রান)' => 24.5, 'Knee (হাঁটু)' => 18.0, 'Bottom (মোহরি)' => 15.0,
                    'Fly/High (হাই)' => 11.5,
                ],
            ],
            [
                'num' => 'HT-2026-006',
                'customer' => $customers['01755001111'],
                'garment' => $waistcoat,
                'karigar' => $employees['Abul Kashem'],
                'status' => 'pending_assignment',
                'urgent' => false,
                'total' => 240000,
                'paid' => 100000,
                'promise' => now()->addDays(5),
                'notes' => 'Raw Silk Mujib Coat with bespoke horn buttons.',
                'measurements' => [
                    'Length (লম্বা)' => 29.0, 'Chest (বুক)' => 41.0, 'Waist (কোমর)' => 38.0,
                    'Shoulder (কাঁধ)' => 17.5, 'Collar/Band (কলার)' => 16.0,
                ],
            ],
            [
                'num' => 'HT-2026-007',
                'customer' => $customers['01552000505'],
                'garment' => $panjabi,
                'karigar' => null,
                'status' => 'measuring',
                'urgent' => false,
                'total' => 125000,
                'paid' => 50000,
                'promise' => now()->addDays(7),
                'notes' => 'Linen Panjabi with computerized collar embroidery.',
                'measurements' => [
                    'Length (লম্বা)' => 41.0, 'Chest (বুক)' => 38.5,
                ],
            ],
            [
                'num' => 'HT-2026-008',
                'customer' => $customers['01811000707'],
                'garment' => $suit,
                'karigar' => $employees['Master Jahangir Alam'],
                'status' => 'in_progress',
                'urgent' => true,
                'total' => 1650000,
                'paid' => 1000000,
                'promise' => now()->subDay(),
                'notes' => 'Midnight Blue Tuxedo with Satin Shawl Lapel.',
                'measurements' => [
                    'Coat Length (কোট লম্বা)' => 31.0, 'Chest (বুক)' => 43.0, 'Waist (কোমর)' => 39.0,
                    'Shoulder (কাঁধ)' => 19.0, 'Sleeve Length (হাতা)' => 25.5, 'Cross Back (পিঠ)' => 18.0,
                    'Pant Length (প্যান্ট লম্বা)' => 41.5, 'Pant Waist (প্যান্ট কোমর)' => 37.0, 'Hip/Seat (হিপ)' => 43.0,
                    'Thigh (রান)' => 26.5, 'Bottom Hem (মোহরি)' => 16.0,
                ],
            ],
        ];

        foreach ($ordersData as $oData) {
            $order = Order::firstOrCreate(
                ['tenant_id' => $tenant->id, 'order_number' => $oData['num']],
                [
                    'public_id' => (string) Str::ulid(),
                    'customer_id' => $oData['customer']->id,
                    'garment_id' => $oData['garment']->id,
                    'karigar_id' => $oData['karigar']?->id,
                    'status' => $oData['status'],
                    'created_via' => 'manual',
                    'total_minor' => $oData['total'],
                    'paid_minor' => $oData['paid'],
                    'promised_at' => $oData['promise'],
                ]
            );

            if ($order->wasRecentlyCreated) {
                OrderItem::create([
                    'tenant_id' => $tenant->id,
                    'order_id' => $order->id,
                    'garment_id' => $oData['garment']->id,
                    'quantity' => 1,
                    'making_cost_minor' => $oData['total'],
                ]);

                foreach ($oData['measurements'] as $partName => $val) {
                    $part = $oData['garment']->parts->firstWhere('name', $partName);
                    if ($part) {
                        OrderMeasurement::create([
                            'tenant_id' => $tenant->id,
                            'order_id' => $order->id,
                            'garment_part_id' => $part->id,
                            'value' => $val,
                            'unit' => $part->unit,
                            'entered_via' => 'manual',
                            'entered_at' => now(),
                        ]);
                    }
                }

                if ($oData['paid'] > 0) {
                    Payment::create([
                        'tenant_id' => $tenant->id,
                        'payable_type' => Order::class,
                        'payable_id' => $order->id,
                        'amount_minor' => $oData['paid'],
                        'currency' => 'BDT',
                        'method' => 'cash',
                        'reference' => 'ADV-'.Str::random(6),
                        'received_at' => now(),
                    ]);
                }

                OrderStatusEvent::create([
                    'tenant_id' => $tenant->id,
                    'order_id' => $order->id,
                    'from_status' => null,
                    'status' => $oData['status'],
                    'actor_type' => 'user',
                    'actor_id' => 1,
                    'note' => 'Order created with initial status '.$oData['status'],
                    'created_at' => now(),
                ]);

                DeliveryReminder::create([
                    'tenant_id' => $tenant->id,
                    'order_id' => $order->id,
                    'customer_id' => $oData['customer']->id,
                    'scheduled_at' => $oData['promise']->copy()->subDay(),
                    'channel' => 'sms',
                    'status' => 'scheduled',
                ]);
            }
        }
    }
}
