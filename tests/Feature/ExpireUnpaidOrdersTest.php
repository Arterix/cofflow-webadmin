<?php

namespace Tests\Feature;

use App\Models\BomItem;
use App\Models\Ingredient;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpireUnpaidOrdersTest extends TestCase
{
    use RefreshDatabase;

    private Ingredient $coffeeBean;
    private Menu $menu;
    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::create([
            'name' => 'Expire Test Customer',
            'email' => 'expire@test.com',
            'phone' => '081244444444',
            'password' => bcrypt('password'),
            'role' => 'customer',
            'is_active' => true,
        ]);

        $category = MenuCategory::create(['name' => 'Coffee', 'slug' => 'coffee']);

        $this->menu = Menu::create([
            'name' => 'Espresso',
            'menu_category_id' => $category->id,
            'price' => 18000,
            'is_active' => true,
        ]);

        $this->coffeeBean = Ingredient::create([
            'name' => 'Biji Kopi Robusta',
            'unit' => 'gram',
            'current_stock' => 500.0,
            'minimum_stock' => 50.0,
        ]);

        BomItem::create([
            'menu_id' => $this->menu->id,
            'ingredient_id' => $this->coffeeBean->id,
            'quantity' => 18.0,
        ]);
    }

    public function test_expired_unpaid_order_is_cancelled_and_stock_restored(): void
    {
        // 1. Create an unpaid QRIS order that expired 10 minutes ago
        // Initial stock: 500g, after deduction of 18g: 482g
        $this->coffeeBean->update(['current_stock' => 482.0]);

        $order = Order::create([
            'user_id' => $this->customer->id,
            'order_type' => 'preorder',
            'status' => 'pending',
            'payment_method' => 'qris',
            'payment_status' => 'unpaid',
            'subtotal' => 18000,
            'discount_amount' => 0,
            'total' => 18000,
            'payment_expired_at' => now()->subMinutes(10),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'menu_id' => $this->menu->id,
            'quantity' => 1,
            'unit_price' => 18000,
            'applied_discount' => 0,
        ]);

        // 2. Run the expire-unpaid command
        $this->artisan('orders:expire-unpaid')
            ->assertExitCode(0);

        // 3. Verify order was marked cancelled
        $this->assertEquals('cancelled', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->stock_restored_at);

        // 4. Verify stock was restored from 482g to 500g
        $this->assertEquals(500.0, (float) $this->coffeeBean->fresh()->current_stock);
    }

    public function test_non_expired_unpaid_order_remains_pending(): void
    {
        // Order expires in 10 minutes
        $order = Order::create([
            'user_id' => $this->customer->id,
            'order_type' => 'preorder',
            'status' => 'pending',
            'payment_method' => 'qris',
            'payment_status' => 'unpaid',
            'subtotal' => 18000,
            'discount_amount' => 0,
            'total' => 18000,
            'payment_expired_at' => now()->addMinutes(10),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'menu_id' => $this->menu->id,
            'quantity' => 1,
            'unit_price' => 18000,
            'applied_discount' => 0,
        ]);

        $this->artisan('orders:expire-unpaid')
            ->assertExitCode(0);

        // Order remains pending, stock untouched
        $this->assertEquals('pending', $order->fresh()->status);
        $this->assertNull($order->fresh()->stock_restored_at);
        $this->assertEquals(500.0, (float) $this->coffeeBean->fresh()->current_stock);
    }
}
