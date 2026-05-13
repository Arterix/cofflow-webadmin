<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $start = $request->query('start') ? Carbon::parse($request->query('start'))->startOfDay() : Carbon::today()->subDays(7)->startOfDay();
        $end = $request->query('end') ? Carbon::parse($request->query('end'))->endOfDay() : Carbon::today()->endOfDay();

        $base = Order::whereBetween('created_at', [$start, $end])->where('status', '!=', 'cancelled');

        $totalOrder = (clone $base)->count();
        $totalRevenue = (float) (clone $base)->where('payment_status', 'paid')->sum('total');

        $payment = (clone $base)->selectRaw('payment_method, COUNT(*) as count, SUM(total) as revenue')
            ->groupBy('payment_method')->get();

        $topMenus = OrderItem::selectRaw('menu_id, SUM(quantity) as total_qty, SUM(quantity * unit_price) as revenue')
            ->whereHas('order', fn ($q) => $q->whereBetween('created_at', [$start, $end])->where('status', '!=', 'cancelled'))
            ->groupBy('menu_id')->orderByDesc('total_qty')->limit(10)
            ->with('menu:id,name')->get();

        return view('admin.report.index', [
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'totalOrder' => $totalOrder,
            'totalRevenue' => $totalRevenue,
            'payment' => $payment,
            'topMenus' => $topMenus,
        ]);
    }
}
