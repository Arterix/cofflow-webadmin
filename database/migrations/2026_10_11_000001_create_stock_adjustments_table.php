<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('adjusted_by')->constrained('users')->cascadeOnDelete();
            $table->string('type')->default('manual'); // manual | restock | order_deduct | order_restore | opname
            $table->decimal('before', 12, 3);
            $table->decimal('after', 12, 3);
            // delta dihitung aplikasi saat insert, tidak pakai generated column
            // agar kompatibel dengan SQLite (development) dan MySQL (production)
            $table->decimal('delta', 12, 3)->default(0);
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['ingredient_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');
    }
};
