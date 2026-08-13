<?php

namespace App\Domain\Lunar;

enum Upgrade: string
{
    case Battery = 'battery';
    case Capacity = 'capacity';

    public function cost(): int
    {
        return match ($this) {
            self::Battery => Rules::UPGRADE_BATTERY_COST,
            self::Capacity => Rules::UPGRADE_CAPACITY_COST,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Battery => 'аккумулятор',
            self::Capacity => 'грузовой отсек',
        };
    }

    public function note(): string
    {
        return match ($this) {
            self::Battery => '+'.round(Rules::UPGRADE_BATTERY_GAIN * 100).'% ёмкости',
            self::Capacity => '+'.round(Rules::UPGRADE_CAPACITY_GAIN * 100).'% грузоподъёмности',
        };
    }

    /** Новое значение характеристики после установки. */
    public function apply(int $current): int
    {
        $gain = match ($this) {
            self::Battery => Rules::UPGRADE_BATTERY_GAIN,
            self::Capacity => Rules::UPGRADE_CAPACITY_GAIN,
        };

        return (int) round($current * (1 + $gain));
    }
}
