<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class EventDiscount extends Model
{
    protected $fillable = [
        'name', 'type', 'value',
        'start_date', 'end_date', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function menus(): BelongsToMany
    {
        return $this->belongsToMany(
            Menu::class,
            'event_discount_menus',
            'event_discount_id',
            'menu_id'
        )->withTimestamps();
    }
}
