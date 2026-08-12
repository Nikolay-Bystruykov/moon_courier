<?php

namespace App\Domain\Lunar;

enum RoverClass: string
{
    case Crawler = 'crawler';
    case Scout = 'scout';
    case Hauler = 'hauler';

    public function capacityKg(): int
    {
        return match ($this) {
            self::Crawler => 400,
            self::Scout => 120,
            self::Hauler => 260,
        };
    }

    public function batteryCapacity(): int
    {
        return match ($this) {
            self::Crawler => 100,
            self::Scout => 80,
            self::Hauler => 140,
        };
    }

    /**
     * Клеток эталонной равнины за сутки без груза.
     *
     * Подобрано так, чтобы средний рейс укладывался в двое суток: при более
     * низких скоростях ровер не успевает вернуться до истечения сроков и парк
     * простаивает в пути.
     */
    public function speed(): float
    {
        return match ($this) {
            self::Crawler => 22.0,
            self::Scout => 34.0,
            self::Hauler => 26.0,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Crawler => 'Гусеница',
            self::Scout => 'Скаут',
            self::Hauler => 'Тягач',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Crawler => 'берёт много, но медленный и недалёкий',
            self::Scout => 'быстрый, лёгкий груз, короткое плечо',
            self::Hauler => 'большой запас хода, средняя грузоподъёмность',
        };
    }
}
