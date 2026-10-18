<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoyaltySetting;
use App\Models\Order;
use App\Models\PointTransaction;
use App\Models\User;
use App\Services\LoyaltyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MembershipController extends Controller
{
    public function __construct(protected LoyaltyService $loyalty) {}

    public function index(Request $request): View
    {
        $settings = LoyaltySetting::current();
        $tab = $request->query('tab', 'members');

        $members = User::where('role', 'customer')
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")))
            ->withSum(['orders as pending_points' => fn ($q) => $this->pendingScope($q)], 'points_base')
            ->orderByDesc('lifetime_points')
            ->orderBy('name')
            ->get();

        $transactions = PointTransaction::with(['user', 'creator'])
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        // Poin dari pesanan app yang sudah dibuat tapi belum selesai: masuk saldo saat completed.
        $pendingOrders = $this->pendingScope(Order::query())
            ->with('user:id,name')
            ->orderByDesc('id')
            ->limit(100)
            ->get(['id', 'user_id', 'status', 'points_base', 'created_at']);

        $stats = [
            'members' => $members->count(),
            'pending' => (int) $this->pendingScope(Order::query())->sum('points_base'),
            'outstanding' => (int) User::where('role', 'customer')->sum('points_balance'),
            'earned' => (int) PointTransaction::where('type', 'earn')->sum('points'),
            'redeemed' => (int) -PointTransaction::where('type', 'redeem')->sum('points'),
        ];

        return view('admin.membership.index', compact('settings', 'tab', 'members', 'transactions', 'pendingOrders', 'stats'));
    }

    private function pendingScope($query)
    {
        return $query->where('order_type', 'preorder')
            ->where('points_base', '>', 0)
            ->whereNull('points_awarded_at')
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('user_id');
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'min_item_price' => ['required', 'integer', 'min:0', 'max:100000000'],
            'silver_threshold' => ['required', 'integer', 'min:1'],
            'gold_threshold' => ['required', 'integer', 'gt:silver_threshold'],
            'voucher_valid_days' => ['required', 'integer', 'min:0', 'max:3650'],
        ]);
        $data['is_enabled'] = $request->boolean('is_enabled');

        LoyaltySetting::current()->update($data);

        return redirect()->route('admin.membership.index', ['tab' => 'settings'])->with('status', 'Pengaturan poin disimpan');
    }

    public function adjust(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->isCustomer(), 404);

        $data = $request->validate([
            'points' => ['required', 'integer', 'between:-1000000,1000000'],
            'reason' => ['required', 'string', 'max:200'],
        ]);

        $this->loyalty->adjust($user, (int) $data['points'], 'Penyesuaian admin: '.$data['reason'], $request->user());

        return back()->with('status', "Poin {$user->name} disesuaikan");
    }
}
