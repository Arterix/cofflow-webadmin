<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockOpnameItem extends Model
{
    public const REASONS = [
        'spillage' => 'Tumpah',
        'waste' => 'Pemborosan / kadaluarsa',
        'unrecorded_use' => 'Pemakaian tak tercatat',
        'theft' => 'Hilang / dugaan pencurian',
        'measurement_error' => 'Kesalahan pengukuran',
        'other' => 'Lainnya',
    ];

    protected $fillable = [
        'stock_opname_id',
        'ingredient_id',
        'system_stock',
        'physical_stock',
        'variance',
        'variance_reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'system_stock' => 'decimal:3',
            'physical_stock' => 'decimal:3',
            'variance' => 'decimal:3',
        ];
    }

    public function opname(): BelongsTo
    {
        return $this->belongsTo(StockOpname::class, 'stock_opname_id');
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }
}
