<?php

namespace Tests\Feature;

use App\Models\LoyaltySetting;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\Order;
use App\Models\PointReward;
use App\Models\PointTransaction;
use App\Models\User;
use App\Models\UserVoucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LoyaltyPointsTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private User $kasir;
    private Menu $latte;
    private Menu $croissant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::create([
            'name' => 'Cust', 'email' => 'cust@test.com', 'password' => 'password',
            'role' => 'customer', 'is_active' => true,
        ]);
        $this->kasir = User::create([
            'name' => 'Kasir', 'email' => 'kasir@test.com', 'password' => 'password',
            'role' => 'kasir', 'is_active' => true,
        ]);

        $cat = MenuCategory::create(['name' => 'Coffee', 'slug' => 'coffee']);
        $this->latte = Menu::create(['name' => 'Latte', 'menu_category_id' => $cat->id, 'price' => 27000, 'is_active' => true]);
        $this->croissant = Menu::create(['name' => 'Croissant', 'menu_category_id' => $cat->id, 'price' => 15000, 'is_active' => true]);
    }

    private function placeOrder(User $user, array $extra = [], string $type = 'preorder'): Order
    {
        Sanctum::actingAs($user);
        $res = $this->postJson('/api/orders', array_merge([
            'order_type' => $type,
            'payment_method' => 'cash',
            'items' => [
                ['menu_id' => $this->latte->id, 'quantity' => 1],
                ['menu_id' => $this->croissant->id, 'quantity' => 1],
            ],
        ], $extra));
        $res->assertCreated();

        return Order::findOrFail($res->json('data.id'));
    }

    private function complete(Order $order): Order
    {
        Sanctum::actingAs($this->kasir);
        foreach (['processing', 'ready', 'completed'] as $s) {
            $this->patchJson("/api/staff/orders/{$order->id}/status", ['status' => $s])->assertOk();
        }

        return $order->fresh();
    }

    public function test_new_customer_starts_with_zero_points(): void
    {
        Sanctum::actingAs($this->customer);
        $this->getJson('/api/loyalty')
            ->assertOk()
            ->assertJsonPath('data.points_balance', 0)
            ->assertJsonPath('data.tier', 'bronze');
    }

    public function test_points_awarded_only_on_completion_and_only_once(): void
    {
        $order = $this->placeOrder($this->customer);
        $this->assertSame(0, $this->customer->fresh()->points_balance);

        $order = $this->complete($order);
        $this->assertSame(2, $order->points_earned);
        $this->assertSame(2, $this->customer->fresh()->points_balance);
        $this->assertSame(2, $this->customer->fresh()->lifetime_points);

        // Re-saving a completed order must not award again
        $order->update(['notes' => 'x']);
        app(\App\Services\LoyaltyService::class)->awardForOrder($order);
        $this->assertSame(2, $this->customer->fresh()->points_balance);
        $this->assertSame(1, PointTransaction::where('type', 'earn')->count());
    }

    public function test_one_point_per_unit_priced_at_least_minimum(): void
    {
        $cat = $this->latte->menu_category_id;
        $water = Menu::create(['name' => 'Air', 'menu_category_id' => $cat, 'price' => 5000, 'is_active' => true]);
        $exact = Menu::create(['name' => 'Teh', 'menu_category_id' => $cat, 'price' => 10000, 'is_active' => true]);

        $order = $this->complete($this->placeOrder($this->customer, ['items' => [
            ['menu_id' => $this->latte->id, 'quantity' => 2],
            ['menu_id' => $water->id, 'quantity' => 3],
            ['menu_id' => $exact->id, 'quantity' => 1],
        ]]));

        // 2 latte + 0 water + 1 teh (exactly 10k)
        $this->assertSame(3, $order->points_earned);
    }

    public function test_menu_override_and_no_tier_multiplier(): void
    {
        $this->latte->update(['points_override' => 3]);
        $this->customer->forceFill(['lifetime_points' => 200])->save(); // gold

        $order = $this->complete($this->placeOrder($this->customer));

        // 3 (override) + 1 croissant, tier doesn't multiply
        $this->assertSame(4, $order->points_earned);
    }

    public function test_walkin_orders_earn_no_points(): void
    {
        $order = $this->complete($this->placeOrder($this->kasir, [], 'walkin'));
        $this->assertSame(0, $order->points_earned);
        $this->assertSame(0, PointTransaction::count());
    }

    public function test_redeem_requires_enough_points_and_tier(): void
    {
        $reward = PointReward::create(['name' => 'Rp10k', 'type' => 'nominal', 'value' => 10000, 'points_cost' => 100, 'min_tier' => 'bronze']);
        $gold = PointReward::create(['name' => 'Gold', 'type' => 'percentage', 'value' => 50, 'points_cost' => 10, 'min_tier' => 'gold']);

        Sanctum::actingAs($this->customer);
        $this->postJson("/api/loyalty/rewards/{$reward->id}/redeem")->assertStatus(422);

        $this->customer->forceFill(['points_balance' => 150])->save();
        $this->postJson("/api/loyalty/rewards/{$gold->id}/redeem")->assertStatus(422);

        $this->postJson("/api/loyalty/rewards/{$reward->id}/redeem")->assertCreated();
        $this->assertSame(50, $this->customer->fresh()->points_balance);
        $this->assertSame(1, UserVoucher::where('user_id', $this->customer->id)->where('status', 'available')->count());
    }

    public function test_voucher_discount_reduces_total_not_points_and_cannot_combine_with_promo(): void
    {
        $this->customer->forceFill(['points_balance' => 200])->save();
        $reward = PointReward::create([
            'name' => '20%', 'type' => 'percentage', 'value' => 20, 'max_discount' => 5000,
            'min_order' => 30000, 'points_cost' => 150, 'min_tier' => 'bronze',
        ]);
        Sanctum::actingAs($this->customer);
        $voucherId = $this->postJson("/api/loyalty/rewards/{$reward->id}/redeem")->json('data.id');

        $items = [['menu_id' => $this->latte->id, 'quantity' => 1], ['menu_id' => $this->croissant->id, 'quantity' => 1]];
        $this->postJson('/api/orders/quote', ['items' => $items, 'voucher_id' => $voucherId])
            ->assertOk()
            ->assertJsonPath('data.voucher_discount', 5000)
            ->assertJsonPath('data.total', 37000)
            ->assertJsonPath('data.estimated_points', 2);

        $this->postJson('/api/orders', [
            'order_type' => 'preorder', 'payment_method' => 'cash', 'items' => $items,
            'voucher_id' => $voucherId, 'promo_code' => 'ANY',
        ])->assertStatus(422);

        $order = $this->placeOrder($this->customer, ['voucher_id' => $voucherId]);
        $this->assertEquals(37000, (float) $order->total);
        $this->assertSame('used', UserVoucher::find($voucherId)->status);

        // Voucher cannot be used twice
        $this->postJson('/api/orders', [
            'order_type' => 'preorder', 'payment_method' => 'cash', 'items' => $items, 'voucher_id' => $voucherId,
        ])->assertStatus(422);

        $order = $this->complete($order);
        $this->assertSame(2, $order->points_earned);
    }

    public function test_cancelled_order_returns_voucher_and_earns_nothing(): void
    {
        $this->customer->forceFill(['points_balance' => 100])->save();
        $reward = PointReward::create(['name' => 'Rp10k', 'type' => 'nominal', 'value' => 10000, 'points_cost' => 100, 'min_tier' => 'bronze']);
        Sanctum::actingAs($this->customer);
        $voucherId = $this->postJson("/api/loyalty/rewards/{$reward->id}/redeem")->json('data.id');

        $order = $this->placeOrder($this->customer, ['voucher_id' => $voucherId]);
        $this->deleteJson("/api/orders/{$order->id}")->assertOk();

        $this->assertSame('available', UserVoucher::find($voucherId)->status);
        $this->assertSame(0, $order->fresh()->points_earned);
    }

    public function test_disabled_program_awards_nothing(): void
    {
        LoyaltySetting::current()->update(['is_enabled' => false]);
        $order = $this->complete($this->placeOrder($this->customer));
        $this->assertSame(0, $this->customer->fresh()->points_balance);
        $this->assertSame(0, $order->points_earned);
    }

    public function test_menu_api_exposes_points_per_item(): void
    {
        $this->latte->update(['points_override' => 3]);
        $menus = collect($this->getJson('/api/menus')->assertOk()->json('data'))->keyBy('name');

        $this->assertSame(3, $menus['Latte']['points_per_item']);
        $this->assertSame(1, $menus['Croissant']['points_per_item']);
    }

    public function test_admin_sees_pending_points_until_completed(): void
    {
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.com', 'password' => 'password',
            'role' => 'admin', 'is_active' => true,
        ]);
        $order = $this->placeOrder($this->customer);

        $this->actingAs($admin, 'web')
            ->get(route('admin.membership.index', ['tab' => 'history']))
            ->assertOk()
            ->assertSee('Menunggu selesai')
            ->assertSee('#'.$order->id);

        $this->complete($order);

        $this->actingAs($admin, 'web')
            ->get(route('admin.membership.index', ['tab' => 'history']))
            ->assertOk()
            ->assertSee('Tidak ada poin tertunda')
            ->assertSee("Pesanan #{$order->id} selesai");
    }

    public function test_tier_rises_automatically_when_threshold_reached(): void
    {
        LoyaltySetting::current()->update(['silver_threshold' => 2, 'gold_threshold' => 10]);

        $this->complete($this->placeOrder($this->customer)); // +2 => silver

        Sanctum::actingAs($this->customer->fresh());
        $this->getJson('/api/loyalty')->assertOk()->assertJsonPath('data.tier', 'silver');
        $this->assertDatabaseHas('notifications', ['user_id' => $this->customer->id, 'type' => 'tier_up']);

        // Redeeming spends balance but never lowers the tier
        $reward = PointReward::create(['name' => 'X', 'type' => 'nominal', 'value' => 1000, 'points_cost' => 2, 'min_tier' => 'bronze']);
        $this->postJson("/api/loyalty/rewards/{$reward->id}/redeem")->assertCreated();
        Sanctum::actingAs($this->customer->fresh());
        $this->getJson('/api/loyalty')->assertJsonPath('data.points_balance', 0)->assertJsonPath('data.tier', 'silver');
    }

    public function test_admin_bonus_points_count_toward_tier(): void
    {
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.com', 'password' => 'password',
            'role' => 'admin', 'is_active' => true,
        ]);

        $this->actingAs($admin, 'web')
            ->post(route('admin.membership.adjust', $this->customer), ['points' => 60, 'reason' => 'bonus'])
            ->assertRedirect();

        $fresh = $this->customer->fresh();
        $this->assertSame(60, $fresh->lifetime_points);
        $this->assertSame('silver', $fresh->tier);
        $this->assertDatabaseHas('notifications', ['user_id' => $this->customer->id, 'type' => 'tier_up']);

        // Minus correction only touches the balance
        $this->actingAs($admin, 'web')
            ->post(route('admin.membership.adjust', $this->customer), ['points' => -10, 'reason' => 'koreksi']);
        $this->assertSame(60, $this->customer->fresh()->lifetime_points);
        $this->assertSame(50, $this->customer->fresh()->points_balance);
    }

    public function test_cancelled_order_drops_pending_points(): void
    {
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.com', 'password' => 'password',
            'role' => 'admin', 'is_active' => true,
        ]);
        $order = $this->placeOrder($this->customer);
        $order->update(['status' => 'cancelled']);

        $this->actingAs($admin, 'web')
            ->get(route('admin.membership.index', ['tab' => 'history']))
            ->assertOk()
            ->assertSee('Tidak ada poin tertunda');
        $this->assertSame(0, $this->customer->fresh()->points_balance);
    }
}
