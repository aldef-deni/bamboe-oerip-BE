<?php

namespace Database\Seeders;

use App\Models\Amenity;
use App\Models\Setting;
use App\Models\User;
use App\Models\Villa;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ---- Admin & demo user ----
        User::updateOrCreate(
            ['email' => 'admin@bamboeoerip.com'],
            [
                'name' => 'Admin Villa Box',
                'username' => 'admin',
                'phone' => '081234567890',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ]
        );

        User::updateOrCreate(
            ['email' => 'user@example.com'],
            [
                'name' => 'Demo User',
                'phone' => '081200000000',
                'password' => Hash::make('password'),
                'role' => 'user',
            ]
        );

        // ---- Settings ----
        $settings = [
            'site_name' => 'Villa Box',
            'site_tagline' => 'Sewa villa & staycation terbaik di Indonesia',
            'contact_email' => 'info@bamboeoerip.com',
            'contact_phone' => '62895338606270',
            'whatsapp' => '62895338606270',
            'address' => 'Jl. Palagan Tentara Pelajar KM 16, Glondong, Purwobinangun, Pakem, Sleman, Daerah Istimewa Yogyakarta.',
            'service_fee_percent' => '5',
            'bank_name' => 'BCA',
            'bank_account_number' => '1234567890',
            'bank_account_name' => 'PT Villa Box Indonesia',
            'payment_instructions' => 'Transfer total tagihan ke rekening di atas, lalu unggah bukti transfer. Pesanan diverifikasi maksimal 1x24 jam.',
        ];
        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        // ---- Amenities ----
        $amenities = [
            ['name' => 'WiFi', 'icon' => 'wifi'],
            ['name' => 'Kolam Renang', 'icon' => 'pool'],
            ['name' => 'AC', 'icon' => 'ac'],
            ['name' => 'Dapur', 'icon' => 'kitchen'],
            ['name' => 'Parkir Gratis', 'icon' => 'parking'],
            ['name' => 'TV', 'icon' => 'tv'],
            ['name' => 'Water Heater', 'icon' => 'shower'],
            ['name' => 'BBQ', 'icon' => 'bbq'],
            ['name' => 'Pet Friendly', 'icon' => 'pet'],
            ['name' => 'Sarapan', 'icon' => 'breakfast'],
        ];
        foreach ($amenities as $a) {
            Amenity::updateOrCreate(['name' => $a['name']], $a);
        }
        $amenityIds = Amenity::pluck('id')->all();

        // ---- Sample villas ----
        $villas = [
            [
                'name' => 'Bamboe Villa Ubud',
                'type' => 'Villa',
                'city' => 'Ubud',
                'province' => 'Bali',
                'description' => 'Villa bambu eksotis dengan pemandangan sawah terasering, kolam renang pribadi, dan suasana tenang khas Ubud.',
                'address' => 'Jl. Tegallalang, Ubud, Gianyar',
                'price_per_night' => 1250000,
                'weekend_price' => 1500000,
                'max_guests' => 4,
                'bedrooms' => 2,
                'bathrooms' => 2,
                'size_sqm' => 120,
                'is_featured' => true,
            ],
            [
                'name' => 'Villa Box Canggu Beachfront',
                'type' => 'Villa',
                'city' => 'Canggu',
                'province' => 'Bali',
                'description' => 'Villa modern tepi pantai Canggu, cocok untuk surfing dan sunset. Dilengkapi infinity pool.',
                'address' => 'Jl. Pantai Batu Bolong, Canggu',
                'price_per_night' => 2100000,
                'weekend_price' => 2500000,
                'max_guests' => 6,
                'bedrooms' => 3,
                'bathrooms' => 3,
                'size_sqm' => 200,
                'is_featured' => true,
            ],
            [
                'name' => 'Cabin Box Lembang',
                'type' => 'Cabin',
                'city' => 'Bandung',
                'province' => 'Jawa Barat',
                'description' => 'Kabin kayu hangat di perbukitan Lembang dengan udara sejuk dan pemandangan pegunungan.',
                'address' => 'Jl. Maribaya, Lembang, Bandung Barat',
                'price_per_night' => 850000,
                'weekend_price' => 1000000,
                'max_guests' => 3,
                'bedrooms' => 1,
                'bathrooms' => 1,
                'size_sqm' => 60,
                'is_featured' => true,
            ],
            [
                'name' => 'Villa Box Puncak Highland',
                'type' => 'Villa',
                'city' => 'Bogor',
                'province' => 'Jawa Barat',
                'description' => 'Villa keluarga luas di kawasan Puncak dengan taman hijau dan area BBQ.',
                'address' => 'Jl. Raya Puncak, Cisarua, Bogor',
                'price_per_night' => 1650000,
                'weekend_price' => 1950000,
                'max_guests' => 8,
                'bedrooms' => 4,
                'bathrooms' => 3,
                'size_sqm' => 250,
                'is_featured' => false,
            ],
            [
                'name' => 'Villa Box Seminyak Suite',
                'type' => 'Villa',
                'city' => 'Seminyak',
                'province' => 'Bali',
                'description' => 'Villa mewah di jantung Seminyak, dekat dengan restoran, beach club, dan pusat perbelanjaan.',
                'address' => 'Jl. Kayu Aya, Seminyak',
                'price_per_night' => 1850000,
                'weekend_price' => 2200000,
                'max_guests' => 4,
                'bedrooms' => 2,
                'bathrooms' => 2,
                'size_sqm' => 140,
                'is_featured' => true,
            ],
            [
                'name' => 'Pod Box Dieng',
                'type' => 'Pod',
                'city' => 'Wonosobo',
                'province' => 'Jawa Tengah',
                'description' => 'Pod minimalis unik di dataran tinggi Dieng, cocok untuk pasangan yang mencari ketenangan.',
                'address' => 'Dataran Tinggi Dieng, Wonosobo',
                'price_per_night' => 550000,
                'weekend_price' => 700000,
                'max_guests' => 2,
                'bedrooms' => 1,
                'bathrooms' => 1,
                'size_sqm' => 30,
                'is_featured' => false,
            ],
        ];

        foreach ($villas as $data) {
            $villa = Villa::updateOrCreate(
                ['slug' => Str::slug($data['name'])],
                array_merge($data, ['is_active' => true])
            );
            $villa->amenities()->sync(collect($amenityIds)->shuffle()->take(rand(4, 6))->all());
        }
    }
}
