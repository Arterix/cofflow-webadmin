<?php

namespace Database\Seeders;

use App\Models\EventDiscount;
use App\Models\Ingredient;
use App\Models\Menu;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemCondiment;
use App\Models\ProductDiscount;
use App\Models\PromoCode;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DummyDataSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'admin')->first();
        $kasir = User::where('role', 'kasir')->first();
        $customer = User::where('role', 'customer')->first();

        // Extra walk-in customers so user_id distribution looks real.
        $extraCustomers = [];
        foreach (['Andi Pratama', 'Sari Wulan', 'Budi Hartono', 'Citra Dewi', 'Ridwan Effendi'] as $i => $name) {
            $extraCustomers[] = User::firstOrCreate(
                ['email' => 'cust'.($i + 1).'@cofflow.test'],
                [
                    'name' => $name,
                    'phone' => '0812'.str_pad((string) ($i + 100), 8, '0', STR_PAD_LEFT),
                    'password' => 'password',
                    'role' => 'customer',
                    'is_active' => true,
                ]
            );
        }
        $customers = collect([$customer, ...$extraCustomers])->filter()->values();

        $this->seedDiscounts();
        $this->seedOrders($customers, $kasir);
        $this->seedOpnames($admin, $kasir);

        $this->command?->info('Dummy data seeded: orders + discounts + opnames.');
    }

    private function seedDiscounts(): void
    {
        $today = Carbon::today();

        $latte = Menu::where('name', 'Latte')->first();
        if ($latte) {
            ProductDiscount::firstOrCreate(
                ['menu_id' => $latte->id, 'start_date' => $today->copy()->subDays(3)->toDateString()],
                [
                    'type' => 'percentage',
                    'value' => 15,
                    'end_date' => $today->copy()->addDays(7)->toDateString(),
                    'is_active' => true,
                ]
            );
        }

        PromoCode::firstOrCreate(
            ['code' => 'WELCOME10'],
            [
                'type' => 'percentage',
                'value' => 10,
                'max_usage' => 100,
                'used_count' => 12,
                'min_order' => 20000,
                'start_date' => $today->copy()->subDays(5)->toDateString(),
                'end_date' => $today->copy()->addDays(30)->toDateString(),
                'is_active' => true,
            ]
        );

        PromoCode::firstOrCreate(
            ['code' => 'EXPIRED5K'],
            [
                'type' => 'nominal',
                'value' => 5000,
                'max_usage' => 50,
                'used_count' => 50,
                'min_order' => 0,
                'start_date' => $today->copy()->subDays(30)->toDateString(),
                'end_date' => $today->copy()->subDays(5)->toDateString(),
                'is_active' => true,
            ]
        );

        $event = EventDiscount::firstOrCreate(
            ['name' => 'Weekend Coffee Fest'],
            [
                'type' => 'percentage',
                'value' => 20,
                'start_date' => $today->copy()->subDays(1)->toDateString(),
                'end_date' => $today->copy()->addDays(2)->toDateString(),
                'is_active' => true,
            ]
        );

        $coffeeMenus = Menu::whereHas('category', fn ($q) => $q->where('slug', 'coffee'))->pluck('id')->all();
        if (! empty($coffeeMenus)) {
            $event->menus()->syncWithoutDetaching($coffeeMenus);
        }
    }

    private function seedOrders($customers, ?User $kasir): void
    {
        if (Order::exists()) {
            return; // do not duplicate on re-seed
        }

        $menus = Menu::with('condimentGroups.options')->where('is_active', true)->get();
        if ($menus->isEmpty()) {
            return;
        }

        $statusBuckets = [
            // [status, paymentStatus, weight]
            ['completed', 'paid', 60],
            ['ready', 'paid', 10],
            ['processing', 'paid', 8],
            ['pending', 'unpaid', 6],
            ['cancelled', 'unpaid', 6],
        ];
        $paymentMethods = ['cash', 'qris', 'virtual_account'];

        // Distribute orders across past 7 days. More orders on recent days.
        $perDay = [12, 14, 11, 16, 18, 22, 24]; // 6 days ago → today

        Order::withoutEvents(function () use ($menus, $statusBuckets, $paymentMethods, $perDay, $customers, $kasir) {
            $orderId = 1;

            for ($d = 0; $d < 7; $d++) {
                $date = Carbon::today()->subDays(6 - $d);
                $count = $perDay[$d];
                $dailyQueue = 0;

                for ($i = 0; $i < $count; $i++) {
                    // Spread across business hours 07–21, peak around 12 and 18.
                    $hourPool = [7, 8, 9, 10, 11, 12, 12, 13, 14, 15, 16, 17, 18, 18, 19, 20];
                    $hour = $hourPool[array_rand($hourPool)];
                    $createdAt = $date->copy()->setTime($hour, random_int(0, 59), random_int(0, 59));

                    $status = $this->weightedPick($statusBuckets);
                    [$statusVal, $paymentStatus] = [$status[0], $status[1]];

                    $isWalkin = random_int(0, 100) < 65;
                    $orderType = $isWalkin ? 'walkin' : 'preorder';
                    $paymentMethod = $isWalkin ? 'cash' : $paymentMethods[array_rand($paymentMethods)];

                    // 1–3 items.
                    $itemCount = random_int(1, 3);
                    $pickedMenus = $menus->random(min($itemCount, $menus->count()));

                    $subtotal = 0;
                    $discount = 0;
                    $itemsBuffer = [];

                    foreach ($pickedMenus as $menu) {
                        $qty = random_int(1, 2);
                        $unit = (float) $menu->price;

                        // Apply 15% discount to Latte (matching seeded ProductDiscount).
                        $itemDiscount = 0.0;
                        if ($menu->name === 'Latte') {
                            $itemDiscount = $unit * 0.15;
                        }

                        $condimentPriceTotal = 0.0;
                        $condimentSelections = [];
                        foreach ($menu->condimentGroups as $group) {
                            if ($group->options->isEmpty()) continue;
                            // Single-select: always pick one. Multi-select: 30% chance per option.
                            if ($group->type === 'single_select') {
                                $opt = $group->options->random();
                                $condimentSelections[] = $opt;
                                $condimentPriceTotal += (float) $opt->additional_price;
                            } else {
                                foreach ($group->options as $opt) {
                                    if (random_int(0, 100) < 30) {
                                        $condimentSelections[] = $opt;
                                        $condimentPriceTotal += (float) $opt->additional_price;
                                    }
                                }
                            }
                        }

                        $lineTotal = ($unit - $itemDiscount) * $qty + $condimentPriceTotal * $qty;
                        $subtotal += $lineTotal;
                        $discount += $itemDiscount * $qty;

                        $itemsBuffer[] = [
                            'menu_id' => $menu->id,
                            'quantity' => $qty,
                            'unit_price' => $unit,
                            'applied_discount' => $itemDiscount,
                            'condiments' => $condimentSelections,
                        ];
                    }

                    // 18% of orders use a promo code (paid orders only).
                    $promoCode = null;
                    $promoDiscount = 0.0;
                    if ($paymentStatus === 'paid' && $statusVal !== 'cancelled' && random_int(0, 100) < 18) {
                        $promoCode = 'WELCOME10';
                        $promoDiscount = $subtotal * 0.10;
                        $discount += $promoDiscount;
                    }

                    $total = max(0, $subtotal - $promoDiscount);

                    $dailyQueue++;
                    $order = Order::create([
                        'user_id' => $isWalkin ? null : $customers->random()->id,
                        'cashier_id' => $isWalkin ? optional($kasir)->id : null,
                        'order_type' => $orderType,
                        'status' => $statusVal,
                        'payment_method' => $paymentMethod,
                        'payment_status' => $paymentStatus,
                        'payment_channel' => $paymentMethod === 'virtual_account' ? ['bca', 'bni', 'bri', 'mandiri'][array_rand(['bca','bni','bri','mandiri'])] : null,
                        'queue_number' => $dailyQueue,
                        'pickup_time' => ! $isWalkin ? $createdAt->copy()->addMinutes(random_int(15, 60)) : null,
                        'promo_code' => $promoCode,
                        'subtotal' => $subtotal,
                        'discount_amount' => $discount,
                        'total' => $total,
                        'midtrans_order_id' => $paymentMethod !== 'cash' ? 'COFFLOW-'.$orderId.'-'.$createdAt->timestamp : null,
                        'notes' => null,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]);

                    foreach ($itemsBuffer as $row) {
                        $item = OrderItem::create([
                            'order_id' => $order->id,
                            'menu_id' => $row['menu_id'],
                            'quantity' => $row['quantity'],
                            'unit_price' => $row['unit_price'],
                            'applied_discount' => $row['applied_discount'],
                            'notes' => null,
                            'created_at' => $createdAt,
                            'updated_at' => $createdAt,
                        ]);

                        foreach ($row['condiments'] as $opt) {
                            OrderItemCondiment::create([
                                'order_item_id' => $item->id,
                                'condiment_option_id' => $opt->id,
                                'option_name' => $opt->name,
                                'additional_price' => $opt->additional_price,
                            ]);
                        }
                    }

                    $orderId++;
                }
            }
        });
    }

    private function seedOpnames(?User $admin, ?User $kasir): void
    {
        if (! $admin) return;

        // Already-approved opname yesterday.
        if (! StockOpname::where('shift_label', 'closing')->whereDate('shift_date', Carbon::yesterday())->exists()) {
            $opname = StockOpname::create([
                'shift_date' => Carbon::yesterday()->toDateString(),
                'shift_label' => 'closing',
                'performed_by' => $kasir?->id ?? $admin->id,
                'reviewed_by' => $admin->id,
                'status' => StockOpname::STATUS_APPROVED,
                'notes' => 'Tutup toko shift sore.',
                'review_notes' => 'OK, selisih kecil masih wajar.',
                'reviewed_at' => Carbon::yesterday()->setTime(22, 30),
            ]);

            $ingredients = Ingredient::orderBy('id')->get();
            foreach ($ingredients as $ing) {
                $sys = (float) $ing->current_stock;
                // Small random variance (-3% .. +1%).
                $physical = max(0, round($sys * (1 + (random_int(-30, 10) / 1000)), 3));
                StockOpnameItem::create([
                    'stock_opname_id' => $opname->id,
                    'ingredient_id' => $ing->id,
                    'system_stock' => $sys,
                    'physical_stock' => $physical,
                    'variance' => $physical - $sys,
                    'variance_reason' => ($physical - $sys) < 0 ? 'spillage' : null,
                    'notes' => null,
                ]);
            }
        }

        // Pending opname today, waiting for admin review.
        if (! StockOpname::whereDate('shift_date', Carbon::today())->where('status', StockOpname::STATUS_PENDING)->exists()) {
            $opname = StockOpname::create([
                'shift_date' => Carbon::today()->toDateString(),
                'shift_label' => 'morning',
                'performed_by' => $kasir?->id ?? $admin->id,
                'status' => StockOpname::STATUS_PENDING,
                'notes' => 'Cek pagi sebelum buka.',
            ]);

            $ingredients = Ingredient::orderBy('id')->limit(4)->get();
            foreach ($ingredients as $ing) {
                $sys = (float) $ing->current_stock;
                $physical = max(0, round($sys * (1 + (random_int(-50, 20) / 1000)), 3));
                StockOpnameItem::create([
                    'stock_opname_id' => $opname->id,
                    'ingredient_id' => $ing->id,
                    'system_stock' => $sys,
                    'physical_stock' => $physical,
                    'variance' => $physical - $sys,
                    'variance_reason' => $physical < $sys ? 'measurement_error' : null,
                    'notes' => null,
                ]);
            }
        }
    }

    private function weightedPick(array $items): array
    {
        $total = array_sum(array_map(fn ($i) => $i[2], $items));
        $r = random_int(1, $total);
        foreach ($items as $item) {
            $r -= $item[2];
            if ($r <= 0) return $item;
        }
        return $items[0];
    }
}
