<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoyaltySetting extends Model
{
    protected $fillable = [
        'is_enabled', 'min_item_price',
        'silver_threshold', 'gold_threshold',
        'voucher_valid_days',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'min_item_price' => 'integer',
            'silver_threshold' => 'integer',
            'gold_threshold' => 'integer',
            'voucher_valid_days' => 'integer',
        ];
    }

    /** Cached in the container per request; Menu::points_per_item reads this for every menu in a listing. */
    public static function current(): self
    {
        if (! app()->bound(self::class)) {
            app()->instance(self::class, static::query()->first() ?? static::create([])->refresh());
        }

        return app(self::class);
    }

    protected static function booted(): void
    {
        static::saved(fn () => app()->forgetInstance(self::class));
    }

    public function tierFor(int $lifetimePoints): string
    {
        if ($lifetimePoints >= $this->gold_threshold) {
            return 'gold';
        }
        if ($lifetimePoints >= $this->silver_threshold) {
            return 'silver';
        }

        return 'bronze';
    }

    public function thresholdFor(string $tier): int
    {
        return match ($tier) {
            'gold' => $this->gold_threshold,
            'silver' => $this->silver_threshold,
            default => 0,
        };
    }
}
