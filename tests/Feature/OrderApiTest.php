<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private Menu $menu;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::factory()->create(['role' => 'customer']);
        $category = MenuCategory::factory()->create();
        $this->menu = Menu::factory()->create([
            'menu_category_id' => $category->id,
            'price' => 25000,
            'is_active' => true,
        ]);
    }

    public function test_create_order_with_cash_payment(): void
    {
        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/orders', [
                'order_type' => 'preorder',
                'payment_method' => 'cash',
                'items' => [
                    ['menu_id' => $this->menu->id, 'quantity' => 2, 'notes' => 'Susu: Oat, Kemanisan: Normal'],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $data = $response->json('data');
        $this->assertEquals('pending', $data['status']);
        $this->assertEquals('cash', $data['payment_method']);
        $this->assertNotNull($data['queue_number']);
    }

    public function test_create_order_requires_authentication(): void
    {
        $response = $this->postJson('/api/orders', [
            'order_type' => 'preorder',
            'payment_method' => 'cash',
            'items' => [
                ['menu_id' => $this->menu->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(401);
    }

    public function test_create_order_validates_required_fields(): void
    {
        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/orders', []);

        $response->assertStatus(422);
    }

    public function test_create_order_validates_items_not_empty(): void
    {
        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/orders', [
                'order_type' => 'preorder',
                'payment_method' => 'cash',
                'items' => [],
            ]);

        $response->assertStatus(422);
    }

    public function test_index_returns_only_own_orders(): void
    {
        $other = User::factory()->create(['role' => 'customer']);

        // Create orders for both users
        Order::factory()->count(2)->create(['user_id' => $this->customer->id]);
        Order::factory()->count(3)->create(['user_id' => $other->id]);

        $response = $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/orders');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data);
    }

    public function test_show_returns_own_order(): void
    {
        $order = Order::factory()->create(['user_id' => $this->customer->id]);

        $response = $this->actingAs($this->customer, 'sanctum')
            ->getJson("/api/orders/{$order->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_show_returns_404_for_nonexistent_order(): void
    {
        $response = $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/orders/99999');

        $response->assertStatus(404);
    }

    public function test_payment_status_returns_order_payment_info(): void
    {
        $order = Order::factory()->create([
            'user_id' => $this->customer->id,
            'payment_status' => 'unpaid',
            'payment_method' => 'qris',
        ]);

        $response = $this->actingAs($this->customer, 'sanctum')
            ->getJson("/api/orders/{$order->id}/payment-status");

        $response->assertStatus(200);
    }
}
