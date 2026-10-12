<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('rupiah_per_point')->default(1000);
            $table->unsignedInteger('silver_threshold')->default(500);
            $table->unsignedInteger('gold_threshold')->default(1500);
            $table->decimal('bronze_multiplier', 4, 2)->default(1);
            $table->decimal('silver_multiplier', 4, 2)->default(1.25);
            $table->decimal('gold_multiplier', 4, 2)->default(1.5);
            $table->unsignedInteger('voucher_valid_days')->default(30);
            $table->timestamps();
        });

        Schema::create('point_rewards', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('type', 10); // percentage | nominal
            $table->decimal('value', 10, 2);
            $table->decimal('max_discount', 10, 2)->nullable();
            $table->decimal('min_order', 10, 2)->default(0);
            $table->unsignedInteger('points_cost');
            $table->string('min_tier', 10)->default('bronze'); // bronze | silver | gold
            $table->unsignedInteger('quota')->nullable();
            $table->unsignedInteger('redeemed_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('user_vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('point_reward_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->string('type', 10);
            $table->decimal('value', 10, 2);
            $table->decimal('max_discount', 10, 2)->nullable();
            $table->decimal('min_order', 10, 2)->default(0);
            $table->unsignedInteger('points_spent');
            $table->string('status', 10)->default('available'); // available | used | expired
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('point_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10); // earn | redeem | adjust
            $table->integer('points');
            $table->integer('balance_after');
            $table->string('description', 255);
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->unsignedBigInteger('user_voucher_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('points_balance')->default(0);
            $table->unsignedInteger('lifetime_points')->default(0);
        });

        Schema::table('menus', function (Blueprint $table) {
            $table->unsignedInteger('points_override')->nullable();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('user_voucher_id')->nullable()->index();
            $table->decimal('voucher_discount', 10, 2)->default(0);
            $table->decimal('points_base', 10, 2)->default(0);
            $table->unsignedInteger('points_earned')->default(0);
            $table->timestamp('points_awarded_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['user_voucher_id']);
            $table->dropColumn(['user_voucher_id', 'voucher_discount', 'points_base', 'points_earned', 'points_awarded_at']);
        });
        Schema::table('menus', fn (Blueprint $table) => $table->dropColumn('points_override'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['points_balance', 'lifetime_points']));
        Schema::dropIfExists('point_transactions');
        Schema::dropIfExists('user_vouchers');
        Schema::dropIfExists('point_rewards');
        Schema::dropIfExists('loyalty_settings');
    }
};
