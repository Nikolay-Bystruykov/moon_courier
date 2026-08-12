<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'weight_kg' => 'integer',
            'reward' => 'integer',
            'deadline_day' => 'integer',
            'created_day' => 'integer',
        ];
    }

    public function outpost(): BelongsTo
    {
        return $this->belongsTo(Outpost::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
