<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('condiment_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('type', 20); // single_select | multi_select
            $table->boolean('is_required')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('condiment_groups');
    }
};
