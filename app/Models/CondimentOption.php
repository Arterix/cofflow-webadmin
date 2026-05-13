<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CondimentOption extends Model
{
    protected $fillable = ['condiment_group_id', 'name', 'additional_price', 'sort_order'];

    protected function casts(): array
    {
        return [
            'additional_price' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(CondimentGroup::class, 'condiment_group_id');
    }
}
