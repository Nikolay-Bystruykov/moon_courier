<?php

namespace App\Domain\Lunar;

enum Incident: string
{
    case Stuck = 'stuck';
    case Suspension = 'suspension';
    case DustStorm = 'dust_storm';
    case CommsLoss = 'comms_loss';
    case CriticalFailure = 'critical_failure';

    public function label(): string
    {
        return match ($this) {
            self::Stuck => 'застревание в реголите',
            self::Suspension => 'повреждение подвески',
            self::DustStorm => 'пылевая буря',
            self::CommsLoss => 'потеря связи',
            self::CriticalFailure => 'критическая поломка',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Stuck => 'Ровер увяз в рыхлом реголите и потерял сутки на выход',
            self::Suspension => 'Камень повредил подвеску, на базе потребовался ремонт',
            self::DustStorm => 'Пылевая буря заставила переждать двое суток',
            self::CommsLoss => 'Связь пропала, груз принят без подтверждения срочности',
            self::CriticalFailure => 'Отказ трансмиссии: груз потерян, ровер эвакуирован',
        };
    }

    public function extraDays(): int
    {
        return match ($this) {
            self::Stuck => 1,
            self::DustStorm => 2,
            default => 0,
        };
    }

    public function repairDays(): int
    {
        return match ($this) {
            self::Suspension => 2,
            self::CriticalFailure => 3,
            default => 0,
        };
    }

    public function repairCost(): int
    {
        return match ($this) {
            self::Suspension => 150,
            self::CriticalFailure => 400,
            default => 0,
        };
    }

    public function failsOrder(): bool
    {
        return $this === self::CriticalFailure;
    }

    public function dropsUrgencyBonus(): bool
    {
        return $this === self::CommsLoss;
    }

    public function extraBatteryDrain(): float
    {
        return $this === self::Stuck ? 8.0 : 0.0;
    }
}
