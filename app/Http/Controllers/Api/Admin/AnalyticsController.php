<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\OrderItem;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    use ApiResponse;

    public function summary(): JsonResponse
    {
        $today = Carbon::today();

        $totalOrderToday = Order::whereDate('created_at', $today)->count();
        $revenueToday = (float) Order::whereDate('created_at', $today)
            ->where('payment_status', 'paid')
            ->sum('total');
        $activeOrders = Order::whereIn('status', ['pending', 'processing', 'ready'])->count();
        $criticalIngredients = Ingredient::whereColumn('current_stock', '<', 'minimum_stock')->count();

        return $this->success([
            'total_order_today' => $totalOrderToday,
            'revenue_today' => $revenueToday,
            'active_orders' => $activeOrders,
            'critical_ingredients' => $criticalIngredients,
        ]);
    }

    public function weeklySales(): JsonResponse
    {
        $start = Carbon::today()->subDays(6);
        $rows = Order::selectRaw('DATE(created_at) as day, SUM(total) as revenue')
            ->where('payment_status', 'paid')
            ->whereDate('created_at', '>=', $start)
            ->groupBy('day')
            ->pluck('revenue', 'day');

        $result = [];
        for ($i = 0; $i < 7; $i++) {
            $d = $start->copy()->addDays($i)->toDateString();
            $result[] = [
                'date' => $d,
                'revenue' => (float) ($rows[$d] ?? 0),
            ];
        }

        return $this->success($result);
    }

    public function topMenus(Request $request): JsonResponse
    {
        $limit = (int) ($request->query('limit', 5));
        $since = Carbon::today()->subDays(30);

        $rows = OrderItem::selectRaw('menu_id, SUM(quantity) as total_qty')
            ->whereHas('order', function ($q) use ($since) {
                $q->whereDate('created_at', '>=', $since)
                    ->where('status', '!=', 'cancelled');
            })
            ->groupBy('menu_id')
            ->orderByDesc('total_qty')
            ->limit($limit)
            ->with('menu:id,name,price')
            ->get();

        return $this->success($rows);
    }

    public function peakHours(): JsonResponse
    {
        $since = Carbon::today()->subDays(30);

        $rows = Order::selectRaw("strftime('%H', created_at) as hour, COUNT(*) as count")
            ->whereDate('created_at', '>=', $since)
            ->groupBy('hour')
            ->pluck('count', 'hour');

        // Use a portable approach for non-SQLite by falling back to PHP grouping if the raw query fails.
        if ($rows->isEmpty()) {
            $rows = Order::whereDate('created_at', '>=', $since)
                ->get(['created_at'])
                ->groupBy(fn ($o) => $o->created_at->format('H'))
                ->map->count();
        }

        $result = [];
        for ($h = 7; $h <= 22; $h++) {
            $key = str_pad((string) $h, 2, '0', STR_PAD_LEFT);
            $result[] = [
                'hour' => $key,
                'count' => (int) ($rows[$key] ?? 0),
            ];
        }

        return $this->success($result);
    }

    public function report(Request $request): JsonResponse
    {
        $data = $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after_or_equal:start'],
        ]);

        $start = Carbon::parse($data['start'])->startOfDay();
        $end = Carbon::parse($data['end'])->endOfDay();

        $base = Order::whereBetween('created_at', [$start, $end])
            ->where('status', '!=', 'cancelled');

        $totalOrder = (clone $base)->count();
        $totalRevenue = (float) (clone $base)->where('payment_status', 'paid')->sum('total');

        $paymentBreakdown = (clone $base)
            ->selectRaw('payment_method, COUNT(*) as count, SUM(total) as revenue')
            ->groupBy('payment_method')
            ->get();

        $topMenus = OrderItem::selectRaw('menu_id, SUM(quantity) as total_qty, SUM(quantity * unit_price) as revenue')
            ->whereHas('order', function ($q) use ($start, $end) {
                $q->whereBetween('created_at', [$start, $end])
                    ->where('status', '!=', 'cancelled');
            })
            ->groupBy('menu_id')
            ->orderByDesc('total_qty')
            ->limit(10)
            ->with('menu:id,name')
            ->get();

        return $this->success([
            'period' => ['start' => $data['start'], 'end' => $data['end']],
            'total_order' => $totalOrder,
            'total_revenue' => $totalRevenue,
            'payment_breakdown' => $paymentBreakdown,
            'top_menus' => $topMenus,
        ]);
    }
}
