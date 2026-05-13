<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CondimentGroup extends Model
{
    protected $fillable = ['name', 'type', 'is_required'];

    protected function casts(): array
    {
        return ['is_required' => 'boolean'];
    }

    public function options(): HasMany
    {
        return $this->hasMany(CondimentOption::class)->orderBy('sort_order')->orderBy('id');
    }

    public function menus(): BelongsToMany
    {
        return $this->belongsToMany(
            Menu::class,
            'menu_condiment_groups',
            'condiment_group_id',
            'menu_id'
        )->withTimestamps();
    }
}
