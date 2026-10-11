<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CondimentOption;
use App\Models\Menu;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemCondiment;
use App\Services\DiscountService;
use App\Services\FcmService;
use App\Services\MidtransService;
use App\Services\QueueService;
use App\Services\StockService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected StockService $stockService,
        protected DiscountService $discountService,
        protected QueueService $queueService,
        protected MidtransService $midtransService,
        protected FcmService $fcmService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $orders = Order::with(['items.menu', 'items.condiments'])
            ->where('user_id', $request->user()->id)
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_at')
            ->get();

        return $this->success($orders);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $order = Order::with(['items.menu', 'items.condiments', 'statusLogs'])
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return $this->success($order);
    }

    public function paymentStatus(Request $request, int $id): JsonResponse
    {
        $order = Order::where('user_id', $request->user()->id)->findOrFail($id);

        return $this->success([
            'order_id' => $order->id,
            'payment_status' => $order->payment_status,
            'status' => $order->status,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_type' => ['required', 'in:preorder,walkin'],
            'payment_method' => ['required', 'in:cash,qris,virtual_account'],
            'payment_channel' => ['nullable', 'in:bca,bni,bri,mandiri'],
            'pickup_time' => ['nullable', 'date'],
            'promo_code' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_id' => ['required', 'integer', 'exists:menus,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.condiment_option_ids' => ['nullable', 'array'],
            'items.*.condiment_option_ids.*' => ['integer', 'exists:condiment_options,id'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        if ($data['payment_method'] === 'virtual_account' && empty($data['payment_channel'])) {
            return $this->error('payment_channel wajib diisi untuk virtual_account', 422);
        }

        // walkin order type hanya bisa dibuat oleh kasir atau admin (bukan customer)
        if ($data['order_type'] === 'walkin' && $request->user()->isCustomer()) {
            return $this->error('Customer tidak dapat membuat order walk-in', 403);
        }

        // 1. Validate condiment options (options belong to menu groups, max 1 for single_select)
        $this->validateItemCondiments($data['items']);

        // 2. Calculate total (outside transaction — read-only, no locking needed)
        $calc = $this->discountService->calculateOrderTotal(
            $data['items'],
            $data['promo_code'] ?? null
        );

        // 3. Create order + deduct stock in a single atomic transaction.
        //    validateAndDeductForOrder() locks ingredients with SELECT FOR UPDATE,
        //    re-validates stock from locked data, then deducts — all inside this
        //    transaction. If stock is insufficient the transaction rolls back and
        //    a ValidationException bubbles up to the client.
        $criticals = [];
        $order = DB::transaction(function () use ($request, $data, $calc, &$criticals) {
            $order = Order::create([
                'user_id' => $request->user()->id,
                'cashier_id' => $request->user()->isKasir() || $request->user()->isAdmin()
                    ? $request->user()->id
                    : null,
                'order_type' => $data['order_type'],
                'status' => 'pending',
                'payment_method' => $data['payment_method'],
                'payment_status' => $data['payment_method'] === 'cash' ? 'paid' : 'unpaid',
                'payment_channel' => $data['payment_channel'] ?? null,
                'queue_number' => $this->queueService->generateQueueNumber(),
                'pickup_time' => $data['pickup_time'] ?? null,
                'promo_code' => $data['promo_code'] ?? null,
                'subtotal' => $calc['subtotal'],
                'discount_amount' => $calc['discount_amount'],
                'total' => $calc['total'],
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $idx => $item) {
                $breakdown = $calc['item_breakdown'][$idx];
                $orderItem = OrderItem::create([
                    'order_id' => $order->id,
                    'menu_id' => $item['menu_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $breakdown['unit_price'],
                    'applied_discount' => $breakdown['applied_discount_per_unit'] * $item['quantity'],
                    'notes' => $item['notes'] ?? null,
                ]);

                if (! empty($item['condiment_option_ids'])) {
                    $options = CondimentOption::whereIn('id', $item['condiment_option_ids'])->get();
                    foreach ($options as $opt) {
                        OrderItemCondiment::create([
                            'order_item_id' => $orderItem->id,
                            'condiment_option_id' => $opt->id,
                            'option_name' => $opt->name,
                            'additional_price' => $opt->additional_price,
                        ]);
                    }
                }
            }

            // Increment promo usage
            if ($calc['promo']) {
                $calc['promo']->increment('used_count');
            }

            // Validate + deduct stock atomically (inside this transaction).
            // Locks ingredients, re-checks stock, throws if insufficient.
            $criticals = $this->stockService->validateAndDeductForOrder($order->load('items.condiments'));

            return $order;
        });

        // 3. Send critical-stock FCM alerts after the transaction commits
        foreach ($criticals as $ingredient) {
            $this->fcmService->sendStockCriticalAlert($ingredient);
        }

        // 4. Payment gateway — jika Midtrans gagal, order sudah dibuat & stok dipotong.
        //    Tandai order sebagai 'payment_failed' agar kasir bisa menangani manual.
        if ($order->payment_method === 'qris') {
            try {
                $qris = $this->midtransService->createQris($order);
                $order->update([
                    'midtrans_order_id' => $qris['midtrans_order_id'],
                    'qr_code_url'       => $qris['qr_code_url'],
                    'payment_expired_at' => $qris['expired_at'],
                ]);
            } catch (\RuntimeException $e) {
                // Rollback order + stok karena QRIS gagal dibuat
                \Illuminate\Support\Facades\DB::transaction(function () use ($order) {
                    $this->stockService->restoreForOrder($order->load('items.condiments'));
                    $order->update(['status' => 'cancelled', 'payment_status' => 'unpaid']);
                });
                return $this->error('Gagal membuat QRIS: '.$e->getMessage(), 502);
            }
        } elseif ($order->payment_method === 'virtual_account') {
            try {
                $va = $this->midtransService->createVirtualAccount($order, $order->payment_channel);
                $order->update([
                    'midtrans_order_id'  => $va['midtrans_order_id'],
                    'va_number'          => $va['va_number'],
                    'payment_expired_at' => $va['expired_at'],
                ]);
            } catch (\RuntimeException $e) {
                \Illuminate\Support\Facades\DB::transaction(function () use ($order) {
                    $this->stockService->restoreForOrder($order->load('items.condiments'));
                    $order->update(['status' => 'cancelled', 'payment_status' => 'unpaid']);
                });
                return $this->error('Gagal membuat Virtual Account: '.$e->getMessage(), 502);
            }
        }

        return $this->success(
            $order->fresh(['items.menu', 'items.condiments']),
            'Order berhasil dibuat',
            201
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $order = Order::where('user_id', $request->user()->id)->findOrFail($id);

        if ($order->status !== 'pending') {
            return $this->error('Hanya order dengan status pending yang dapat dibatalkan', 422);
        }

        DB::transaction(function () use ($order) {
            // Restore stock only if it was already deducted (cash = always deducted;
            // qris/va = deducted at order creation since we deduct inside the tx now)
            $this->stockService->restoreForOrder($order->load('items.condiments'));
            $order->update(['status' => 'cancelled', 'payment_status' => 'refunded']);
        });

        return $this->success($order->fresh(), 'Order dibatalkan');
    }

    /**
     * Validate that submitted condiment option IDs belong to the corresponding menu
     * and do not violate single_select constraints.
     *
     * @param  array<int, array{menu_id: int, condiment_option_ids?: array<int>}>  $items
     * @throws ValidationException
     */
    protected function validateItemCondiments(array $items): void
    {
        $menuIds = array_unique(array_column($items, 'menu_id'));
        $menus = Menu::with('condimentGroups.options')
            ->whereIn('id', $menuIds)
            ->get()
            ->keyBy('id');

        foreach ($items as $idx => $item) {
            $menu = $menus->get($item['menu_id']);
            if (! $menu) {
                continue;
            }

            $optionIds = $item['condiment_option_ids'] ?? [];
            if (empty($optionIds)) {
                continue;
            }

            $menuGroups = $menu->condimentGroups;
            $allValidOptionIds = $menuGroups
                ->flatMap(fn ($g) => $g->options->pluck('id'))
                ->all();

            // 1. Verify every option ID belongs to a condiment group of this menu
            foreach ($optionIds as $optId) {
                if (! in_array($optId, $allValidOptionIds, false)) {
                    throw ValidationException::withMessages([
                        "items.{$idx}.condiment_option_ids" => [
                            "Opsi kondimen ID {$optId} tidak valid untuk menu {$menu->name}",
                        ],
                    ]);
                }
            }

            // 2. Verify single_select groups do not have multiple options selected
            foreach ($menuGroups as $group) {
                if ($group->type !== 'single_select') {
                    continue;
                }
                $groupOptionIds = $group->options->pluck('id')->all();
                $chosenInGroup = array_intersect($optionIds, $groupOptionIds);
                if (count($chosenInGroup) > 1) {
                    throw ValidationException::withMessages([
                        "items.{$idx}.condiment_option_ids" => [
                            "Grup kondimen '{$group->name}' hanya boleh dipilih maksimal 1 opsi",
                        ],
                    ]);
                }
            }
        }
    }
}
