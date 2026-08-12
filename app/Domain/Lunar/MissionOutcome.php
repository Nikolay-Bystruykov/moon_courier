<?php

namespace App\Domain\Lunar;

readonly class MissionOutcome
{
    public function __construct(
        public bool $incidentOccurred,
        public ?Incident $incident,
        public int $extraDays,
        public int $repairDays,
        public int $repairCost,
        public bool $orderFailed,
        public float $extraBatteryDrain,
        public bool $dropsUrgencyBonus,
    ) {
    }

    public static function clean(): self
    {
        return new self(false, null, 0, 0, 0, false, 0.0, false);
    }
}
