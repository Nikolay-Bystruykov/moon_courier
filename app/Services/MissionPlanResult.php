<?php

namespace App\Services;

use App\Domain\Lunar\MissionEstimate;
use App\Domain\Lunar\ValidationResult;

readonly class MissionPlanResult
{
    public function __construct(
        public ?MissionEstimate $estimate,
        public ValidationResult $validation,
    ) {
    }
}
