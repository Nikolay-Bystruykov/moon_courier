<?php

namespace App\Domain\Lunar;

/**
 * Расчёт рейса: расход заряда, время в пути и риск.
 *
 * Груз едет только в одну сторону, поэтому обратный путь считается порожняком.
 * Это заметно влияет на дальность и делает экономику честной: ровер,
 * доставивший тяжёлый заказ, возвращается налегке.
 */
class MissionPlanner
{
    public static function estimate(
        LunarMap $map,
        Route $route,
        int $capacityKg,
        int $batteryCapacity,
        float $batteryLevel,
        float $speed,
        int $cargoKg,
        int $dispatchDay,
        int $deadlineDay,
    ): MissionEstimate {
        $loadFactor = 1 + ($cargoKg / max(1, $capacityKg)) * Rules::LOAD_PENALTY;

        $batteryCost = self::legCost($map, $route, $loadFactor) + self::legCost($map, $route, 1.0);
        $batteryAfter = $batteryLevel - $batteryCost;

        $days = max(1, (int) ceil($route->cost * 2 / ($speed / $loadFactor)));
        $returnDay = $dispatchDay + $days;

        return new MissionEstimate(
            route: $route,
            batteryCost: $batteryCost,
            batteryAfter: $batteryAfter,
            days: $days,
            returnDay: $returnDay,
            risk: self::risk(
                $map, $route, $cargoKg, $capacityKg, $batteryAfter, $batteryCapacity, $returnDay, $deadlineDay,
            ),
        );
    }

    /**
     * Заряд тратится на въезд в клетку, поэтому стартовая не считается —
     * ровер уже на ней стоит.
     */
    private static function legCost(LunarMap $map, Route $route, float $loadFactor): float
    {
        $cost = 0.0;

        foreach (array_slice($route->coordinates, 1) as $coordinate) {
            $terrain = $map->at($coordinate);

            $cost += Rules::BASE_BATTERY_DRAW * $terrain->moveCost() * $loadFactor + $terrain->extraDraw();
        }

        return $cost;
    }

    private static function risk(
        LunarMap $map,
        Route $route,
        int $cargoKg,
        int $capacityKg,
        float $batteryAfter,
        int $batteryCapacity,
        int $returnDay,
        int $deadlineDay,
    ): RiskProfile {
        // Вероятность хотя бы одного происшествия — дополнение к вероятности
        // проехать все клетки маршрута без единого.
        $survival = 1.0;

        foreach (array_slice($route->coordinates, 1) as $coordinate) {
            $survival *= 1 - $map->at($coordinate)->risk();
        }

        $routeRisk = 1 - $survival;

        $overload = $cargoKg / max(1, $capacityKg) > Rules::OVERLOAD_THRESHOLD
            ? Rules::OVERLOAD_RISK
            : 0.0;

        $lowBattery = $batteryAfter / max(1, $batteryCapacity) < Rules::LOW_BATTERY_THRESHOLD
            ? Rules::LOW_BATTERY_RISK
            : 0.0;

        $lateReturn = $returnDay > $deadlineDay ? Rules::LATE_RETURN_RISK : 0.0;

        return new RiskProfile(
            route: $routeRisk,
            overload: $overload,
            lowBattery: $lowBattery,
            lateReturn: $lateReturn,
            total: min(Rules::MAX_RISK, $routeRisk + $overload + $lowBattery + $lateReturn),
        );
    }
}
