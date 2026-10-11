<?php

namespace Tests\Feature;

use App\Models\BomItem;
use App\Models\CondimentBomItem;
use App\Models\CondimentGroup;
use App\Models\CondimentOption;
use App\Models\Ingredient;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemCondiment;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StockConcurrencyAndDeductionTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private Menu $menu;
    private Ingredient $coffeeBean;
    private Ingredient $milk;
    private CondimentOption $largeOption;
    private StockService $stockService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stockService = app(StockService::class);

        $this->customer = User::create([
            'name' => 'Stock Test Customer',
            'email' => 'stock@test.com',
            'phone' => '081233333333',
            'password' => bcrypt('password'),
            'role' => 'customer',
            'is_active' => true,
        ]);

        $category = MenuCategory::create(['name' => 'Coffee', 'slug' => 'coffee']);

        $this->menu = Menu::create([
            'name' => 'Caffe Latte',
            'menu_category_id' => $category->id,
            'price' => 28000,
            'is_active' => true,
        ]);

        $this->coffeeBean = Ingredient::create([
            'name' => 'Biji Kopi Arabika',
            'unit' => 'gram',
            'current_stock' => 1000.0,
            'minimum_stock' => 100.0,
        ]);

        $this->milk = Ingredient::create([
            'name' => 'Susu Fresh Milk',
            'unit' => 'ml',
            'current_stock' => 1000.0,
            'minimum_stock' => 200.0,
        ]);

        // Latte BOM: 18g coffee beans, 150ml milk
        BomItem::create([
            'menu_id' => $this->menu->id,
            'ingredient_id' => $this->coffeeBean->id,
            'quantity' => 18.0,
        ]);
        BomItem::create([
            'menu_id' => $this->menu->id,
            'ingredient_id' => $this->milk->id,
            'quantity' => 150.0,
        ]);

        // Condiment: Large size adds 50ml extra milk
        $group = CondimentGroup::create([
            'name' => 'Ukuran',
            'type' => 'single_select',
            'is_required' => false,
        ]);
        $this->menu->condimentGroups()->attach($group->id);

        $this->largeOption = CondimentOption::create([
            'condiment_group_id' => $group->id,
            'name' => 'Large',
            'additional_price' => 5000,
            'sort_order' => 1,
        ]);

        CondimentBomItem::create([
            'condiment_option_id' => $this->largeOption->id,
            'ingredient_id' => $this->milk->id,
            'quantity' => 50.0,
        ]);
    }

    public function test_stock_deduction_fails_when_ingredient_insufficient(): void
    {
        Sanctum::actingAs($this->customer, ['*']);

        // Set milk stock lower than needed
        $this->milk->update(['current_stock' => 50.0]); // needs at least 150ml

        $response = $this->postJson('/api/orders', [
            'order_type' => 'preorder',
            'payment_method' => 'cash',
            'items' => [
                [
                    'menu_id' => $this->menu->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('orders', 0);
        $this->assertEquals(50.0, (float) $this->milk->fresh()->current_stock);
    }

    public function test_stock_deducts_both_menu_bom_and_condiment_bom(): void
    {
        Sanctum::actingAs($this->customer, ['*']);

        // Order 2 Lattes with Large option (150ml + 50ml milk = 200ml per item -> 400ml total)
        // Coffee beans: 18g * 2 = 36g total
        $response = $this->postJson('/api/orders', [
            'order_type' => 'preorder',
            'payment_method' => 'cash',
            'items' => [
                [
                    'menu_id' => $this->menu->id,
                    'quantity' => 2,
                    'condiment_option_ids' => [$this->largeOption->id],
                ],
            ],
        ]);

        $response->assertStatus(201);

        $this->assertEquals(1000.0 - 36.0, (float) $this->coffeeBean->fresh()->current_stock);
        $this->assertEquals(1000.0 - 400.0, (float) $this->milk->fresh()->current_stock);
    }

    public function test_stock_restores_correctly_on_cancellation(): void
    {
        Sanctum::actingAs($this->customer, ['*']);

        $response = $this->postJson('/api/orders', [
            'order_type' => 'preorder',
            'payment_method' => 'cash',
            'items' => [
                [
                    'menu_id' => $this->menu->id,
                    'quantity' => 2,
                    'condiment_option_ids' => [$this->largeOption->id],
                ],
            ],
        ]);

        $response->assertStatus(201);
        $orderId = $response->json('data.id');

        // Cancel the order
        $cancelResponse = $this->deleteJson("/api/orders/{$orderId}");
        $cancelResponse->assertStatus(200);

        // Stock must be restored back to initial 1000.0
        $this->assertEquals(1000.0, (float) $this->coffeeBean->fresh()->current_stock);
        $this->assertEquals(1000.0, (float) $this->milk->fresh()->current_stock);
    }
}
