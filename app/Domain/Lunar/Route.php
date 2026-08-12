<?php

namespace App\Domain\Lunar;

readonly class Route
{
    /** @param  Coordinate[]  $coordinates */
    public function __construct(public array $coordinates, public float $cost)
    {
    }

    public function length(): int
    {
        return count($this->coordinates);
    }

    public function destination(): Coordinate
    {
        return $this->coordinates[array_key_last($this->coordinates)];
    }

    /** @return array<int, array{x: int, y: int}> */
    public function toArray(): array
    {
        return array_map(fn (Coordinate $c) => ['x' => $c->x, 'y' => $c->y], $this->coordinates);
    }
}
