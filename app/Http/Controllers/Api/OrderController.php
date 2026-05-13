<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CondimentOption;
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

        // 1. Validate stock
        $this->stockService->validateStock(array_map(
            fn ($i) => ['menu_id' => $i['menu_id'], 'quantity' => $i['quantity']],
            $data['items']
        ));

        // 2. Calculate total
        $calc = $this->discountService->calculateOrderTotal(
            $data['items'],
            $data['promo_code'] ?? null
        );

        // 3. Create order in transaction
        $order = DB::transaction(function () use ($request, $data, $calc) {
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

            return $order;
        });

        // 4. Deduct stock now that order_items exist; emit critical alerts
        $criticals = $this->stockService->deductForOrder($order->fresh('items'));
        foreach ($criticals as $ingredient) {
            $this->fcmService->sendStockCriticalAlert($ingredient);
        }

        // 5. Payment gateway
        if ($order->payment_method === 'qris') {
            $qris = $this->midtransService->createQris($order);
            $order->update([
                'midtrans_order_id' => $qris['midtrans_order_id'],
                'qr_code_url' => $qris['qr_code_url'],
                'payment_expired_at' => $qris['expired_at'],
            ]);
        } elseif ($order->payment_method === 'virtual_account') {
            $va = $this->midtransService->createVirtualAccount($order, $order->payment_channel);
            $order->update([
                'midtrans_order_id' => $va['midtrans_order_id'],
                'va_number' => $va['va_number'],
                'payment_expired_at' => $va['expired_at'],
            ]);
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

        $order->update(['status' => 'cancelled']);

        return $this->success($order->fresh(), 'Order dibatalkan');
    }
}
