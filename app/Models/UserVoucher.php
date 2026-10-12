<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserVoucher extends Model
{
    protected $fillable = [
        'user_id', 'point_reward_id', 'code', 'name', 'type', 'value',
        'max_discount', 'min_order', 'points_spent', 'status',
        'expires_at', 'used_at', 'order_id',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'max_discount' => 'decimal:2',
            'min_order' => 'decimal:2',
            'points_spent' => 'integer',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reward(): BelongsTo
    {
        return $this->belongsTo(PointReward::class, 'point_reward_id');
    }

    public function isUsable(): bool
    {
        return $this->status === 'available'
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
