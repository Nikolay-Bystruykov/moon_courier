<?php

namespace App\Models;

use App\Domain\Lunar\Rules;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Outpost extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'x' => 'integer',
            'y' => 'integer',
            'route_cost' => 'float',
            'route_tiles' => 'integer',
        ];
    }

    /** Физическое расстояние от базы в километрах. */
    public function distanceKm(): int
    {
        return $this->route_tiles * Rules::KM_PER_TILE;
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
