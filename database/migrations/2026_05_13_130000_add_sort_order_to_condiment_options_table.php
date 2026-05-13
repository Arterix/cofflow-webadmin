<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('condiment_options', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('additional_price');
            $table->index(['condiment_group_id', 'sort_order']);
        });

        // Seed sort_order for existing rows by id within each group.
        $groups = DB::table('condiment_options')->select('condiment_group_id')->distinct()->pluck('condiment_group_id');
        foreach ($groups as $groupId) {
            $i = 0;
            DB::table('condiment_options')
                ->where('condiment_group_id', $groupId)
                ->orderBy('id')
                ->get(['id'])
                ->each(function ($row) use (&$i) {
                    DB::table('condiment_options')->where('id', $row->id)->update(['sort_order' => $i++]);
                });
        }
    }

    public function down(): void
    {
        Schema::table('condiment_options', function (Blueprint $table) {
            $table->dropIndex(['condiment_group_id', 'sort_order']);
            $table->dropColumn('sort_order');
        });
    }
};
