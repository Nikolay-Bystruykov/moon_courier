<?php

namespace App\Domain\Lunar;

enum RejectionReason: string
{
    case Overweight = 'overweight';
    case InsufficientBattery = 'insufficient_battery';
    case RoverBusy = 'rover_busy';
    case RoverInRepair = 'rover_in_repair';
    case OrderTaken = 'order_taken';
    case OrderExpired = 'order_expired';
    case Unreachable = 'unreachable';

    public function message(): string
    {
        return match ($this) {
            self::Overweight => 'Груз тяжелее грузоподъёмности ровера',
            self::InsufficientBattery => 'Заряда не хватит на дорогу туда и обратно',
            self::RoverBusy => 'Ровер уже в рейсе',
            self::RoverInRepair => 'Ровер в ремонте',
            self::OrderTaken => 'Заказ уже назначен другому роверу',
            self::OrderExpired => 'Срок заказа истёк',
            self::Unreachable => 'До аванпоста нет маршрута',
        };
    }
}
