<?php

namespace App\Domain\Lunar;

/**
 * Проверка допустимости рейса.
 *
 * Возвращает все нарушенные ограничения сразу, а не первое попавшееся: игрок
 * должен один раз увидеть полную причину отказа, а не исправлять их по одной.
 */
class MissionValidator
{
    public static function validate(
        ?MissionEstimate $estimate,
        int $cargoKg,
        int $capacityKg,
        int $batteryCapacity,
        string $roverStatus,
        int $repairDaysLeft,
        string $orderStatus,
        int $currentDay,
        int $deadlineDay,
    ): ValidationResult {
        $reasons = [];

        if ($cargoKg > $capacityKg) {
            $reasons[] = RejectionReason::Overweight;
        }

        if ($repairDaysLeft > 0 || $roverStatus === 'repair') {
            $reasons[] = RejectionReason::RoverInRepair;
        } elseif ($roverStatus !== 'idle') {
            $reasons[] = RejectionReason::RoverBusy;
        }

        if ($orderStatus !== 'pending') {
            $reasons[] = RejectionReason::OrderTaken;
        }

        if ($currentDay > $deadlineDay) {
            $reasons[] = RejectionReason::OrderExpired;
        }

        if ($estimate === null) {
            $reasons[] = RejectionReason::Unreachable;

            return new ValidationResult(false, $reasons);
        }

        // Резерв — неприкосновенный остаток: ровер, вернувшийся ровно на нуле,
        // не переживёт ни одной задержки в пути.
        if ($estimate->batteryAfter < $batteryCapacity * Rules::BATTERY_RESERVE) {
            $reasons[] = RejectionReason::InsufficientBattery;
        }

        return new ValidationResult($reasons === [], $reasons);
    }
}
