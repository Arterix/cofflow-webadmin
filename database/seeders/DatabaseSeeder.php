<?php

namespace Database\Seeders;

use App\Models\BomItem;
use App\Models\CondimentGroup;
use App\Models\CondimentOption;
use App\Models\Ingredient;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ----- Users -----
        User::firstOrCreate(
            ['email' => 'admin@cofflow.test'],
            [
                'name' => 'Owner Cofflow',
                'phone' => '081200000001',
                'password' => 'password',
                'role' => 'admin',
                'is_active' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'kasir@cofflow.test'],
            [
                'name' => 'Kasir Pagi',
                'phone' => '081200000002',
                'password' => 'password',
                'role' => 'kasir',
                'is_active' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'customer@cofflow.test'],
            [
                'name' => 'Pelanggan Setia',
                'phone' => '081200000003',
                'password' => 'password',
                'role' => 'customer',
                'is_active' => true,
            ]
        );

        // ----- Categories -----
        $coffee = MenuCategory::firstOrCreate(['slug' => 'coffee'], ['name' => 'Coffee']);
        $nonCoffee = MenuCategory::firstOrCreate(['slug' => 'non-coffee'], ['name' => 'Non-Coffee']);
        $snack = MenuCategory::firstOrCreate(['slug' => 'snack'], ['name' => 'Snack']);

        // ----- Ingredients -----
        $coffeeBean = Ingredient::firstOrCreate(['name' => 'Biji Kopi Arabica'], [
            'unit' => 'gram', 'current_stock' => 5000, 'minimum_stock' => 1000,
        ]);
        $milk = Ingredient::firstOrCreate(['name' => 'Susu UHT'], [
            'unit' => 'ml', 'current_stock' => 10000, 'minimum_stock' => 2000,
        ]);
        $matcha = Ingredient::firstOrCreate(['name' => 'Bubuk Matcha'], [
            'unit' => 'gram', 'current_stock' => 800, 'minimum_stock' => 200,
        ]);
        $sugar = Ingredient::firstOrCreate(['name' => 'Gula'], [
            'unit' => 'gram', 'current_stock' => 5000, 'minimum_stock' => 1000,
        ]);
        $croissant = Ingredient::firstOrCreate(['name' => 'Croissant'], [
            'unit' => 'pcs', 'current_stock' => 30, 'minimum_stock' => 10,
        ]);

        // ----- Condiment Groups -----
        $sizeGroup = CondimentGroup::firstOrCreate(
            ['name' => 'Ukuran'],
            ['type' => 'single_select', 'is_required' => true]
        );
        CondimentOption::firstOrCreate(['condiment_group_id' => $sizeGroup->id, 'name' => 'Regular'], ['additional_price' => 0]);
        CondimentOption::firstOrCreate(['condiment_group_id' => $sizeGroup->id, 'name' => 'Large'], ['additional_price' => 5000]);

        $sugarGroup = CondimentGroup::firstOrCreate(
            ['name' => 'Tingkat Manis'],
            ['type' => 'single_select', 'is_required' => true]
        );
        foreach (['Less Sugar', 'Normal', 'Extra Sugar'] as $opt) {
            CondimentOption::firstOrCreate(
                ['condiment_group_id' => $sugarGroup->id, 'name' => $opt],
                ['additional_price' => 0]
            );
        }

        $extraGroup = CondimentGroup::firstOrCreate(
            ['name' => 'Extra'],
            ['type' => 'multi_select', 'is_required' => false]
        );
        CondimentOption::firstOrCreate(
            ['condiment_group_id' => $extraGroup->id, 'name' => 'Extra Espresso Shot'],
            ['additional_price' => 5000]
        );
        CondimentOption::firstOrCreate(
            ['condiment_group_id' => $extraGroup->id, 'name' => 'Whipped Cream'],
            ['additional_price' => 3000]
        );

        // ----- Menus + BOM + Condiment associations -----
        $menusData = [
            ['name' => 'Espresso', 'category' => $coffee, 'price' => 18000, 'bom' => [[$coffeeBean->id, 18]]],
            ['name' => 'Cappuccino', 'category' => $coffee, 'price' => 25000, 'bom' => [[$coffeeBean->id, 18], [$milk->id, 150]]],
            ['name' => 'Latte', 'category' => $coffee, 'price' => 27000, 'bom' => [[$coffeeBean->id, 18], [$milk->id, 200]]],
            ['name' => 'Matcha Latte', 'category' => $nonCoffee, 'price' => 28000, 'bom' => [[$matcha->id, 12], [$milk->id, 200], [$sugar->id, 10]]],
            ['name' => 'Hot Chocolate', 'category' => $nonCoffee, 'price' => 26000, 'bom' => [[$milk->id, 200], [$sugar->id, 15]]],
            ['name' => 'Croissant Butter', 'category' => $snack, 'price' => 15000, 'bom' => [[$croissant->id, 1]]],
        ];

        foreach ($menusData as $m) {
            $menu = Menu::firstOrCreate(
                ['name' => $m['name']],
                [
                    'menu_category_id' => $m['category']->id,
                    'price' => $m['price'],
                    'description' => $m['name'].' khas Cofflow',
                    'is_active' => true,
                ]
            );

            foreach ($m['bom'] as [$ingredientId, $qty]) {
                BomItem::firstOrCreate(
                    ['menu_id' => $menu->id, 'ingredient_id' => $ingredientId],
                    ['quantity' => $qty]
                );
            }

            if ($m['category']->slug !== 'snack') {
                $menu->condimentGroups()->syncWithoutDetaching([
                    $sizeGroup->id, $sugarGroup->id, $extraGroup->id,
                ]);
            }
        }

        $this->call(DummyDataSeeder::class);

        $this->command?->info('Cofflow seed ready. Login: admin@cofflow.test / password (role admin)');
    }
}
