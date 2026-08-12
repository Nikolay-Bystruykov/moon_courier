<?php

namespace App\Domain\Lunar;

enum Terrain: string
{
    case Mare = 'mare';
    case Regolith = 'regolith';
    case Crater = 'crater';
    case Rille = 'rille';
    case Shadow = 'shadow';

    /** Во сколько раз клетка дороже эталонной морской равнины. */
    public function moveCost(): float
    {
        return match ($this) {
            self::Mare => 1.0,
            self::Regolith => 1.3,
            self::Shadow => 1.6,
            self::Crater => 2.2,
            self::Rille => 3.0,
        };
    }

    /** Вероятность происшествия при проезде одной клетки. */
    public function risk(): float
    {
        return match ($this) {
            self::Mare => 0.01,
            self::Regolith => 0.02,
            self::Shadow => 0.05,
            self::Crater => 0.06,
            self::Rille => 0.10,
        };
    }

    /** Дополнительный расход заряда: в тени ровер греется. */
    public function extraDraw(): float
    {
        return $this === self::Shadow ? Rules::SHADOW_EXTRA_DRAW : 0.0;
    }

    public function label(): string
    {
        return match ($this) {
            self::Mare => 'морская равнина',
            self::Regolith => 'реголит',
            self::Crater => 'кратерное поле',
            self::Rille => 'борозда',
            self::Shadow => 'вечная тень',
        };
    }
}
