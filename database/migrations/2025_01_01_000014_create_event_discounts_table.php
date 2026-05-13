<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_discounts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('type', 10);
            $table->decimal('value', 10, 2);
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('event_discount_menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_discount_id')->constrained('event_discounts')->cascadeOnDelete();
            $table->foreignId('menu_id')->constrained('menus')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['event_discount_id', 'menu_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_discount_menus');
        Schema::dropIfExists('event_discounts');
    }
};
