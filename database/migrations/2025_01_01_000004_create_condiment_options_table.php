<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('condiment_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('condiment_group_id')->constrained('condiment_groups')->cascadeOnDelete();
            $table->string('name', 100);
            $table->decimal('additional_price', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('condiment_options');
    }
};
