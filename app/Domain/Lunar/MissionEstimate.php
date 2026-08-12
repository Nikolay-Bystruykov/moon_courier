<?php

namespace App\Domain\Lunar;

readonly class MissionEstimate
{
    public function __construct(
        public Route $route,
        public float $batteryCost,
        public float $batteryAfter,
        public int $days,
        public int $returnDay,
        public RiskProfile $risk,
    ) {
    }
}
