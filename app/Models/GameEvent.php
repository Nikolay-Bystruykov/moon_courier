<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameEvent extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'day' => 'integer',
            'payload' => 'array',
        ];
    }
}
