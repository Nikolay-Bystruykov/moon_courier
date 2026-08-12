<?php

namespace App\Domain\Lunar;

/**
 * Генератор Лемера (minimal standard, множитель 48271).
 *
 * Своя реализация нужна не из недоверия к стандартной библиотеке, а потому,
 * что исход рейса обязан воспроизводиться по записанному зерну: только так
 * редкий инцидент можно поймать тестом и объяснить игроку постфактум.
 */
class SeededRandom
{
    private const MODULUS = 2147483647;

    private const MULTIPLIER = 48271;

    private int $state;

    public function __construct(int $seed)
    {
        // Ноль и числа, кратные модулю, зацикливают генератор на нуле.
        $this->state = ($seed % self::MODULUS + self::MODULUS) % self::MODULUS ?: 1;
    }

    public function next(): float
    {
        $this->state = ($this->state * self::MULTIPLIER) % self::MODULUS;

        return ($this->state - 1) / (self::MODULUS - 1);
    }

    public function nextInt(int $min, int $max): int
    {
        return $min + (int) floor($this->next() * ($max - $min + 1));
    }

    public function pick(array $items): mixed
    {
        $values = array_values($items);

        return $values[$this->nextInt(0, count($values) - 1)];
    }
}
