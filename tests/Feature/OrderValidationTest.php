<?php

namespace Tests\Feature;

use App\Models\CondimentGroup;
use App\Models\CondimentOption;
use App\Models\Ingredient;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private User $kasir;
    private Menu $menu;
    private CondimentGroup $singleGroup;
    private CondimentOption $opt1;
    private CondimentOption $opt2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::create([
            'name' => 'Customer Test',
            'email' => 'cust@test.com',
            'phone' => '081211111111',
            'password' => bcrypt('password'),
            'role' => 'customer',
            'is_active' => true,
        ]);

        $this->kasir = User::create([
            'name' => 'Kasir Test',
            'email' => 'kasir@test.com',
            'phone' => '081222222222',
            'password' => bcrypt('password'),
            'role' => 'kasir',
            'is_active' => true,
        ]);

        $category = MenuCategory::create(['name' => 'Coffee', 'slug' => 'coffee']);

        $this->menu = Menu::create([
            'name' => 'Latte',
            'menu_category_id' => $category->id,
            'price' => 25000,
            'is_active' => true,
        ]);

        $this->singleGroup = CondimentGroup::create([
            'name' => 'Ukuran',
            'type' => 'single_select',
            'is_required' => true,
        ]);

        $this->opt1 = CondimentOption::create([
            'condiment_group_id' => $this->singleGroup->id,
            'name' => 'Regular',
            'additional_price' => 0,
            'sort_order' => 1,
        ]);

        $this->opt2 = CondimentOption::create([
            'condiment_group_id' => $this->singleGroup->id,
            'name' => 'Large',
            'additional_price' => 5000,
            'sort_order' => 2,
        ]);

        $this->menu->condimentGroups()->attach($this->singleGroup->id);
    }

    public function test_customer_cannot_create_walkin_order(): void
    {
        Sanctum::actingAs($this->customer, ['*']);

        $response = $this->postJson('/api/orders', [
            'order_type' => 'walkin',
            'payment_method' => 'cash',
            'items' => [
                [
                    'menu_id' => $this->menu->id,
                    'quantity' => 1,
                    'condiment_option_ids' => [$this->opt1->id],
                ],
            ],
        ]);

        $response->assertStatus(403);
    }

    public function test_cashier_can_create_walkin_order(): void
    {
        Sanctum::actingAs($this->kasir, ['*']);

        $response = $this->postJson('/api/orders', [
            'order_type' => 'walkin',
            'payment_method' => 'cash',
            'items' => [
                [
                    'menu_id' => $this->menu->id,
                    'quantity' => 1,
                    'condiment_option_ids' => [$this->opt1->id],
                ],
            ],
        ]);

        $response->assertStatus(201);
    }

    public function test_order_with_unrelated_condiment_fails_validation(): void
    {
        Sanctum::actingAs($this->customer, ['*']);

        // Create an unrelated condiment option
        $unrelatedGroup = CondimentGroup::create([
            'name' => 'Topping Snack',
            'type' => 'single_select',
            'is_required' => false,
        ]);
        $unrelatedOpt = CondimentOption::create([
            'condiment_group_id' => $unrelatedGroup->id,
            'name' => 'Cheese',
            'additional_price' => 2000,
        ]);

        $response = $this->postJson('/api/orders', [
            'order_type' => 'preorder',
            'payment_method' => 'cash',
            'items' => [
                [
                    'menu_id' => $this->menu->id,
                    'quantity' => 1,
                    'condiment_option_ids' => [$unrelatedOpt->id],
                ],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['items.0.condiment_option_ids']);
    }

    public function test_order_with_multiple_options_on_single_select_group_fails_validation(): void
    {
        Sanctum::actingAs($this->customer, ['*']);

        // Send both Regular and Large for single_select group Ukuran
        $response = $this->postJson('/api/orders', [
            'order_type' => 'preorder',
            'payment_method' => 'cash',
            'items' => [
                [
                    'menu_id' => $this->menu->id,
                    'quantity' => 1,
                    'condiment_option_ids' => [$this->opt1->id, $this->opt2->id],
                ],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['items.0.condiment_option_ids']);
    }
}
