<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PointReward extends Model
{
    public const TIERS = ['bronze', 'silver', 'gold'];

    protected $fillable = [
        'name', 'description', 'type', 'value', 'max_discount', 'min_order',
        'points_cost', 'min_tier', 'quota', 'redeemed_count', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'max_discount' => 'decimal:2',
            'min_order' => 'decimal:2',
            'points_cost' => 'integer',
            'quota' => 'integer',
            'redeemed_count' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function isSoldOut(): bool
    {
        return $this->quota !== null && $this->redeemed_count >= $this->quota;
    }

    public static function tierRank(string $tier): int
    {
        return (int) array_search($tier, self::TIERS, true);
    }
}
