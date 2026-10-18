<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Menu extends Model
{
    use HasFactory;

    protected $fillable = [
        'menu_category_id', 'name', 'description',
        'price', 'image_url', 'is_active', 'points_override',
    ];

    protected $appends = ['points_per_item'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
            'points_override' => 'integer',
        ];
    }

    /**
     * Member points for one unit: the admin override, else 1 if the normal price
     * reaches the configured minimum (discounts and condiments don't matter).
     */
    public function getPointsPerItemAttribute(): int
    {
        if ($this->points_override !== null) {
            return (int) $this->points_override;
        }

        return (float) $this->price >= LoyaltySetting::current()->min_item_price ? 1 : 0;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'menu_category_id');
    }

    public function bomItems(): HasMany
    {
        return $this->hasMany(BomItem::class);
    }

    public function condimentGroups(): BelongsToMany
    {
        return $this->belongsToMany(
            CondimentGroup::class,
            'menu_condiment_groups',
            'menu_id',
            'condiment_group_id'
        )->withTimestamps();
    }

    public function productDiscounts(): HasMany
    {
        return $this->hasMany(ProductDiscount::class);
    }

    public function eventDiscounts(): BelongsToMany
    {
        return $this->belongsToMany(
            EventDiscount::class,
            'event_discount_menus',
            'menu_id',
            'event_discount_id'
        )->withTimestamps();
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
