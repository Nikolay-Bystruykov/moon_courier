<?php

namespace App\Models;

use App\Domain\Lunar\RoverClass;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rover extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'rover_class' => RoverClass::class,
            'capacity_kg' => 'integer',
            'battery_capacity' => 'integer',
            'battery_level' => 'float',
            'repair_days_left' => 'integer',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function isAvailable(): bool
    {
        return $this->status === 'idle' && $this->repair_days_left === 0;
    }
}
