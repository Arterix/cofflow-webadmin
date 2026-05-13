<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_opname_id')->constrained('stock_opnames')->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained('ingredients')->cascadeOnUpdate();
            $table->decimal('system_stock', 10, 3);
            $table->decimal('physical_stock', 10, 3);
            $table->decimal('variance', 10, 3); // physical - system
            $table->string('variance_reason', 30)->nullable(); // spillage | waste | unrecorded_use | theft | measurement_error | other
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['stock_opname_id', 'ingredient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_opname_items');
    }
};
