<?php

namespace App\Domain\Lunar;

class LunarMap
{
    /** @param  array<string, Terrain>  $tiles */
    public function __construct(
        private readonly int $width,
        private readonly int $height,
        private readonly array $tiles,
    ) {
    }

    public function width(): int
    {
        return $this->width;
    }

    public function height(): int
    {
        return $this->height;
    }

    /** @return array<string, Terrain> */
    public function all(): array
    {
        return $this->tiles;
    }

    public function at(Coordinate $coordinate): Terrain
    {
        return $this->tiles[$coordinate->key()];
    }

    public function contains(Coordinate $coordinate): bool
    {
        return isset($this->tiles[$coordinate->key()]);
    }

    /** @return Coordinate[] */
    public function neighbours(Coordinate $coordinate): array
    {
        $candidates = [
            new Coordinate($coordinate->x + 1, $coordinate->y),
            new Coordinate($coordinate->x - 1, $coordinate->y),
            new Coordinate($coordinate->x, $coordinate->y + 1),
            new Coordinate($coordinate->x, $coordinate->y - 1),
        ];

        return array_values(array_filter($candidates, fn (Coordinate $c) => $this->contains($c)));
    }
}
