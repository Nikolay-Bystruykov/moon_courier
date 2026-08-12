<?php

namespace App\Domain\Lunar;

/**
 * Таблица инцидентов с весами: мелкие неприятности случаются часто,
 * потеря груза вместе с ровером — редко.
 */
class IncidentTable
{
    /** @var array<string, int> */
    private const WEIGHTS = [
        'stuck' => 35,
        'suspension' => 25,
        'dust_storm' => 20,
        'comms_loss' => 12,
        'critical_failure' => 8,
    ];

    public static function pick(SeededRandom $rng): Incident
    {
        $roll = $rng->next() * array_sum(self::WEIGHTS);
        $cursor = 0.0;

        foreach (self::WEIGHTS as $value => $weight) {
            $cursor += $weight;

            if ($roll < $cursor) {
                return Incident::from($value);
            }
        }

        return Incident::Stuck;
    }
}
