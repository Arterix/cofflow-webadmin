<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $today = Carbon::today();

        $totalOrderToday = Order::whereDate('created_at', $today)->count();
        $revenueToday = (float) Order::whereDate('created_at', $today)
            ->where('payment_status', 'paid')
            ->sum('total');
        $activeOrders = Order::whereIn('status', ['pending', 'processing', 'ready'])->count();
        $criticalIngredients = Ingredient::whereColumn('current_stock', '<', 'minimum_stock')->count();

        // Weekly sales (7 days)
        $start = $today->copy()->subDays(6);
        $sales = Order::selectRaw('DATE(created_at) as day, SUM(total) as revenue')
            ->where('payment_status', 'paid')
            ->whereDate('created_at', '>=', $start)
            ->groupBy('day')
            ->pluck('revenue', 'day');

        $weekly = [];
        $weekDates = [];
        for ($i = 0; $i < 7; $i++) {
            $d = $start->copy()->addDays($i);
            $iso = $d->toDateString();
            $weekDates[] = $iso;
            $weekly[] = [
                'date' => $iso,
                'label' => $d->isoFormat('dd D/M'),
                'revenue' => (float) ($sales[$iso] ?? 0),
                'is_today' => $d->isToday(),
            ];
        }

        $maxRevenue = max(array_column($weekly, 'revenue')) ?: 1;

        $topMenus = OrderItem::selectRaw('menu_id, SUM(quantity) as total_qty')
            ->whereHas('order', fn ($q) => $q->whereDate('created_at', '>=', $today->copy()->subDays(30))
                ->where('status', '!=', 'cancelled'))
            ->groupBy('menu_id')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->with('menu:id,name,price')
            ->get();

        // ───── Selected-day panel ─────
        $selectedDate = $request->query('date');
        if (! $selectedDate || ! in_array($selectedDate, $weekDates, true)) {
            $selectedDate = $today->toDateString();
        }
        $selected = $this->statsForDate(Carbon::parse($selectedDate));

        return view('admin.dashboard', compact(
            'totalOrderToday', 'revenueToday', 'activeOrders', 'criticalIngredients',
            'weekly', 'maxRevenue', 'topMenus',
            'selectedDate', 'selected'
        ));
    }

    /**
     * Compute the 6 selected-day stats. All scoped to whereDate(created_at, $date).
     * Revenue/discount sums exclude cancelled orders. Top-menus exclude cancelled.
     */
    private function statsForDate(Carbon $date): array
    {
        $base = Order::whereDate('created_at', $date);

        // Cancellation counts — total includes cancelled, cancelled is subset.
        $totalOrders = (clone $base)->count();
        $cancelledCount = (clone $base)->where('status', 'cancelled')->count();
        $cancellationRate = $totalOrders > 0 ? round($cancelledCount / $totalOrders * 100, 1) : 0.0;

        // Money — non-cancelled only.
        $moneyQuery = (clone $base)->where('status', '!=', 'cancelled');
        $subtotal = (float) (clone $moneyQuery)->sum('subtotal');
        $totalDiscount = (float) (clone $moneyQuery)->sum('discount_amount');
        $netRevenue = (float) (clone $moneyQuery)->sum('total');

        // Items sold — total cups/units that day (non-cancelled).
        $totalItemsSold = (int) OrderItem::whereHas('order', function ($q) use ($date) {
            $q->whereDate('created_at', $date)->where('status', '!=', 'cancelled');
        })->sum('quantity');

        // Top 3 menus that day.
        $topMenusToday = OrderItem::selectRaw('menu_id, SUM(quantity) as total_qty, SUM(quantity * unit_price) as total_rev')
            ->whereHas('order', function ($q) use ($date) {
                $q->whereDate('created_at', $date)->where('status', '!=', 'cancelled');
            })
            ->groupBy('menu_id')
            ->orderByDesc('total_qty')
            ->limit(3)
            ->with('menu:id,name')
            ->get();

        // Peak hour — group by hour, take top.
        // Use DATE_PART for Postgres, strftime for SQLite fallback handled by Carbon-side dataset.
        $hourly = (clone $base)
            ->where('status', '!=', 'cancelled')
            ->selectRaw(DB::connection()->getDriverName() === 'sqlite'
                ? "CAST(strftime('%H', created_at) AS INTEGER) as hour, COUNT(*) as cnt"
                : 'EXTRACT(HOUR FROM created_at)::int as hour, COUNT(*) as cnt')
            ->groupBy('hour')
            ->orderByDesc('cnt')
            ->get();

        $peakHour = $hourly->first();

        return [
            'total_items_sold' => $totalItemsSold,
            'top_menus' => $topMenusToday,
            'peak_hour' => $peakHour ? [
                'hour' => (int) $peakHour->hour,
                'count' => (int) $peakHour->cnt,
            ] : null,
            'cancelled_count' => $cancelledCount,
            'total_orders' => $totalOrders,
            'cancellation_rate' => $cancellationRate,
            'net_revenue' => $netRevenue,
            'subtotal' => $subtotal,
            'total_discount' => $totalDiscount,
        ];
    }
}
