<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('condiment_bom_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('condiment_option_id')->constrained('condiment_options')->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained('ingredients')->cascadeOnDelete();
            $table->decimal('quantity', 10, 3);
            $table->timestamps();

            $table->unique(['condiment_option_id', 'ingredient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('condiment_bom_items');
    }
};
