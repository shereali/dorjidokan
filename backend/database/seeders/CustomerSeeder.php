<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyPoint;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'heritage-tailors')->firstOrFail();
        app(TenantContext::class)->set($tenant);

        $customerProfiles = [
            ['name' => 'Barrister Anisur Rahman', 'mobile' => '01711000101', 'address' => 'House 42, Road 11, Banani, Dhaka', 'marketing' => true, 'points' => 350],
            ['name' => 'Dr. Mahbubul Alam', 'mobile' => '01819000202', 'address' => 'Flat 5B, Dhanmondi 27, Dhaka', 'marketing' => true, 'points' => 200],
            ['name' => 'Engr. Shahinur Rashid', 'mobile' => '01914000303', 'address' => 'Plot 18, Sector 4, Uttara, Dhaka', 'marketing' => true, 'points' => 120],
            ['name' => 'Tahmid Hasan (Groom)', 'mobile' => '01678000404', 'address' => 'Gulshan 2, Dhaka', 'marketing' => true, 'points' => 500],
            ['name' => 'Kazi Farhan Ahmed', 'mobile' => '01552000505', 'address' => 'Wari, Old Dhaka', 'marketing' => false, 'points' => 80],
            ['name' => 'Al-Amin Sikder', 'mobile' => '01720000606', 'address' => 'Mirpur DOHS, Dhaka', 'marketing' => true, 'points' => 150],
            ['name' => 'Syed Nazmul Huda', 'mobile' => '01811000707', 'address' => 'Bashundhara R/A, Block C, Dhaka', 'marketing' => true, 'points' => 240],
            ['name' => 'Mohammad Jashim Uddin', 'mobile' => '01922000808', 'address' => 'Chawkbazar, Old Dhaka', 'marketing' => true, 'points' => 90],
            ['name' => 'Nusrat Jahan (Bridal Suite)', 'mobile' => '01733000909', 'address' => 'Dhanmondi 9/A, Dhaka', 'marketing' => true, 'points' => 450],
            ['name' => 'Shahidul Islam Jewel', 'mobile' => '01844001010', 'address' => 'Shantinagar, Dhaka', 'marketing' => true, 'points' => 110],
            ['name' => 'Zillur Rahman', 'mobile' => '01755001111', 'address' => 'Mohakhali DOHS, Dhaka', 'marketing' => true, 'points' => 180],
            ['name' => 'Mizanur Rahman Chowdhury', 'mobile' => '01966001212', 'address' => 'Baridhara Diplomatic Zone, Dhaka', 'marketing' => true, 'points' => 600],
        ];

        foreach ($customerProfiles as $cp) {
            $customer = Customer::firstOrCreate(
                ['tenant_id' => $tenant->id, 'mobile_number' => $cp['mobile']],
                [
                    'public_id' => (string) Str::ulid(),
                    'name' => $cp['name'],
                    'address' => $cp['address'],
                    'marketing_consent' => $cp['marketing'],
                ]
            );

            $loyaltyAccount = LoyaltyAccount::firstOrCreate(
                ['tenant_id' => $tenant->id, 'customer_id' => $customer->id],
                ['balance' => $cp['points']]
            );

            if ($loyaltyAccount->wasRecentlyCreated) {
                LoyaltyPoint::create([
                    'tenant_id' => $tenant->id,
                    'customer_id' => $customer->id,
                    'loyalty_account_id' => $loyaltyAccount->id,
                    'type' => 'earned',
                    'points' => $cp['points'],
                    'reason' => 'Opening Loyalty Bonus & Past Bespoke Orders',
                ]);
            }
        }
    }
}
