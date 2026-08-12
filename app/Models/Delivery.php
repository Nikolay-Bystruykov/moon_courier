<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delivery extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'route' => 'array',
            'risk_breakdown' => 'array',
            'delay_applied' => 'boolean',
            'dispatched_day' => 'integer',
            'return_day' => 'integer',
            'route_cost' => 'float',
            'battery_cost' => 'float',
            'risk' => 'float',
            'seed' => 'integer',
            'resolved_day' => 'integer',
        ];
    }

    public function rover(): BelongsTo
    {
        return $this->belongsTo(Rover::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
