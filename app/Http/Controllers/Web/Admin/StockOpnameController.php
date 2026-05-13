<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StockOpnameController extends Controller
{
    public function __construct(private readonly StockService $stockService) {}

    public function index(Request $request): View
    {
        $status = $request->query('status');

        $query = StockOpname::with(['performedBy', 'reviewedBy'])
            ->withCount('items')
            ->latest('shift_date')
            ->latest('id');

        if ($status && in_array($status, [
            StockOpname::STATUS_PENDING,
            StockOpname::STATUS_APPROVED,
            StockOpname::STATUS_REJECTED,
        ], true)) {
            $query->where('status', $status);
        }

        $opnames = $query->paginate(20)->withQueryString();

        $summary = [
            'pending' => StockOpname::where('status', StockOpname::STATUS_PENDING)->count(),
            'approved_today' => StockOpname::where('status', StockOpname::STATUS_APPROVED)
                ->whereDate('reviewed_at', today())
                ->count(),
            'rejected_today' => StockOpname::where('status', StockOpname::STATUS_REJECTED)
                ->whereDate('reviewed_at', today())
                ->count(),
        ];

        return view('admin.opnames.index', compact('opnames', 'summary', 'status'));
    }

    public function create(): View
    {
        $ingredients = Ingredient::orderBy('name')->get();
        return view('admin.opnames.create', compact('ingredients'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'shift_date' => ['required', 'date'],
            'shift_label' => ['nullable', 'in:morning,evening,closing,adhoc'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.ingredient_id' => ['required', 'exists:ingredients,id'],
            'items.*.physical_stock' => ['required', 'numeric', 'min:0'],
            'items.*.variance_reason' => ['nullable', 'in:'.implode(',', array_keys(StockOpnameItem::REASONS))],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        $opname = DB::transaction(function () use ($data) {
            $opname = StockOpname::create([
                'shift_date' => $data['shift_date'],
                'shift_label' => $data['shift_label'] ?? 'adhoc',
                'performed_by' => auth()->id(),
                'status' => StockOpname::STATUS_PENDING,
                'notes' => $data['notes'] ?? null,
            ]);

            $ingredients = Ingredient::whereIn('id', collect($data['items'])->pluck('ingredient_id'))->get()->keyBy('id');

            foreach ($data['items'] as $row) {
                $ingredient = $ingredients[$row['ingredient_id']] ?? null;
                if (! $ingredient) {
                    continue;
                }
                $system = (float) $ingredient->current_stock;
                $physical = (float) $row['physical_stock'];
                $opname->items()->create([
                    'ingredient_id' => $ingredient->id,
                    'system_stock' => $system,
                    'physical_stock' => $physical,
                    'variance' => $physical - $system,
                    'variance_reason' => $row['variance_reason'] ?? null,
                    'notes' => $row['notes'] ?? null,
                ]);
            }

            return $opname;
        });

        return redirect()->route('admin.opnames.show', $opname)->with('status', 'Opname dibuat. Menunggu review.');
    }

    public function show(StockOpname $opname): View
    {
        $opname->load(['performedBy', 'reviewedBy', 'items.ingredient']);

        $totalAbsVariance = $opname->items->sum(fn ($i) => abs((float) $i->variance));
        $hasVariance = $opname->items->contains(fn ($i) => (float) $i->variance !== 0.0);

        return view('admin.opnames.show', [
            'opname' => $opname,
            'totalAbsVariance' => $totalAbsVariance,
            'hasVariance' => $hasVariance,
            'reasons' => StockOpnameItem::REASONS,
        ]);
    }

    public function approve(Request $request, StockOpname $opname): RedirectResponse
    {
        if (! $opname->isPending()) {
            return back()->withErrors(['status' => 'Opname sudah direview']);
        }

        $data = $request->validate(['review_notes' => ['nullable', 'string', 'max:1000']]);

        $this->stockService->applyOpnameAdjustment($opname);

        $opname->update([
            'status' => StockOpname::STATUS_APPROVED,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_notes' => $data['review_notes'] ?? null,
        ]);

        return back()->with('status', 'Opname disetujui. Stok bahan diperbarui.');
    }

    public function reject(Request $request, StockOpname $opname): RedirectResponse
    {
        if (! $opname->isPending()) {
            return back()->withErrors(['status' => 'Opname sudah direview']);
        }

        $data = $request->validate(['review_notes' => ['required', 'string', 'max:1000']]);

        $opname->update([
            'status' => StockOpname::STATUS_REJECTED,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_notes' => $data['review_notes'],
        ]);

        return back()->with('status', 'Opname ditolak. Stok tidak berubah.');
    }
}
