<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_condiment_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained('menus')->cascadeOnDelete();
            $table->foreignId('condiment_group_id')->constrained('condiment_groups')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['menu_id', 'condiment_group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_condiment_groups');
    }
};
