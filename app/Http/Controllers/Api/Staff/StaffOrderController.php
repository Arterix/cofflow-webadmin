<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffOrderController extends Controller
{
    use ApiResponse;

    private array $validTransitions = [
        'pending' => ['processing', 'cancelled'],
        'processing' => ['ready', 'cancelled'],
        'ready' => ['completed'],
    ];

    public function index(Request $request): JsonResponse
    {
        $orders = Order::with(['user', 'items.menu', 'items.condiments'])
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('type'), fn ($q, $t) => $q->where('order_type', $t))
            ->when($request->query('date'), fn ($q, $d) => $q->whereDate('created_at', $d))
            ->orderByDesc('created_at')
            ->get();

        return $this->success($orders);
    }

    public function show(int $id): JsonResponse
    {
        $order = Order::with([
            'user', 'cashier', 'items.menu', 'items.condiments', 'statusLogs.changedBy',
        ])->findOrFail($id);

        return $this->success($order);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:pending,processing,ready,completed,cancelled'],
            'notes' => ['nullable', 'string'],
        ]);

        $order = Order::findOrFail($id);
        $allowed = $this->validTransitions[$order->status] ?? [];

        if (! in_array($data['status'], $allowed, true)) {
            return $this->error(
                "Transisi status dari {$order->status} ke {$data['status']} tidak diperbolehkan",
                422
            );
        }

        $order->update(['status' => $data['status']]);

        OrderStatusLog::create([
            'order_id' => $order->id,
            'status' => $data['status'],
            'changed_by' => $request->user()->id,
            'notes' => $data['notes'] ?? null,
        ]);

        return $this->success($order->fresh(), 'Status diperbarui');
    }
}
