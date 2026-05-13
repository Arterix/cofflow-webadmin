<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItemCondiment extends Model
{
    protected $fillable = [
        'order_item_id', 'condiment_option_id',
        'option_name', 'additional_price',
    ];

    protected function casts(): array
    {
        return ['additional_price' => 'decimal:2'];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function condimentOption(): BelongsTo
    {
        return $this->belongsTo(CondimentOption::class);
    }
}
