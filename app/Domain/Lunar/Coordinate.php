<?php

namespace App\Domain\Lunar;

readonly class Coordinate
{
    public function __construct(public int $x, public int $y)
    {
    }

    public function key(): string
    {
        return $this->x.':'.$this->y;
    }

    public function equals(self $other): bool
    {
        return $this->x === $other->x && $this->y === $other->y;
    }
}
