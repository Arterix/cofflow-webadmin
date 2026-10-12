<?php

namespace Database\Seeders;

use App\Models\LoyaltySetting;
use App\Models\PointReward;
use Illuminate\Database\Seeder;

/**
 * Default loyalty settings + sample rewards. Only touches loyalty tables; safe to re-run.
 */
class LoyaltySeeder extends Seeder
{
    public function run(): void
    {
        LoyaltySetting::current();

        $rewards = [
            ['name' => 'Potongan Rp 10.000', 'description' => 'Potongan langsung untuk pesanan apa saja', 'type' => 'nominal', 'value' => 10000, 'max_discount' => null, 'min_order' => 25000, 'points_cost' => 10, 'min_tier' => 'bronze'],
            ['name' => 'Diskon 20%', 'description' => 'Diskon 20%, maksimal Rp 15.000', 'type' => 'percentage', 'value' => 20, 'max_discount' => 15000, 'min_order' => 30000, 'points_cost' => 15, 'min_tier' => 'bronze'],
            ['name' => 'Diskon 50% Gold', 'description' => 'Khusus member Gold, maksimal Rp 30.000', 'type' => 'percentage', 'value' => 50, 'max_discount' => 30000, 'min_order' => 40000, 'points_cost' => 40, 'min_tier' => 'gold'],
        ];

        foreach ($rewards as $r) {
            PointReward::firstOrCreate(['name' => $r['name']], $r + ['is_active' => true]);
        }
    }
}
