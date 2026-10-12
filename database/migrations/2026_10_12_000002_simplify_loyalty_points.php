<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Switch from "1 point per Rp1.000 × tier multiplier" to
 * "1 point per item priced >= min_item_price" (no multiplier).
 */
return new class extends Migration
{
    private array $rewardCosts = [
        'Potongan Rp 10.000' => [100, 10],
        'Diskon 20%' => [150, 15],
        'Diskon 50% Gold' => [400, 40],
    ];

    public function up(): void
    {
        Schema::table('loyalty_settings', function (Blueprint $table) {
            $table->unsignedInteger('min_item_price')->default(10000);
        });
        Schema::table('loyalty_settings', function (Blueprint $table) {
            $table->dropColumn(['rupiah_per_point', 'bronze_multiplier', 'silver_multiplier', 'gold_multiplier']);
        });
        Schema::table('loyalty_settings', function (Blueprint $table) {
            $table->unsignedInteger('silver_threshold')->default(50)->change();
            $table->unsignedInteger('gold_threshold')->default(150)->change();
        });

        DB::table('loyalty_settings')
            ->where('silver_threshold', 500)->where('gold_threshold', 1500)
            ->update(['silver_threshold' => 50, 'gold_threshold' => 150]);

        foreach ($this->rewardCosts as $name => [$old, $new]) {
            DB::table('point_rewards')
                ->where('name', $name)->where('points_cost', $old)->where('redeemed_count', 0)
                ->update(['points_cost' => $new]);
        }
    }

    public function down(): void
    {
        Schema::table('loyalty_settings', function (Blueprint $table) {
            $table->unsignedInteger('rupiah_per_point')->default(1000);
            $table->decimal('bronze_multiplier', 4, 2)->default(1);
            $table->decimal('silver_multiplier', 4, 2)->default(1.25);
            $table->decimal('gold_multiplier', 4, 2)->default(1.5);
        });
        Schema::table('loyalty_settings', function (Blueprint $table) {
            $table->dropColumn('min_item_price');
            $table->unsignedInteger('silver_threshold')->default(500)->change();
            $table->unsignedInteger('gold_threshold')->default(1500)->change();
        });

        DB::table('loyalty_settings')
            ->where('silver_threshold', 50)->where('gold_threshold', 150)
            ->update(['silver_threshold' => 500, 'gold_threshold' => 1500]);

        foreach ($this->rewardCosts as $name => [$old, $new]) {
            DB::table('point_rewards')
                ->where('name', $name)->where('points_cost', $new)->where('redeemed_count', 0)
                ->update(['points_cost' => $old]);
        }
    }
};
