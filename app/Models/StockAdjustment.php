<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustment extends Model
{
    protected $fillable = [
        'ingredient_id',
        'adjusted_by',
        'type',
        'before',
        'after',
        'delta',
        'reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'before' => 'decimal:3',
            'after'  => 'decimal:3',
        ];
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function adjustedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }
}
