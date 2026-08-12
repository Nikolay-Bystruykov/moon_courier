<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'seed' => 'integer',
            'day' => 'integer',
            'credits' => 'integer',
            'reputation' => 'integer',
        ];
    }

    public function tiles(): HasMany
    {
        return $this->hasMany(MapTile::class);
    }

    public function outposts(): HasMany
    {
        return $this->hasMany(Outpost::class);
    }

    public function rovers(): HasMany
    {
        return $this->hasMany(Rover::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(GameEvent::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
