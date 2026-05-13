<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderMonitorController extends Controller
{
    public function index(Request $request): View
    {
        $orders = Order::with(['user', 'items.menu'])
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('type'), fn ($q, $t) => $q->where('order_type', $t))
            ->when($request->query('date'), fn ($q, $d) => $q->whereDate('created_at', $d))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.orders.index', compact('orders'));
    }

    public function show(Order $order): View
    {
        $order->load(['user', 'cashier', 'items.menu', 'items.condiments', 'statusLogs.changedBy']);
        return view('admin.orders.show', compact('order'));
    }
}
