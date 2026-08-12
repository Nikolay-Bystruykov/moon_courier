<?php

namespace App\Domain\Lunar;

/**
 * Разрешение рейса: первый бросок решает, случилось ли происшествие,
 * второй выбирает его вид.
 *
 * Генератор передаётся снаружи и создаётся из зерна, записанного в базу.
 * Благодаря этому исход воспроизводится: редкий инцидент можно поймать
 * тестом, а игроку — объяснить, почему рейс закончился именно так.
 */
class MissionResolver
{
    public static function resolve(float $risk, SeededRandom $rng): MissionOutcome
    {
        if ($rng->next() >= $risk) {
            return MissionOutcome::clean();
        }

        $incident = IncidentTable::pick($rng);

        return new MissionOutcome(
            incidentOccurred: true,
            incident: $incident,
            extraDays: $incident->extraDays(),
            repairDays: $incident->repairDays(),
            repairCost: $incident->repairCost(),
            orderFailed: $incident->failsOrder(),
            extraBatteryDrain: $incident->extraBatteryDrain(),
            dropsUrgencyBonus: $incident->dropsUrgencyBonus(),
        );
    }

    /**
     * Зерно рейса выводится из зерна партии, поэтому вся партия целиком
     * воспроизводится по одному числу.
     */
    public static function seedFor(int $gameSeed, int $deliveryId): int
    {
        return (int) hexdec(substr(hash('sha256', $gameSeed.':'.$deliveryId), 0, 12));
    }
}
