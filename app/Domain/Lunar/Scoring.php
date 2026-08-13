<?php

namespace App\Domain\Lunar;

/**
 * Итог смены.
 *
 * В счёт входят не только кредиты, но и стоимость парка: иначе покупка ровера
 * читалась бы как чистая потеря очков, и выгоднее всего было бы не тратить
 * ничего. Техника — вложение, а не расход.
 */
class Scoring
{
    public const REPUTATION_WEIGHT = 10;

    /** @param  array<int, array{class: string, battery_upgraded: bool, capacity_upgraded: bool}>  $fleet */
    public static function fleetValue(array $fleet): int
    {
        $value = 0;

        foreach ($fleet as $rover) {
            $value += Rules::ROVER_PRICES[$rover['class']];
            $value += $rover['battery_upgraded'] ? Rules::UPGRADE_BATTERY_COST : 0;
            $value += $rover['capacity_upgraded'] ? Rules::UPGRADE_CAPACITY_COST : 0;
        }

        return $value;
    }

    public static function total(int $credits, int $fleetValue, int $reputation): int
    {
        return $credits + $fleetValue + $reputation * self::REPUTATION_WEIGHT;
    }
}
