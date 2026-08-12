<?php

namespace App\Models;

use App\Domain\Lunar\Terrain;
use Illuminate\Database\Eloquent\Model;

class MapTile extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'x' => 'integer',
            'y' => 'integer',
            'terrain' => Terrain::class,
        ];
    }
}
