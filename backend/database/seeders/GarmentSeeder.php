<?php

namespace Database\Seeders;

use App\Models\Garment;
use App\Models\GarmentDesignOption;
use App\Models\GarmentDesignValue;
use App\Models\GarmentPart;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class GarmentSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'heritage-tailors')->firstOrFail();
        app(TenantContext::class)->set($tenant);

        $garmentCatalog = [
            // ================= GENTS (পুরুষদের পোশাক) =================
            [
                'name' => 'Panjabi (পাঞ্জাবি)',
                'slug' => 'panjabi',
                'category' => 'gents',
                'group_name' => 'Panjabi / Jubbah',
                'base_making_minor' => 45000,
                'master_rate_minor' => 7000,
                'karigar_rate_minor' => 25000,
                'parts' => [
                    ['name' => 'Length (লম্বা)', 'slug' => 'length', 'unit' => 'inch', 'display_order' => 1, 'svg' => '/svg/garments/panjabi-body.svg'],
                    ['name' => 'Chest (বুক)', 'slug' => 'chest', 'unit' => 'inch', 'display_order' => 2, 'svg' => '/svg/garments/panjabi-chest.svg'],
                    ['name' => 'Waist (কোমর)', 'slug' => 'waist', 'unit' => 'inch', 'display_order' => 3, 'svg' => '/svg/garments/panjabi-waist.svg'],
                    ['name' => 'Sleeve (হাতা)', 'slug' => 'sleeve', 'unit' => 'inch', 'display_order' => 4, 'svg' => '/svg/garments/panjabi-sleeve.svg'],
                    ['name' => 'Collar/Neck (কলার)', 'slug' => 'collar', 'unit' => 'inch', 'display_order' => 5, 'svg' => '/svg/garments/panjabi-collar.svg'],
                    ['name' => 'Cuff (কফ)', 'slug' => 'cuff', 'unit' => 'inch', 'display_order' => 6, 'svg' => '/svg/garments/panjabi-cuff.svg'],
                    ['name' => 'Shoulder/Teera (তীরা)', 'slug' => 'shoulder', 'unit' => 'inch', 'display_order' => 7, 'svg' => '/svg/garments/panjabi-shoulder.svg'],
                    ['name' => 'Bottom/Gher (ঘের)', 'slug' => 'bottom', 'unit' => 'inch', 'display_order' => 8, 'svg' => '/svg/garments/panjabi-bottom.svg'],
                ],
            ],
            [
                'name' => 'Kabli Suit (কাবলি স্যুট / পাঞ্জাবি)',
                'slug' => 'kabli',
                'category' => 'gents',
                'group_name' => 'Panjabi / Jubbah',
                'base_making_minor' => 85000,
                'master_rate_minor' => 12000,
                'karigar_rate_minor' => 45000,
                'parts' => [
                    ['name' => 'Length (লম্বা)', 'slug' => 'length', 'unit' => 'inch', 'display_order' => 1, 'svg' => '/svg/garments/panjabi-body.svg'],
                    ['name' => 'Chest (বুক)', 'slug' => 'chest', 'unit' => 'inch', 'display_order' => 2, 'svg' => '/svg/garments/panjabi-chest.svg'],
                    ['name' => 'Waist (কোমর)', 'slug' => 'waist', 'unit' => 'inch', 'display_order' => 3, 'svg' => '/svg/garments/panjabi-waist.svg'],
                    ['name' => 'Sleeve (হাতা)', 'slug' => 'sleeve', 'unit' => 'inch', 'display_order' => 4, 'svg' => '/svg/garments/panjabi-sleeve.svg'],
                    ['name' => 'Collar/Band (কলার)', 'slug' => 'collar', 'unit' => 'inch', 'display_order' => 5, 'svg' => '/svg/garments/panjabi-collar.svg'],
                    ['name' => 'Shoulder/Teera (তীরা)', 'slug' => 'shoulder', 'unit' => 'inch', 'display_order' => 6, 'svg' => '/svg/garments/panjabi-shoulder.svg'],
                    ['name' => 'Salwar Length (পায়জামা লম্বা)', 'slug' => 'salwar-length', 'unit' => 'inch', 'display_order' => 7, 'svg' => '/svg/garments/pant-length.svg'],
                    ['name' => 'Salwar Mohori (মোহরি)', 'slug' => 'salwar-mohori', 'unit' => 'inch', 'display_order' => 8, 'svg' => '/svg/garments/pant-bottom.svg'],
                ],
            ],
            [
                'name' => 'Jubbah / Arabic Thobe (জুব্বা / থোব)',
                'slug' => 'jubbah',
                'category' => 'gents',
                'group_name' => 'Panjabi / Jubbah',
                'base_making_minor' => 55000,
                'master_rate_minor' => 9000,
                'karigar_rate_minor' => 30000,
                'parts' => [
                    ['name' => 'Length (লম্বা)', 'slug' => 'length', 'unit' => 'inch', 'display_order' => 1, 'svg' => '/svg/garments/panjabi-body.svg'],
                    ['name' => 'Chest (বুক)', 'slug' => 'chest', 'unit' => 'inch', 'display_order' => 2, 'svg' => '/svg/garments/panjabi-chest.svg'],
                    ['name' => 'Waist (কোমর)', 'slug' => 'waist', 'unit' => 'inch', 'display_order' => 3, 'svg' => '/svg/garments/panjabi-waist.svg'],
                    ['name' => 'Shoulder (তীরা)', 'slug' => 'shoulder', 'unit' => 'inch', 'display_order' => 4, 'svg' => '/svg/garments/panjabi-shoulder.svg'],
                    ['name' => 'Sleeve (হাতা)', 'slug' => 'sleeve', 'unit' => 'inch', 'display_order' => 5, 'svg' => '/svg/garments/panjabi-sleeve.svg'],
                    ['name' => 'Collar/Neck (কলার)', 'slug' => 'collar', 'unit' => 'inch', 'display_order' => 6, 'svg' => '/svg/garments/panjabi-collar.svg'],
                    ['name' => 'Bottom Flare/Gher (ঘের)', 'slug' => 'gher', 'unit' => 'inch', 'display_order' => 7, 'svg' => '/svg/garments/panjabi-bottom.svg'],
                ],
            ],
            [
                'name' => 'Formal 2-Piece Suit (স্যুট - ২ পিস)',
                'slug' => 'suit-2pc',
                'category' => 'gents',
                'group_name' => 'Coat / Suit / Sherwani',
                'base_making_minor' => 350000,
                'master_rate_minor' => 60000,
                'karigar_rate_minor' => 180000,
                'parts' => [
                    ['name' => 'Coat Length (কোট লম্বা)', 'slug' => 'coat-length', 'unit' => 'inch', 'display_order' => 1, 'svg' => '/svg/garments/suit-length.svg'],
                    ['name' => 'Chest (বুক)', 'slug' => 'chest', 'unit' => 'inch', 'display_order' => 2, 'svg' => '/svg/garments/suit-chest.svg'],
                    ['name' => 'Waist (কোমর)', 'slug' => 'waist', 'unit' => 'inch', 'display_order' => 3, 'svg' => '/svg/garments/suit-waist.svg'],
                    ['name' => 'Shoulder (কাঁধ)', 'slug' => 'shoulder', 'unit' => 'inch', 'display_order' => 4, 'svg' => '/svg/garments/suit-shoulder.svg'],
                    ['name' => 'Sleeve Length (হাতা)', 'slug' => 'sleeve-length', 'unit' => 'inch', 'display_order' => 5, 'svg' => '/svg/garments/suit-sleeve.svg'],
                    ['name' => 'Cross Back (পিঠ)', 'slug' => 'cross-back', 'unit' => 'inch', 'display_order' => 6, 'svg' => '/svg/garments/suit-back.svg'],
                    ['name' => 'Pant Length (প্যান্ট লম্বা)', 'slug' => 'pant-length', 'unit' => 'inch', 'display_order' => 7, 'svg' => '/svg/garments/pant-length.svg'],
                    ['name' => 'Pant Waist (প্যান্ট কোমর)', 'slug' => 'pant-waist', 'unit' => 'inch', 'display_order' => 8, 'svg' => '/svg/garments/pant-waist.svg'],
                    ['name' => 'Hip/Seat (হিপ)', 'slug' => 'hip', 'unit' => 'inch', 'display_order' => 9, 'svg' => '/svg/garments/pant-hip.svg'],
                    ['name' => 'Thigh (রান)', 'slug' => 'thigh', 'unit' => 'inch', 'display_order' => 10, 'svg' => '/svg/garments/pant-thigh.svg'],
                    ['name' => 'Bottom Hem (মোহরি)', 'slug' => 'bottom-hem', 'unit' => 'inch', 'display_order' => 11, 'svg' => '/svg/garments/pant-bottom.svg'],
                ],
            ],
            [
                'name' => 'Formal 3-Piece Suit (স্যুট - ৩ পিস)',
                'slug' => 'suit-3pc',
                'category' => 'gents',
                'group_name' => 'Coat / Suit / Sherwani',
                'base_making_minor' => 450000,
                'master_rate_minor' => 80000,
                'karigar_rate_minor' => 240000,
                'parts' => [
                    ['name' => 'Coat Length (কোট লম্বা)', 'slug' => 'coat-length', 'unit' => 'inch', 'display_order' => 1, 'svg' => '/svg/garments/suit-length.svg'],
                    ['name' => 'Chest (বুক)', 'slug' => 'chest', 'unit' => 'inch', 'display_order' => 2, 'svg' => '/svg/garments/suit-chest.svg'],
                    ['name' => 'Waist (কোমর)', 'slug' => 'waist', 'unit' => 'inch', 'display_order' => 3, 'svg' => '/svg/garments/suit-waist.svg'],
                    ['name' => 'Vest Length (ভেস্ট লম্বা)', 'slug' => 'vest-length', 'unit' => 'inch', 'display_order' => 4, 'svg' => '/svg/garments/waistcoat-length.svg'],
                    ['name' => 'Shoulder (কাঁধ)', 'slug' => 'shoulder', 'unit' => 'inch', 'display_order' => 5, 'svg' => '/svg/garments/suit-shoulder.svg'],
                    ['name' => 'Sleeve Length (হাতা)', 'slug' => 'sleeve-length', 'unit' => 'inch', 'display_order' => 6, 'svg' => '/svg/garments/suit-sleeve.svg'],
                    ['name' => 'Pant Length (প্যান্ট লম্বা)', 'slug' => 'pant-length', 'unit' => 'inch', 'display_order' => 7, 'svg' => '/svg/garments/pant-length.svg'],
                    ['name' => 'Pant Waist (প্যান্ট কোমর)', 'slug' => 'pant-waist', 'unit' => 'inch', 'display_order' => 8, 'svg' => '/svg/garments/pant-waist.svg'],
                    ['name' => 'Hip/Seat (হিপ)', 'slug' => 'hip', 'unit' => 'inch', 'display_order' => 9, 'svg' => '/svg/garments/pant-hip.svg'],
                    ['name' => 'Thigh (রান)', 'slug' => 'thigh', 'unit' => 'inch', 'display_order' => 10, 'svg' => '/svg/garments/pant-thigh.svg'],
                    ['name' => 'Bottom Hem (মোহরি)', 'slug' => 'bottom-hem', 'unit' => 'inch', 'display_order' => 11, 'svg' => '/svg/garments/pant-bottom.svg'],
                ],
            ],
            [
                'name' => 'Blazer / Single Coat (ব্লেজার / কোট)',
                'slug' => 'blazer',
                'category' => 'gents',
                'group_name' => 'Coat / Suit / Sherwani',
                'base_making_minor' => 250000,
                'master_rate_minor' => 45000,
                'karigar_rate_minor' => 130000,
                'parts' => [
                    ['name' => 'Length (লম্বা)', 'slug' => 'length', 'unit' => 'inch', 'display_order' => 1, 'svg' => '/svg/garments/suit-length.svg'],
                    ['name' => 'Chest (বুক)', 'slug' => 'chest', 'unit' => 'inch', 'display_order' => 2, 'svg' => '/svg/garments/suit-chest.svg'],
                    ['name' => 'Waist (কোমর)', 'slug' => 'waist', 'unit' => 'inch', 'display_order' => 3, 'svg' => '/svg/garments/suit-waist.svg'],
                    ['name' => 'Hip (হিপ)', 'slug' => 'hip', 'unit' => 'inch', 'display_order' => 4, 'svg' => '/svg/garments/suit-waist.svg'],
                    ['name' => 'Shoulder (কাঁধ)', 'slug' => 'shoulder', 'unit' => 'inch', 'display_order' => 5, 'svg' => '/svg/garments/suit-shoulder.svg'],
                    ['name' => 'Sleeve (হাতা)', 'slug' => 'sleeve', 'unit' => 'inch', 'display_order' => 6, 'svg' => '/svg/garments/suit-sleeve.svg'],
                    ['name' => 'Cross Back (পিঠ)', 'slug' => 'cross-back', 'unit' => 'inch', 'display_order' => 7, 'svg' => '/svg/garments/suit-back.svg'],
                ],
            ],
            [
                'name' => 'Mujib Coat (মুজিব কোট)',
                'slug' => 'mujib-coat',
                'category' => 'gents',
                'group_name' => 'Coat / Suit / Sherwani',
                'base_making_minor' => 180000,
                'master_rate_minor' => 30000,
                'karigar_rate_minor' => 90000,
                'parts' => [
                    ['name' => 'Length (লম্বা)', 'slug' => 'length', 'unit' => 'inch', 'display_order' => 1, 'svg' => '/svg/garments/waistcoat-length.svg'],
                    ['name' => 'Chest (বুক)', 'slug' => 'chest', 'unit' => 'inch', 'display_order' => 2, 'svg' => '/svg/garments/waistcoat-chest.svg'],
                    ['name' => 'Waist (কোমর)', 'slug' => 'waist', 'unit' => 'inch', 'display_order' => 3, 'svg' => '/svg/garments/waistcoat-waist.svg'],
                    ['name' => 'Shoulder (কাঁধ)', 'slug' => 'shoulder', 'unit' => 'inch', 'display_order' => 4, 'svg' => '/svg/garments/waistcoat-shoulder.svg'],
                    ['name' => 'Band Collar (ব্যান্ড কলার)', 'slug' => 'collar', 'unit' => 'inch', 'display_order' => 5, 'svg' => '/svg/garments/waistcoat-collar.svg'],
                ],
            ],
            [
                'name' => 'Prince Coat / Jodhpuri (প্রিন্স কোট)',
                'slug' => 'prince-coat',
                'category' => 'gents',
                'group_name' => 'Coat / Suit / Sherwani',
                'base_making_minor' => 320000,
                'master_rate_minor' => 55000,
                'karigar_rate_minor' => 160000,
                'parts' => [
                    ['name' => 'Length (লম্বা)', 'slug' => 'length', 'unit' => 'inch', 'display_order' => 1, 'svg' => '/svg/garments/suit-length.svg'],
                    ['name' => 'Chest (বুক)', 'slug' => 'chest', 'unit' => 'inch', 'display_order' => 2, 'svg' => '/svg/garments/suit-chest.svg'],
                    ['name' => 'Waist (কোমর)', 'slug' => 'waist', 'unit' => 'inch', 'display_order' => 3, 'svg' => '/svg/garments/suit-waist.svg'],
                    ['name' => 'Hip (হিপ)', 'slug' => 'hip', 'unit' => 'inch', 'display_order' => 4, 'svg' => '/svg/garments/suit-waist.svg'],
                    ['name' => 'Shoulder (কাঁধ)', 'slug' => 'shoulder', 'unit' => 'inch', 'display_order' => 5, 'svg' => '/svg/garments/suit-shoulder.svg'],
                    ['name' => 'Sleeve (হাতা)', 'slug' => 'sleeve', 'unit' => 'inch', 'display_order' => 6, 'svg' => '/svg/garments/suit-sleeve.svg'],
                    ['name' => 'Band Collar (ব্যান্ড কলার)', 'slug' => 'collar', 'unit' => 'inch', 'display_order' => 7, 'svg' => '/svg/garments/sherwani-collar.svg'],
                ],
            ],
            [
                'name' => 'Royal Sherwani (শেরওয়ানি)',
                'slug' => 'sherwani',
                'category' => 'gents',
                'group_name' => 'Coat / Suit / Sherwani',
                'base_making_minor' => 450000,
                'master_rate_minor' => 80000,
                'karigar_rate_minor' => 250000,
                'parts' => [
                    ['name' => 'Length (লম্বা)', 'slug' => 'length', 'unit' => 'inch', 'display_order' => 1, 'svg' => '/svg/garments/sherwani-length.svg'],
                    ['name' => 'Chest (বুক)', 'slug' => 'chest', 'unit' => 'inch', 'display_order' => 2, 'svg' => '/svg/garments/sherwani-chest.svg'],
                    ['name' => 'Waist (কোমর)', 'slug' => 'waist', 'unit' => 'inch', 'display_order' => 3, 'svg' => '/svg/garments/sherwani-waist.svg'],
                    ['name' => 'Hip (হিপ)', 'slug' => 'hip', 'unit' => 'inch', 'display_order' => 4, 'svg' => '/svg/garments/sherwani-hip.svg'],
                    ['name' => 'Shoulder (কাঁধ)', 'slug' => 'shoulder', 'unit' => 'inch', 'display_order' => 5, 'svg' => '/svg/garments/sherwani-shoulder.svg'],
                    ['name' => 'Sleeve (হাতা)', 'slug' => 'sleeve', 'unit' => 'inch', 'display_order' => 6, 'svg' => '/svg/garments/sherwani-sleeve.svg'],
                    ['name' => 'Collar/Band (ব্যান্ড কলার)', 'slug' => 'collar', 'unit' => 'inch', 'display_order' => 7, 'svg' => '/svg/garments/sherwani-collar.svg'],
                ],
            ],
            [
                'name' => 'Waistcoat / Koti (ওয়েস্টকোট / কটি)',
                'slug' => 'waistcoat',
                'category' => 'gents',
                'group_name' => 'Coat / Suit / Sherwani',
                'base_making_minor' => 120000,
                'master_rate_minor' => 20000,
                'karigar_rate_minor' => 60000,
                'parts' => [
                    ['name' => 'Length (লম্বা)', 'slug' => 'length', 'unit' => 'inch', 'display_order' => 1, 'svg' => '/svg/garments/waistcoat-length.svg'],
                    ['name' => 'Chest (বুক)', 'slug' => 'chest', 'unit' => 'inch', 'display_order' => 2, 'svg' => '/svg/garments/waistcoat-chest.svg'],
                    ['name' => 'Waist (কোমর)', 'slug' => 'waist', 'unit' => 'inch', 'display_order' => 3, 'svg' => '/svg/garments/waistcoat-waist.svg'],
                    ['name' => 'Shoulder (কাঁধ)', 'slug' => 'shoulder', 'unit' => 'inch', 'display_order' => 4, 'svg' => '/svg/garments/waistcoat-shoulder.svg'],
                    ['name' => 'V-Neck / Collar (গলা)', 'slug' => 'collar', 'unit' => 'inch', 'display_order' => 5, 'svg' => '/svg/garments/waistcoat-collar.svg'],
                ],
            ],
            [
                'name' => 'Executive Shirt (শার্ট)',
                'slug' => 'shirt',
                'category' => 'gents',
                'group_name' => 'Shirt / Fatua / Safari',
                'base_making_minor' => 35000,
                'master_rate_minor' => 5000,
                'karigar_rate_minor' => 18000,
                'parts' => [
                    ['name' => 'Length (লম্বা)', 'slug' => 'length', 'unit' => 'inch', 'display_order' => 1, 'svg' => '/svg/garments/shirt-length.svg'],
                    ['name' => 'Chest (বুক)', 'slug' => 'chest', 'unit' => 'inch', 'display_order' => 2, 'svg' => '/svg/garments/shirt-chest.svg'],
                    ['name' => 'Waist (কোমর)', 'slug' => 'waist', 'unit' => 'inch', 'display_order' => 3, 'svg' => '/svg/garments/shirt-waist.svg'],
                    ['name' => 'Collar (কলার)', 'slug' => 'collar', 'unit' => 'inch', 'display_order' => 4, 'svg' => '/svg/garments/shirt-collar.svg'],
                    ['name' => 'Sleeve (হাতা)', 'slug' => 'sleeve', 'unit' => 'inch', 'display_order' => 5, 'svg' => '/svg/garments/shirt-sleeve.svg'],
                    ['name' => 'Shoulder (তীরা)', 'slug' => 'shoulder', 'unit' => 'inch', 'display_order' => 6, 'svg' => '/svg/garments/shirt-shoulder.svg'],
                    ['name' => 'Cuff (কফ)', 'slug' => 'cuff', 'unit' => 'inch', 'display_order' => 7, 'svg' => '/svg/garments/shirt-cuff.svg'],
                ],
            ],
            [
                'name' => 'Casual / Half Sleeve Shirt (হাফ হাতা শার্ট)',
                'slug' => 'half-shirt',
                'category' => 'gents',
                'group_name' => 'Shirt / Fatua / Safari',
                'base_making_minor' => 30000,
                'master_rate_minor' => 4500,
                'karigar_rate_minor' => 15000,
                'parts' => [
                    ['name' => 'Length (লম্বা)', 'slug' => 'length', 'unit' => 'inch', 'display_order' => 1, 'svg' => '/svg/garments/shirt-length.svg'],
                    ['name' => 'Chest (বুক)', 'slug' => 'chest', 'unit' => 'inch', 'display_order' => 2, 'svg' => '/svg/garments/shirt-chest.svg'],
                    ['name' => 'Waist (কোমর)', 'slug' => 'waist', 'unit' => 'inch', 'display_order' => 3, 'svg' => '/svg/garments/shirt-waist.svg'],
                    ['name' => 'Collar (কলার)', 'slug' => 'collar', 'unit' => 'inch', 'display_order' => 4, 'svg' => '/svg/garments/shirt-collar.svg'],
                    ['name' => 'Half Sleeve Length (হাফ হাতা)', 'slug' => 'half-sleeve', 'unit' => 'inch', 'display_order' => 5, 'svg' => '/svg/garments/shirt-sleeve.svg'],
                    ['name' => 'Sleeve Mohori (মোহরি)', 'slug' => 'sleeve-mohori', 'unit' => 'inch', 'display_order' => 6, 'svg' => '/svg/garments/shirt-cuff.svg'],
                    ['name' => 'Shoulder (তীরা)', 'slug' => 'shoulder', 'unit' => 'inch', 'display_order' => 7, 'svg' => '/svg/garments/shirt-shoulder.svg'],
                ],
            ],
            [
                'name' => 'Fatua / Short Kurta (ফতুয়া)',
                'slug' => 'fatua',
                'category' => 'gents',
                'group_name' => 'Shirt / Fatua / Safari',
                'base_making_minor' => 28000,
                'master_rate_minor' => 4000,
                'karigar_rate_minor' => 14000,
                'parts' => [
                    ['name' => 'Length (লম্বা)', 'slug' => 'length', 'unit' => 'inch', 'display_order' => 1, 'svg' => '/svg/garments/shirt-length.svg'],
                    ['name' => 'Chest (বুক)', 'slug' => 'chest', 'unit' => 'inch', 'display_order' => 2, 'svg' => '/svg/garments/shirt-chest.svg'],
                    ['name' => 'Waist (কোমর)', 'slug' => 'waist', 'unit' => 'inch', 'display_order' => 3, 'svg' => '/svg/garments/shirt-waist.svg'],
                    ['name' => 'Shoulder (তীরা)', 'slug' => 'shoulder', 'unit' => 'inch', 'display_order' => 4, 'svg' => '/svg/garments/shirt-shoulder.svg'],
                    ['name' => 'Sleeve (হাতা)', 'slug' => 'sleeve', 'unit' => 'inch', 'display_order' => 5, 'svg' => '/svg/garments/shirt-sleeve.svg'],
                    ['name' => 'Collar/Neck (কলার)', 'slug' => 'collar', 'unit' => 'inch', 'display_order' => 6, 'svg' => '/svg/garments/shirt-collar.svg'],
                    ['name' => 'Gher (ঘের)', 'slug' => 'gher', 'unit' => 'inch', 'display_order' => 7, 'svg' => '/svg/garments/panjabi-bottom.svg'],
                ],
            ],
            [
                'name' => 'Safari Suit (সাফারি স্যুট)',
                'slug' => 'safari-suit',
                'category' => 'gents',
                'group_name' => 'Shirt / Fatua / Safari',
                'base_making_minor' => 95000,
                'master_rate_minor' => 15000,
                'karigar_rate_minor' => 50000,
                'parts' => [
                    ['name' => 'Safari Length (লম্বা)', 'slug' => 'safari-length', 'unit' => 'inch', 'display_order' => 1, 'svg' => '/svg/garments/shirt-length.svg'],
                    ['name' => 'Chest (বুক)', 'slug' => 'chest', 'unit' => 'inch', 'display_order' => 2, 'svg' => '/svg/garments/shirt-chest.svg'],
                    ['name' => 'Waist (কোমর)', 'slug' => 'waist', 'unit' => 'inch', 'display_order' => 3, 'svg' => '/svg/garments/shirt-waist.svg'],
                    ['name' => 'Shoulder (তীরা)', 'slug' => 'shoulder', 'unit' => 'inch', 'display_order' => 4, 'svg' => '/svg/garments/shirt-shoulder.svg'],
                    ['name' => 'Sleeve (হাতা)', 'slug' => 'sleeve', 'unit' => 'inch', 'display_order' => 5, 'svg' => '/svg/garments/shirt-sleeve.svg'],
                    ['name' => 'Collar (কলার)', 'slug' => 'collar', 'unit' => 'inch', 'display_order' => 6, 'svg' => '/svg/garments/shirt-collar.svg'],
                    ['name' => 'Pant Length (প্যান্ট লম্বা)', 'slug' => 'pant-length', 'unit' => 'inch', 'display_order' => 7, 'svg' => '/svg/garments/pant-length.svg'],
                    ['name' => 'Pant Waist (প্যান্ট কোমর)', 'slug' => 'pant-waist', 'unit' => 'inch', 'display_order' => 8, 'svg' => '/svg/garments/pant-waist.svg'],
                    ['name' => 'Thigh (রান)', 'slug' => 'thigh', 'unit' => 'inch', 'display_order' => 9, 'svg' => '/svg/garments/pant-thigh.svg'],
                    ['name' => 'Bottom Hem (মোহরি)', 'slug' => 'bottom-hem', 'unit' => 'inch', 'display_order' => 10, 'svg' => '/svg/garments/pant-bottom.svg'],
                ],
            ],
            [
                'name' => 'Formal Trouser/Pant (প্যান্ট)',
                'slug' => 'trouser',
                'category' => 'gents',
                'group_name' => 'Pant / Pajama / Kabli',
                'base_making_minor' => 40000,
                'master_rate_minor' => 6000,
                'karigar_rate_minor' => 22000,
                'parts' => [
                    ['name' => 'Length (লম্বা)', 'slug' => 'length', 'unit' => 'inch', 'display_order' => 1, 'svg' => '/svg/garments/pant-length.svg'],
                    ['name' => 'Waist (কোমর)', 'slug' => 'waist', 'unit' => 'inch', 'display_order' => 2, 'svg' => '/svg/garments/pant-waist.svg'],
                    ['name' => 'Hip (হিপ)', 'slug' => 'hip', 'unit' => 'inch', 'display_order' => 3, 'svg' => '/svg/garments/pant-hip.svg'],
                    ['name' => 'Thigh (রান)', 'slug' => 'thigh', 'unit' => 'inch', 'display_order' => 4, 'svg' => '/svg/garments/pant-thigh.svg'],
                    ['name' => 'Knee (হাঁটু)', 'slug' => 'knee', 'unit' => 'inch', 'display_order' => 5, 'svg' => '/svg/garments/pant-knee.svg'],
                    ['name' => 'Bottom (মোহরি)', 'slug' => 'bottom', 'unit' => 'inch', 'display_order' => 6, 'svg' => '/svg/garments/pant-bottom.svg'],
                    ['name' => 'Fly/High (হাই)', 'slug' => 'high', 'unit' => 'inch', 'display_order' => 7, 'svg' => '/svg/garments/pant-high.svg'],
                ],
            ],
            [
                'name' => 'Pajama (পায়জামা)',
                'slug' => 'pajama',
                'category' => 'gents',
                'group_name' => 'Pant / Pajama / Kabli',
                'base_making_minor' => 25000,
                'master_rate_minor' => 3500,
                'karigar_rate_minor' => 13000,
                'parts' => [
                    ['name' => 'Length (লম্বা)', 'slug' => 'length', 'unit' => 'inch', 'display_order' => 1, 'svg' => '/svg/garments/pant-length.svg'],
                    ['name' => 'Waist (কোমর)', 'slug' => 'waist', 'unit' => 'inch', 'display_order' => 2, 'svg' => '/svg/garments/pant-waist.svg'],
                    ['name' => 'Hip / Seat (হিপ / ছিট)', 'slug' => 'hip', 'unit' => 'inch', 'display_order' => 3, 'svg' => '/svg/garments/pant-hip.svg'],
                    ['name' => 'Thigh (রান)', 'slug' => 'thigh', 'unit' => 'inch', 'display_order' => 4, 'svg' => '/svg/garments/pant-thigh.svg'],
                    ['name' => 'High (হাই)', 'slug' => 'high', 'unit' => 'inch', 'display_order' => 5, 'svg' => '/svg/garments/pant-high.svg'],
                    ['name' => 'Mohori (মোহরি)', 'slug' => 'mohori', 'unit' => 'inch', 'display_order' => 6, 'svg' => '/svg/garments/pant-bottom.svg'],
                ],
            ],
            [
                'name' => 'Churidar / Dhuti Pajama (চুড়িদার / ধুতি পায়জামা)',
                'slug' => 'churidar-pajama',
                'category' => 'gents',
                'group_name' => 'Pant / Pajama / Kabli',
                'base_making_minor' => 35000,
                'master_rate_minor' => 5000,
                'karigar_rate_minor' => 18000,
                'parts' => [
                    ['name' => 'Length (লম্বা)', 'slug' => 'length', 'unit' => 'inch', 'display_order' => 1, 'svg' => '/svg/garments/pant-length.svg'],
                    ['name' => 'Churi Extra (চুড়ি বাড়তি)', 'slug' => 'churi-extra', 'unit' => 'inch', 'display_order' => 2, 'svg' => '/svg/garments/pant-length.svg'],
                    ['name' => 'Waist (কোমর)', 'slug' => 'waist', 'unit' => 'inch', 'display_order' => 3, 'svg' => '/svg/garments/pant-waist.svg'],
                    ['name' => 'Thigh (রান)', 'slug' => 'thigh', 'unit' => 'inch', 'display_order' => 4, 'svg' => '/svg/garments/pant-thigh.svg'],
                    ['name' => 'Calf (গোড়ালি)', 'slug' => 'calf', 'unit' => 'inch', 'display_order' => 5, 'svg' => '/svg/garments/pant-knee.svg'],
                    ['name' => 'Ankle/Mohori (মোহরি)', 'slug' => 'ankle', 'unit' => 'inch', 'display_order' => 6, 'svg' => '/svg/garments/pant-bottom.svg'],
                ],
            ],

            // ================= LADIES (মহিলাদের পোশাক) =================
            [
                'name' => 'Ladies Kamiz / Kurti (কামিজ / কুর্তি)',
                'slug' => 'kamiz',
                'category' => 'ladies',
                'group_name' => 'Ladies Group-1 (Kamiz/Salwar)',
                'base_making_minor' => 35000,
                'master_rate_minor' => 5000,
                'karigar_rate_minor' => 18000,
                'parts' => [
                    ['name' => 'Length (লম্বা)', 'slug' => 'length', 'unit' => 'inch', 'display_order' => 1, 'svg' => '/svg/garments/panjabi-body.svg'],
                    ['name' => 'Chest / Body (বুক)', 'slug' => 'chest', 'unit' => 'inch', 'display_order' => 2, 'svg' => '/svg/garments/panjabi-chest.svg'],
                    ['name' => 'Waist (কোমর)', 'slug' => 'waist', 'unit' => 'inch', 'display_order' => 3, 'svg' => '/svg/garments/panjabi-waist.svg'],
                    ['name' => 'Hip (হিপ)', 'slug' => 'hip', 'unit' => 'inch', 'display_order' => 4, 'svg' => '/svg/garments/panjabi-bottom.svg'],
                    ['name' => 'Sleeve (হাতা)', 'slug' => 'sleeve', 'unit' => 'inch', 'display_order' => 5, 'svg' => '/svg/garments/panjabi-sleeve.svg'],
                    ['name' => 'Sleeve Mohori (মোহরি)', 'slug' => 'mohori', 'unit' => 'inch', 'display_order' => 6, 'svg' => '/svg/garments/panjabi-cuff.svg'],
                    ['name' => 'Shoulder (তীরা)', 'slug' => 'shoulder', 'unit' => 'inch', 'display_order' => 7, 'svg' => '/svg/garments/panjabi-shoulder.svg'],
                    ['name' => 'Side Slit / Fara (ফাড়া)', 'slug' => 'slit', 'unit' => 'inch', 'display_order' => 8, 'svg' => '/svg/garments/panjabi-body.svg'],
                    ['name' => 'Gher (ঘের)', 'slug' => 'gher', 'unit' => 'inch', 'display_order' => 9, 'svg' => '/svg/garments/panjabi-bottom.svg'],
                ],
            ],
            [
                'name' => 'Ladies Salwar (সালোয়ার)',
                'slug' => 'salwar',
                'category' => 'ladies',
                'group_name' => 'Ladies Group-1 (Kamiz/Salwar)',
                'base_making_minor' => 22000,
                'master_rate_minor' => 3000,
                'karigar_rate_minor' => 11000,
                'parts' => [
                    ['name' => 'Length (লম্বা)', 'slug' => 'length', 'unit' => 'inch', 'display_order' => 1, 'svg' => '/svg/garments/pant-length.svg'],
                    ['name' => 'Belt (বেল্ট/কোমর)', 'slug' => 'belt', 'unit' => 'inch', 'display_order' => 2, 'svg' => '/svg/garments/pant-waist.svg'],
                    ['name' => 'High (হাই)', 'slug' => 'high', 'unit' => 'inch', 'display_order' => 3, 'svg' => '/svg/garments/pant-high.svg'],
                    ['name' => 'Thigh / Gher (রান/ঘের)', 'slug' => 'thigh', 'unit' => 'inch', 'display_order' => 4, 'svg' => '/svg/garments/pant-thigh.svg'],
                    ['name' => 'Mohori (মোহরি)', 'slug' => 'mohori', 'unit' => 'inch', 'display_order' => 5, 'svg' => '/svg/garments/pant-bottom.svg'],
                ],
            ],
            [
                'name' => 'Ladies Palazzo / Culottes (প্লাজো)',
                'slug' => 'palazzo',
                'category' => 'ladies',
                'group_name' => 'Ladies Group-1 (Kamiz/Salwar)',
                'base_making_minor' => 25000,
                'master_rate_minor' => 3500,
                'karigar_rate_minor' => 12500,
                'parts' => [
                    ['name' => 'Length (লম্বা)', 'slug' => 'length', 'unit' => 'inch', 'display_order' => 1, 'svg' => '/svg/garments/pant-length.svg'],
                    ['name' => 'Waist (কোমর)', 'slug' => 'waist', 'unit' => 'inch', 'display_order' => 2, 'svg' => '/svg/garments/pant-waist.svg'],
                    ['name' => 'Hip (হিপ)', 'slug' => 'hip', 'unit' => 'inch', 'display_order' => 3, 'svg' => '/svg/garments/pant-hip.svg'],
                    ['name' => 'High (হাই)', 'slug' => 'high', 'unit' => 'inch', 'display_order' => 4, 'svg' => '/svg/garments/pant-high.svg'],
                    ['name' => 'Bottom Wide Flare (নিচ ঘের)', 'slug' => 'bottom-flare', 'unit' => 'inch', 'display_order' => 5, 'svg' => '/svg/garments/pant-bottom.svg'],
                ],
            ],
            [
                'name' => 'Ladies Blouse (ব্লাউজ)',
                'slug' => 'blouse',
                'category' => 'ladies',
                'group_name' => 'Ladies Group-2 (Blouse)',
                'base_making_minor' => 30000,
                'master_rate_minor' => 4000,
                'karigar_rate_minor' => 15000,
                'parts' => [
                    ['name' => 'Length (লম্বা)', 'slug' => 'length', 'unit' => 'inch', 'display_order' => 1, 'svg' => '/svg/garments/shirt-length.svg'],
                    ['name' => 'Chest (বুক)', 'slug' => 'chest', 'unit' => 'inch', 'display_order' => 2, 'svg' => '/svg/garments/shirt-chest.svg'],
                    ['name' => 'Waist (কোমর)', 'slug' => 'waist', 'unit' => 'inch', 'display_order' => 3, 'svg' => '/svg/garments/shirt-waist.svg'],
                    ['name' => 'Front Neck (সামনে গলা)', 'slug' => 'front-neck', 'unit' => 'inch', 'display_order' => 4, 'svg' => '/svg/garments/shirt-collar.svg'],
                    ['name' => 'Back Neck (পিছনে গলা)', 'slug' => 'back-neck', 'unit' => 'inch', 'display_order' => 5, 'svg' => '/svg/garments/shirt-collar.svg'],
                    ['name' => 'Sleeve (হাতা)', 'slug' => 'sleeve', 'unit' => 'inch', 'display_order' => 6, 'svg' => '/svg/garments/shirt-sleeve.svg'],
                    ['name' => 'Armhole / Mohra (মোহরা)', 'slug' => 'armhole', 'unit' => 'inch', 'display_order' => 7, 'svg' => '/svg/garments/shirt-shoulder.svg'],
                ],
            ],
            [
                'name' => 'Ladies Petticoat / Saya (পেটিকোট / সায়া)',
                'slug' => 'petticoat',
                'category' => 'ladies',
                'group_name' => 'Ladies Group-2 (Blouse)',
                'base_making_minor' => 15000,
                'master_rate_minor' => 2000,
                'karigar_rate_minor' => 7000,
                'parts' => [
                    ['name' => 'Length (লম্বা)', 'slug' => 'length', 'unit' => 'inch', 'display_order' => 1, 'svg' => '/svg/garments/pant-length.svg'],
                    ['name' => 'Waist (কোমর)', 'slug' => 'waist', 'unit' => 'inch', 'display_order' => 2, 'svg' => '/svg/garments/pant-waist.svg'],
                    ['name' => 'Hip (হিপ)', 'slug' => 'hip', 'unit' => 'inch', 'display_order' => 3, 'svg' => '/svg/garments/pant-hip.svg'],
                    ['name' => 'Gher (নিচ ঘের)', 'slug' => 'gher', 'unit' => 'inch', 'display_order' => 4, 'svg' => '/svg/garments/panjabi-bottom.svg'],
                ],
            ],
            [
                'name' => 'Ladies Lehenga & Choli (লেহেঙ্গা ও চোলি)',
                'slug' => 'lehenga',
                'category' => 'ladies',
                'group_name' => 'Ladies Group-3 (Lehenga/Gown)',
                'base_making_minor' => 250000,
                'master_rate_minor' => 40000,
                'karigar_rate_minor' => 130000,
                'parts' => [
                    ['name' => 'Lehenga Length (লেহেঙ্গা লম্বা)', 'slug' => 'lehenga-length', 'unit' => 'inch', 'display_order' => 1, 'svg' => '/svg/garments/panjabi-bottom.svg'],
                    ['name' => 'Lehenga Waist (কোমর)', 'slug' => 'lehenga-waist', 'unit' => 'inch', 'display_order' => 2, 'svg' => '/svg/garments/pant-waist.svg'],
                    ['name' => 'Lehenga Gher (ঘের)', 'slug' => 'lehenga-gher', 'unit' => 'inch', 'display_order' => 3, 'svg' => '/svg/garments/panjabi-bottom.svg'],
                    ['name' => 'Choli Length (চোলি লম্বা)', 'slug' => 'choli-length', 'unit' => 'inch', 'display_order' => 4, 'svg' => '/svg/garments/shirt-length.svg'],
                    ['name' => 'Choli Chest (বুক)', 'slug' => 'choli-chest', 'unit' => 'inch', 'display_order' => 5, 'svg' => '/svg/garments/shirt-chest.svg'],
                    ['name' => 'Choli Waist (কোমর)', 'slug' => 'choli-waist', 'unit' => 'inch', 'display_order' => 6, 'svg' => '/svg/garments/shirt-waist.svg'],
                    ['name' => 'Sleeve (হাতা)', 'slug' => 'sleeve', 'unit' => 'inch', 'display_order' => 7, 'svg' => '/svg/garments/shirt-sleeve.svg'],
                ],
            ],
            [
                'name' => 'Anarkali / Long Gown (আনারকলি / গাউন)',
                'slug' => 'anarkali-gown',
                'category' => 'ladies',
                'group_name' => 'Ladies Group-3 (Lehenga/Gown)',
                'base_making_minor' => 180000,
                'master_rate_minor' => 30000,
                'karigar_rate_minor' => 90000,
                'parts' => [
                    ['name' => 'Total Length (পুরো লম্বা)', 'slug' => 'length', 'unit' => 'inch', 'display_order' => 1, 'svg' => '/svg/garments/panjabi-body.svg'],
                    ['name' => 'Body / Chest (বুক)', 'slug' => 'chest', 'unit' => 'inch', 'display_order' => 2, 'svg' => '/svg/garments/panjabi-chest.svg'],
                    ['name' => 'Waist (কোমর)', 'slug' => 'waist', 'unit' => 'inch', 'display_order' => 3, 'svg' => '/svg/garments/panjabi-waist.svg'],
                    ['name' => 'Upper Body Length (বডি লম্বা)', 'slug' => 'upper-length', 'unit' => 'inch', 'display_order' => 4, 'svg' => '/svg/garments/shirt-length.svg'],
                    ['name' => 'Shoulder (তীরা)', 'slug' => 'shoulder', 'unit' => 'inch', 'display_order' => 5, 'svg' => '/svg/garments/panjabi-shoulder.svg'],
                    ['name' => 'Sleeve (হাতা)', 'slug' => 'sleeve', 'unit' => 'inch', 'display_order' => 6, 'svg' => '/svg/garments/panjabi-sleeve.svg'],
                    ['name' => 'Gown Gher (নিচ ঘের)', 'slug' => 'gher', 'unit' => 'inch', 'display_order' => 7, 'svg' => '/svg/garments/panjabi-bottom.svg'],
                ],
            ],
            [
                'name' => 'Ladies Burqa / Abaya (বোরকা / আবায়া)',
                'slug' => 'burqa-abaya',
                'category' => 'ladies',
                'group_name' => 'Ladies Group-4 (Burqa/Abaya)',
                'base_making_minor' => 65000,
                'master_rate_minor' => 10000,
                'karigar_rate_minor' => 35000,
                'parts' => [
                    ['name' => 'Length (লম্বা)', 'slug' => 'length', 'unit' => 'inch', 'display_order' => 1, 'svg' => '/svg/garments/panjabi-body.svg'],
                    ['name' => 'Chest / Body (বুক)', 'slug' => 'chest', 'unit' => 'inch', 'display_order' => 2, 'svg' => '/svg/garments/panjabi-chest.svg'],
                    ['name' => 'Shoulder (তীরা)', 'slug' => 'shoulder', 'unit' => 'inch', 'display_order' => 3, 'svg' => '/svg/garments/panjabi-shoulder.svg'],
                    ['name' => 'Sleeve (হাতা)', 'slug' => 'sleeve', 'unit' => 'inch', 'display_order' => 4, 'svg' => '/svg/garments/panjabi-sleeve.svg'],
                    ['name' => 'Cuff/Elastic (হাতার মুখ)', 'slug' => 'cuff', 'unit' => 'inch', 'display_order' => 5, 'svg' => '/svg/garments/panjabi-cuff.svg'],
                    ['name' => 'Bottom Gher (নিচ ঘের)', 'slug' => 'gher', 'unit' => 'inch', 'display_order' => 6, 'svg' => '/svg/garments/panjabi-bottom.svg'],
                ],
            ],
            [
                'name' => 'Ladies Maxi / House Coat (ম্যাক্সি / নাইটি)',
                'slug' => 'maxi-nighty',
                'category' => 'ladies',
                'group_name' => 'Ladies Group-5 (Maxi/Nighty)',
                'base_making_minor' => 25000,
                'master_rate_minor' => 3500,
                'karigar_rate_minor' => 12000,
                'parts' => [
                    ['name' => 'Length (লম্বা)', 'slug' => 'length', 'unit' => 'inch', 'display_order' => 1, 'svg' => '/svg/garments/panjabi-body.svg'],
                    ['name' => 'Chest (বুক)', 'slug' => 'chest', 'unit' => 'inch', 'display_order' => 2, 'svg' => '/svg/garments/panjabi-chest.svg'],
                    ['name' => 'Shoulder (তীরা)', 'slug' => 'shoulder', 'unit' => 'inch', 'display_order' => 3, 'svg' => '/svg/garments/panjabi-shoulder.svg'],
                    ['name' => 'Sleeve (হাতা)', 'slug' => 'sleeve', 'unit' => 'inch', 'display_order' => 4, 'svg' => '/svg/garments/panjabi-sleeve.svg'],
                    ['name' => 'Gher (ঘের)', 'slug' => 'gher', 'unit' => 'inch', 'display_order' => 5, 'svg' => '/svg/garments/panjabi-bottom.svg'],
                ],
            ],

            // ================= KIDS & UNISEX (বাচ্চাদের ও উভলিঙ্গ পোশাক) =================
            [
                'name' => 'Baby / Kids Frock (বেবি ফ্রক)',
                'slug' => 'baby-frock',
                'category' => 'kids',
                'group_name' => 'Ladies Group-6 (Frock/Skirt)',
                'base_making_minor' => 25000,
                'master_rate_minor' => 3500,
                'karigar_rate_minor' => 12000,
                'parts' => [
                    ['name' => 'Length (লম্বা)', 'slug' => 'length', 'unit' => 'inch', 'display_order' => 1, 'svg' => '/svg/garments/panjabi-body.svg'],
                    ['name' => 'Chest (বুক)', 'slug' => 'chest', 'unit' => 'inch', 'display_order' => 2, 'svg' => '/svg/garments/panjabi-chest.svg'],
                    ['name' => 'Waist (কোমর)', 'slug' => 'waist', 'unit' => 'inch', 'display_order' => 3, 'svg' => '/svg/garments/panjabi-waist.svg'],
                    ['name' => 'Body Part (বডি পার্ট)', 'slug' => 'body-part', 'unit' => 'inch', 'display_order' => 4, 'svg' => '/svg/garments/shirt-length.svg'],
                    ['name' => 'Shoulder (কাঁধ)', 'slug' => 'shoulder', 'unit' => 'inch', 'display_order' => 5, 'svg' => '/svg/garments/panjabi-shoulder.svg'],
                    ['name' => 'Sleeve (হাতা)', 'slug' => 'sleeve', 'unit' => 'inch', 'display_order' => 6, 'svg' => '/svg/garments/panjabi-sleeve.svg'],
                    ['name' => 'Gher (ঘের)', 'slug' => 'gher', 'unit' => 'inch', 'display_order' => 7, 'svg' => '/svg/garments/panjabi-bottom.svg'],
                ],
            ],
            [
                'name' => 'Kids Panjabi & Pajama Set (বাচ্চাদের পাঞ্জাবি সেট)',
                'slug' => 'kids-panjabi-set',
                'category' => 'kids',
                'group_name' => 'Panjabi / Jubbah',
                'base_making_minor' => 35000,
                'master_rate_minor' => 5000,
                'karigar_rate_minor' => 18000,
                'parts' => [
                    ['name' => 'Panjabi Length (লম্বা)', 'slug' => 'panjabi-length', 'unit' => 'inch', 'display_order' => 1, 'svg' => '/svg/garments/panjabi-body.svg'],
                    ['name' => 'Chest (বুক)', 'slug' => 'chest', 'unit' => 'inch', 'display_order' => 2, 'svg' => '/svg/garments/panjabi-chest.svg'],
                    ['name' => 'Sleeve (হাতা)', 'slug' => 'sleeve', 'unit' => 'inch', 'display_order' => 3, 'svg' => '/svg/garments/panjabi-sleeve.svg'],
                    ['name' => 'Collar (কলার)', 'slug' => 'collar', 'unit' => 'inch', 'display_order' => 4, 'svg' => '/svg/garments/panjabi-collar.svg'],
                    ['name' => 'Pajama Length (পায়জামা লম্বা)', 'slug' => 'pajama-length', 'unit' => 'inch', 'display_order' => 5, 'svg' => '/svg/garments/pant-length.svg'],
                    ['name' => 'Pajama Mohori (মোহরি)', 'slug' => 'pajama-mohori', 'unit' => 'inch', 'display_order' => 6, 'svg' => '/svg/garments/pant-bottom.svg'],
                ],
            ],
            [
                'name' => 'School Uniform Set (স্কুল ইউনিফর্ম)',
                'slug' => 'school-uniform',
                'category' => 'unisex',
                'group_name' => 'Shirt / Fatua / Safari',
                'base_making_minor' => 40000,
                'master_rate_minor' => 6000,
                'karigar_rate_minor' => 20000,
                'parts' => [
                    ['name' => 'Shirt Length (শার্ট লম্বা)', 'slug' => 'shirt-length', 'unit' => 'inch', 'display_order' => 1, 'svg' => '/svg/garments/shirt-length.svg'],
                    ['name' => 'Chest (বুক)', 'slug' => 'chest', 'unit' => 'inch', 'display_order' => 2, 'svg' => '/svg/garments/shirt-chest.svg'],
                    ['name' => 'Collar (কলার)', 'slug' => 'collar', 'unit' => 'inch', 'display_order' => 3, 'svg' => '/svg/garments/shirt-collar.svg'],
                    ['name' => 'Sleeve (হাতা)', 'slug' => 'sleeve', 'unit' => 'inch', 'display_order' => 4, 'svg' => '/svg/garments/shirt-sleeve.svg'],
                    ['name' => 'Pant / Skirt Length (প্যান্ট/স্কার্ট লম্বা)', 'slug' => 'bottom-length', 'unit' => 'inch', 'display_order' => 5, 'svg' => '/svg/garments/pant-length.svg'],
                    ['name' => 'Waist (কোমর)', 'slug' => 'waist', 'unit' => 'inch', 'display_order' => 6, 'svg' => '/svg/garments/pant-waist.svg'],
                ],
            ],
        ];

        foreach ($garmentCatalog as $gData) {
            $garment = Garment::updateOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => $gData['slug']],
                [
                    'public_id' => (string) Str::ulid(),
                    'name' => $gData['name'],
                    'category' => $gData['category'] ?? 'gents',
                    'group_name' => $gData['group_name'] ?? null,
                    'base_making_minor' => $gData['base_making_minor'] ?? 0,
                    'master_rate_minor' => $gData['master_rate_minor'] ?? 0,
                    'karigar_rate_minor' => $gData['karigar_rate_minor'] ?? 0,
                    'active' => true,
                ]
            );

            foreach ($gData['parts'] as $pData) {
                GarmentPart::updateOrCreate(
                    ['garment_id' => $garment->id, 'slug' => $pData['slug']],
                    [
                        'tenant_id' => $tenant->id,
                        'public_id' => (string) Str::ulid(),
                        'name' => $pData['name'],
                        'unit' => $pData['unit'],
                        'display_order' => $pData['display_order'],
                        'svg_asset_ref' => $pData['svg'],
                        'required' => true,
                    ]
                );
            }

            $this->seedDesignOptionsForGarment($tenant->id, $garment);
        }
    }

    private function seedDesignOptionsForGarment(string $tenantId, Garment $garment): void
    {
        $slug = $garment->slug;
        $category = $garment->category;
        $designs = [];

        if (str_contains($slug, 'shirt') || str_contains($slug, 'safari') || str_contains($slug, 'uniform')) {
            $designs = [
                [
                    'name' => 'প্যাটার্ন / ডিজাইন',
                    'type' => 'select',
                    'values' => [
                        ['name' => 'Full Regular (ফুল হাতা রেগুলার)', 'extra_price' => 0, 'is_default' => true],
                        ['name' => 'Half Regular (হাফ হাতা)', 'extra_price' => 0, 'is_default' => false],
                        ['name' => 'Full Chinese (ফুল চাইনিজ)', 'extra_price' => 0, 'is_default' => false],
                        ['name' => 'Half Chinese (হাফ চাইনিজ)', 'extra_price' => 0, 'is_default' => false],
                        ['name' => 'Half Hawaiian, Side Slit', 'extra_price' => 0, 'is_default' => false],
                        ['name' => 'Half Hawaiian, No Side Slit', 'extra_price' => 0, 'is_default' => false],
                        ['name' => 'Full Hawaiian, Side Slit', 'extra_price' => 0, 'is_default' => false],
                    ],
                ],
                [
                    'name' => 'কলার স্টাইল',
                    'type' => 'select',
                    'values' => [
                        ['name' => 'শার্ট কলার (Spread Collar)', 'extra_price' => 0, 'is_default' => true],
                        ['name' => 'ব্যান / চাইনিজ কলার', 'extra_price' => 0, 'is_default' => false],
                        ['name' => 'বাটন ডাউন কলার', 'extra_price' => 0, 'is_default' => false],
                        ['name' => 'ওয়াইড স্প্রেড কলার', 'extra_price' => 0, 'is_default' => false],
                        ['name' => 'ম্যান্ডারিন কলার', 'extra_price' => 0, 'is_default' => false],
                    ],
                ],
                [
                    'name' => 'প্লিট (Pleat)',
                    'type' => 'select',
                    'values' => [
                        ['name' => 'Plain (প্লিট ছাড়া)', 'extra_price' => 0, 'is_default' => true],
                        ['name' => 'Box Pleat (মাঝখানে বক্স প্লিট)', 'extra_price' => 0, 'is_default' => false],
                        ['name' => 'Side Pleat (দুই পাশে প্লিট)', 'extra_price' => 0, 'is_default' => false],
                        ['name' => 'Center Inverted Pleat', 'extra_price' => 0, 'is_default' => false],
                    ],
                ],
                [
                    'name' => 'কফ ডিজাইন',
                    'type' => 'select',
                    'values' => [
                        ['name' => 'কাট কফ (Cut Cuff)', 'extra_price' => 0, 'is_default' => true],
                        ['name' => 'গোল কফ (Round Cuff)', 'extra_price' => 0, 'is_default' => false],
                        ['name' => 'সোজা কফ (Square Cuff)', 'extra_price' => 0, 'is_default' => false],
                        ['name' => 'ফ্রেঞ্চ কফ (ডাবল কফ)', 'extra_price' => 5000, 'is_default' => false],
                    ],
                ],
                [
                    'name' => 'পকেট স্টাইল',
                    'type' => 'select',
                    'values' => [
                        ['name' => '১ বুক পকেট (Plain)', 'extra_price' => 0, 'is_default' => true],
                        ['name' => '১ পকেট (V সেলাইসহ)', 'extra_price' => 0, 'is_default' => false],
                        ['name' => '২ ফ্ল্যাপ পকেট (Safari Style)', 'extra_price' => 3000, 'is_default' => false],
                        ['name' => '১.৫০ পকেট (পেন স্লটসহ)', 'extra_price' => 2000, 'is_default' => false],
                        ['name' => 'পকেট ছাড়া (No Pocket)', 'extra_price' => 0, 'is_default' => false],
                    ],
                ],
                [
                    'name' => 'সোল্ডার / তীরা',
                    'type' => 'select',
                    'values' => [
                        ['name' => 'রেগুলার সিঙ্গেল তীরা', 'extra_price' => 0, 'is_default' => true],
                        ['name' => 'স্প্লিট তীরা (Split Yoke)', 'extra_price' => 0, 'is_default' => false],
                        ['name' => 'সিমলেস তীরা (Seamless)', 'extra_price' => 0, 'is_default' => false],
                    ],
                ],
                [
                    'name' => 'অতিরিক্ত সেলাই ও স্টাইল',
                    'type' => 'checkbox',
                    'values' => [
                        ['name' => 'হাতা+পকেটে ফোল্ডিং হবে', 'extra_price' => 10000, 'is_default' => false],
                        ['name' => 'সেম্পল ডিজাইন', 'extra_price' => 10000, 'is_default' => false],
                        ['name' => 'প্লেট+কপ+কলার ১ পয়েন্ট চওড়া সেলাই', 'extra_price' => 5000, 'is_default' => false],
                        ['name' => 'পকেটে V সেলাই', 'extra_price' => 0, 'is_default' => false],
                        ['name' => 'হাতাই কুচি হবে না', 'extra_price' => 0, 'is_default' => false],
                        ['name' => 'ছবি অনুযায়ী স্পেশাল ডিজাইন', 'extra_price' => 15000, 'is_default' => false],
                    ],
                ],
            ];
        } elseif (str_contains($slug, 'panjabi') || str_contains($slug, 'kabli') || str_contains($slug, 'jubbah') || str_contains($slug, 'fatua')) {
            $designs = [
                [
                    'name' => 'কলার স্টাইল',
                    'type' => 'select',
                    'values' => [
                        ['name' => 'ব্যান কলার', 'extra_price' => 0, 'is_default' => true],
                        ['name' => 'শেরওয়ানি হাই কলার', 'extra_price' => 0, 'is_default' => false],
                        ['name' => 'ওপেন চাইনিজ কলার', 'extra_price' => 0, 'is_default' => false],
                        ['name' => 'শার্ট কলার', 'extra_price' => 0, 'is_default' => false],
                    ],
                ],
                [
                    'name' => 'প্লেট ডিজাইন',
                    'type' => 'select',
                    'values' => [
                        ['name' => 'লুকানো বোতাম প্লেট (Hidden Placket)', 'extra_price' => 0, 'is_default' => true],
                        ['name' => 'ওপেন ফ্ল্যাট প্লেট', 'extra_price' => 0, 'is_default' => false],
                        ['name' => 'জামদানি পাইপিন প্লেট', 'extra_price' => 5000, 'is_default' => false],
                    ],
                ],
                [
                    'name' => 'পকেট স্টাইল',
                    'type' => 'select',
                    'values' => [
                        ['name' => '২ সাইড পকেট', 'extra_price' => 0, 'is_default' => true],
                        ['name' => '১ সাইড পকেট', 'extra_price' => 0, 'is_default' => false],
                        ['name' => 'বুক পকেট + ২ সাইড পকেট', 'extra_price' => 3000, 'is_default' => false],
                        ['name' => 'চেইন ওয়ালেট পকেট', 'extra_price' => 2000, 'is_default' => false],
                    ],
                ],
                [
                    'name' => 'হাতা ও মোহরি',
                    'type' => 'select',
                    'values' => [
                        ['name' => 'সাধারণ খোলা হাতা', 'extra_price' => 0, 'is_default' => true],
                        ['name' => 'শার্ট কাপ হাতা', 'extra_price' => 3000, 'is_default' => false],
                        ['name' => 'কাবলি ফোল্ডিং হাতা', 'extra_price' => 5000, 'is_default' => false],
                    ],
                ],
                [
                    'name' => 'অতিরিক্ত স্টাইল ও কাজ',
                    'type' => 'checkbox',
                    'values' => [
                        ['name' => 'হাতা+পকেটে ফোল্ডিং', 'extra_price' => 10000, 'is_default' => false],
                        ['name' => 'পাইপিন ডিজাইন', 'extra_price' => 10000, 'is_default' => false],
                        ['name' => '১ পয়েন্ট ডাবল সেলাই', 'extra_price' => 5000, 'is_default' => false],
                        ['name' => 'এমব্রয়ডারি / কারচুপি কাজ', 'extra_price' => 25000, 'is_default' => false],
                    ],
                ],
            ];
        } elseif (str_contains($slug, 'pant') || str_contains($slug, 'trouser') || str_contains($slug, 'pajama') || str_contains($slug, 'salwar') || str_contains($slug, 'palazzo')) {
            $designs = [
                [
                    'name' => 'প্লিট স্টাইল',
                    'type' => 'select',
                    'values' => [
                        ['name' => 'প্লিট ছাড়া (Flat Front)', 'extra_price' => 0, 'is_default' => true],
                        ['name' => '১ প্লিট (Single Pleat)', 'extra_price' => 0, 'is_default' => false],
                        ['name' => '২ প্লিট (Double Pleat)', 'extra_price' => 0, 'is_default' => false],
                    ],
                ],
                [
                    'name' => 'পকেট স্টাইল',
                    'type' => 'select',
                    'values' => [
                        ['name' => 'ক্রস পকেট (Cross Slant)', 'extra_price' => 0, 'is_default' => true],
                        ['name' => 'স্ট্রেইট পকেট (Straight)', 'extra_price' => 0, 'is_default' => false],
                        ['name' => 'ব্যাক ডাবল পকেট', 'extra_price' => 2000, 'is_default' => false],
                        ['name' => 'কয়েন / সিক্রেট পকেট', 'extra_price' => 1000, 'is_default' => false],
                    ],
                ],
                [
                    'name' => 'বেল্ট ও লুপ',
                    'type' => 'select',
                    'values' => [
                        ['name' => 'স্ট্যান্ডার্ড বেল্ট লুপ', 'extra_price' => 0, 'is_default' => true],
                        ['name' => 'গ্রিপার ওয়েস্টব্যান্ড', 'extra_price' => 3000, 'is_default' => false],
                        ['name' => 'এক্সটেন্ডেড ট্যাব বাটন', 'extra_price' => 2000, 'is_default' => false],
                    ],
                ],
                [
                    'name' => 'বটম ফিনিশিং',
                    'type' => 'select',
                    'values' => [
                        ['name' => 'প্লেন বটম (Plain Hem)', 'extra_price' => 0, 'is_default' => true],
                        ['name' => 'টার্ন-আপ ফোল্ডিং (Cuff Hem)', 'extra_price' => 3000, 'is_default' => false],
                    ],
                ],
            ];
        } elseif (str_contains($slug, 'suit') || str_contains($slug, 'coat') || str_contains($slug, 'blazer') || str_contains($slug, 'sherwani') || str_contains($slug, 'waistcoat')) {
            $designs = [
                [
                    'name' => 'ল্যাপেল স্টাইল',
                    'type' => 'select',
                    'values' => [
                        ['name' => 'নচ ল্যাপেল (Notch Lapel)', 'extra_price' => 0, 'is_default' => true],
                        ['name' => 'পিক ল্যাপেল (Peak Lapel)', 'extra_price' => 0, 'is_default' => false],
                        ['name' => 'শাল ল্যাপেল (Shawl Lapel)', 'extra_price' => 5000, 'is_default' => false],
                    ],
                ],
                [
                    'name' => 'বাটন কনফিগারেশন',
                    'type' => 'select',
                    'values' => [
                        ['name' => '২ বাটন সিঙ্গেল ব্রেস্টেড', 'extra_price' => 0, 'is_default' => true],
                        ['name' => '১ বাটন পার্টি ব্লেজার', 'extra_price' => 0, 'is_default' => false],
                        ['name' => 'ডাবল ব্রেস্টেড', 'extra_price' => 15000, 'is_default' => false],
                    ],
                ],
                [
                    'name' => 'ভেন্ট / ব্যাক কাট',
                    'type' => 'select',
                    'values' => [
                        ['name' => 'ডাবল সাইড ভেন্ট', 'extra_price' => 0, 'is_default' => true],
                        ['name' => 'সিঙ্গেল সেন্টার ভেন্ট', 'extra_price' => 0, 'is_default' => false],
                        ['name' => 'নো ভেন্ট (ইটালিয়ান কাট)', 'extra_price' => 0, 'is_default' => false],
                    ],
                ],
                [
                    'name' => 'পকেট স্টাইল',
                    'type' => 'select',
                    'values' => [
                        ['name' => 'ফ্ল্যাপ পকেট', 'extra_price' => 0, 'is_default' => true],
                        ['name' => 'টিকিট পকেটসহ ফ্ল্যাপ', 'extra_price' => 5000, 'is_default' => false],
                        ['name' => 'প্যাচ পকেট (ক্যাজুয়াল)', 'extra_price' => 0, 'is_default' => false],
                    ],
                ],
            ];
        } elseif (str_contains($slug, 'kamiz') || str_contains($slug, 'anarkali') || str_contains($slug, 'frock') || str_contains($slug, 'maxi')) {
            $designs = [
                [
                    'name' => 'গলার ডিজাইন',
                    'type' => 'select',
                    'values' => [
                        ['name' => 'পান গলা (V Neck)', 'extra_price' => 0, 'is_default' => true],
                        ['name' => 'গোল গলা (Round Neck)', 'extra_price' => 0, 'is_default' => false],
                        ['name' => 'ব্যান গলা (High Neck)', 'extra_price' => 0, 'is_default' => false],
                        ['name' => 'বোট নেক (Boat Neck)', 'extra_price' => 0, 'is_default' => false],
                        ['name' => 'স্টার গলা', 'extra_price' => 3000, 'is_default' => false],
                        ['name' => 'আংরাখা ওভারল্যাপ', 'extra_price' => 10000, 'is_default' => false],
                    ],
                ],
                [
                    'name' => 'হাতার ডিজাইন',
                    'type' => 'select',
                    'values' => [
                        ['name' => 'সাধারণ হাতা', 'extra_price' => 0, 'is_default' => true],
                        ['name' => 'থ্রি-কোয়ার্টার হাতা', 'extra_price' => 0, 'is_default' => false],
                        ['name' => 'বেল হাতা / ঘটি হাতা', 'extra_price' => 5000, 'is_default' => false],
                        ['name' => 'কাট হাতা (Sleeveless)', 'extra_price' => 0, 'is_default' => false],
                        ['name' => 'বাটারফ্লাই হাতা', 'extra_price' => 5000, 'is_default' => false],
                    ],
                ],
                [
                    'name' => 'ঘের ও কাটিং',
                    'type' => 'select',
                    'values' => [
                        ['name' => 'স্ট্রেইট সাইড ফাড়া', 'extra_price' => 0, 'is_default' => true],
                        ['name' => 'এ-লাইন ফ্রক কাট', 'extra_price' => 5000, 'is_default' => false],
                        ['name' => 'আনারকলি গাউন কাট', 'extra_price' => 15000, 'is_default' => false],
                    ],
                ],
                [
                    'name' => 'অতিরিক্ত পাইপিন ও ডিজাইন',
                    'type' => 'checkbox',
                    'values' => [
                        ['name' => 'গলা ও হাতায় পাইপিন', 'extra_price' => 5000, 'is_default' => false],
                        ['name' => 'সাইড ফাড়ে পাইপিন', 'extra_price' => 5000, 'is_default' => false],
                        ['name' => 'প্রিমিয়াম লেইস ফিটিংস', 'extra_price' => 10000, 'is_default' => false],
                        ['name' => 'সম্পূর্ণ ইনার আস্তর', 'extra_price' => 15000, 'is_default' => false],
                    ],
                ],
            ];
        } elseif (str_contains($slug, 'burqa') || str_contains($slug, 'abaya')) {
            $designs = [
                [
                    'name' => 'বোরকা প্যাটার্ন',
                    'type' => 'select',
                    'values' => [
                        ['name' => 'ফ্রন্ট ওপেন বাটন / জিপার', 'extra_price' => 0, 'is_default' => true],
                        ['name' => 'ক্লোজড গাউন স্টাইল', 'extra_price' => 0, 'is_default' => false],
                        ['name' => 'কিমোনো বাটারফ্লাই', 'extra_price' => 10000, 'is_default' => false],
                        ['name' => 'কাফতান আবায়া', 'extra_price' => 10000, 'is_default' => false],
                    ],
                ],
                [
                    'name' => 'হাতা ও কফ',
                    'type' => 'select',
                    'values' => [
                        ['name' => 'ইলাস্টিক কফ হাতা', 'extra_price' => 0, 'is_default' => true],
                        ['name' => 'লুপ বাটন কফ', 'extra_price' => 0, 'is_default' => false],
                        ['name' => 'লেইস এমব্রয়ডারি হাতা', 'extra_price' => 8000, 'is_default' => false],
                    ],
                ],
            ];
        } elseif (str_contains($slug, 'blouse') || str_contains($slug, 'lehenga') || str_contains($slug, 'choli')) {
            $designs = [
                [
                    'name' => 'কাটিং প্যাটার্ন',
                    'type' => 'select',
                    'values' => [
                        ['name' => 'প্রিন্সেস কাট', 'extra_price' => 5000, 'is_default' => true],
                        ['name' => '৪ ডাট কাট (Plain)', 'extra_price' => 0, 'is_default' => false],
                        ['name' => 'কাটোরি কাট', 'extra_price' => 5000, 'is_default' => false],
                        ['name' => 'সব্যসাচী ডিপ প্লাঞ্জ', 'extra_price' => 10000, 'is_default' => false],
                    ],
                ],
                [
                    'name' => 'ওপেনিং ও হুক',
                    'type' => 'select',
                    'values' => [
                        ['name' => 'ব্যাক ওপেন হুক', 'extra_price' => 0, 'is_default' => true],
                        ['name' => 'ফ্রন্ট ওপেন হুক', 'extra_price' => 0, 'is_default' => false],
                        ['name' => 'সাইড চেইন জিপার', 'extra_price' => 3000, 'is_default' => false],
                    ],
                ],
                [
                    'name' => 'ব্যাক নেক স্টাইল',
                    'type' => 'select',
                    'values' => [
                        ['name' => 'ডিপ রাউন্ড উইথ ডোরি', 'extra_price' => 3000, 'is_default' => true],
                        ['name' => 'বোট নেক ব্যাক', 'extra_price' => 0, 'is_default' => false],
                        ['name' => 'কিহোল ব্যাক', 'extra_price' => 2000, 'is_default' => false],
                    ],
                ],
            ];
        }

        foreach ($designs as $dIdx => $dData) {
            $opt = GarmentDesignOption::updateOrCreate(
                ['tenant_id' => $tenantId, 'garment_id' => $garment->id, 'name' => $dData['name']],
                [
                    'public_id' => (string) Str::ulid(),
                    'type' => $dData['type'],
                    'display_order' => $dIdx + 1,
                ]
            );

            foreach ($dData['values'] as $vIdx => $vData) {
                GarmentDesignValue::updateOrCreate(
                    ['tenant_id' => $tenantId, 'garment_design_option_id' => $opt->id, 'name' => $vData['name']],
                    [
                        'public_id' => (string) Str::ulid(),
                        'extra_price_minor' => $vData['extra_price'],
                        'is_default' => $vData['is_default'],
                        'display_order' => $vIdx + 1,
                    ]
                );
            }
        }
    }
}
