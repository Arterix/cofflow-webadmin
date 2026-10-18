<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_menus_index_returns_all_available_menus(): void
    {
        $category = MenuCategory::factory()->create(['name' => 'Coffee']);
        Menu::factory()->count(3)->create([
            'menu_category_id' => $category->id,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/menus');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $data = $response->json('data');
        $this->assertCount(3, $data);
    }

    public function test_menus_index_is_public_no_auth_required(): void
    {
        $response = $this->getJson('/api/menus');

        // Should not return 401
        $response->assertStatus(200);
    }

    public function test_menus_show_returns_single_menu(): void
    {
        $category = MenuCategory::factory()->create();
        $menu = Menu::factory()->create(['menu_category_id' => $category->id]);

        $response = $this->getJson("/api/menus/{$menu->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $data = $response->json('data');
        $this->assertEquals($menu->name, $data['name']);
    }

    public function test_menus_show_returns_404_for_missing_menu(): void
    {
        $response = $this->getJson('/api/menus/99999');

        $response->assertStatus(404);
    }

    public function test_categories_index_returns_all_categories(): void
    {
        MenuCategory::factory()->count(4)->create();

        $response = $this->getJson('/api/categories');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $data = $response->json('data');
        $this->assertCount(4, $data);
    }

    public function test_categories_is_public_no_auth_required(): void
    {
        $response = $this->getJson('/api/categories');

        $response->assertStatus(200);
    }
}
