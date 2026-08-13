<?php

namespace App\Domain\Lunar;

/**
 * Игровые константы. Собраны в одном месте, чтобы баланс правился здесь,
 * а не поиском магических чисел по всему коду.
 */
class Rules
{
    public const MAP_WIDTH = 18;

    public const MAP_HEIGHT = 12;

    public const BASE_X = 3;

    public const BASE_Y = 6;

    public const OUTPOST_COUNT = 8;

    /** Сторона клетки в километрах — всё расстояние на экране считается отсюда. */
    public const KM_PER_TILE = 5;

    /** Аванпосты ставятся только там, где стоимость маршрута попадает в диапазон. */
    public const MIN_OUTPOST_COST = 6.0;

    public const MAX_OUTPOST_COST = 32.0;

    /**
     * Пояса дальности: сколько аванпостов ставить в каждом диапазоне стоимости.
     *
     * Без поясов случайная расстановка сбивает почти все аванпосты в один
     * пояс, и выбор ровера перестаёт что-либо значить: любой доезжает всюду.
     */
    public const OUTPOST_BANDS = [
        ['count' => 3, 'min' => 6.0, 'max' => 12.0],
        ['count' => 3, 'min' => 12.0, 'max' => 20.0],
        ['count' => 2, 'min' => 20.0, 'max' => 32.0],
    ];

    /** Заряд, который тратит порожний ровер на клетку морской равнины. */
    public const BASE_BATTERY_DRAW = 1.5;

    /** Надбавка за клетку вечной тени: ровер тратит энергию на обогрев. */
    public const SHADOW_EXTRA_DRAW = 1.5;

    /** Насколько полная загрузка увеличивает расход и снижает скорость. */
    public const LOAD_PENALTY = 0.8;

    /** Неснижаемый остаток заряда, который нельзя закладывать в рейс. */
    public const BATTERY_RESERVE = 0.10;

    /**
     * Доля ёмкости, восстанавливаемая за сутки на базе.
     *
     * Ниже 0.6 парк не успевает оборачиваться: ровер после рейса простаивает
     * двое суток, весь парк выдаёт меньше рейса в сутки, и заказы копятся
     * быстрее, чем их успевают возить.
     */
    public const RECHARGE_RATE = 0.65;

    public const OVERLOAD_THRESHOLD = 0.85;

    public const OVERLOAD_RISK = 0.08;

    public const LOW_BATTERY_THRESHOLD = 0.15;

    public const LOW_BATTERY_RISK = 0.10;

    public const LATE_RETURN_RISK = 0.05;

    /** Совсем гиблых рейсов не бывает — риск ограничен сверху. */
    public const MAX_RISK = 0.85;

    public const TOTAL_DAYS = 14;

    public const START_CREDITS = 500;

    public const START_REPUTATION = 100;

    public const MAX_REPUTATION = 100;

    public const REPUTATION_ON_TIME = 3;

    public const REPUTATION_LATE = -5;

    public const REPUTATION_FAILED = -12;

    public const REPUTATION_EXPIRED = -4;

    /**
     * Поток заявок держится чуть выше пропускной способности парка: игрок
     * обязан выбирать, что везти, но не тонет в заведомо непосильной очереди.
     */
    public const ORDERS_PER_DAY_MIN = 2;

    public const ORDERS_PER_DAY_MAX = 3;

    public const ORDER_WEIGHT_MIN = 40;

    public const ORDER_WEIGHT_MAX = 350;

    public const ORDER_DEADLINE_MIN = 2;

    public const ORDER_DEADLINE_MAX = 5;

    public const REWARD_PER_KG = 1.0;

    public const REWARD_PER_COST = 18.0;

    /** Насколько короткий срок повышает награду. */
    public const URGENCY_BONUS = 0.12;

    /** Доля награды при доставке с потерей связи. */
    public const COMMS_LOSS_PAYOUT = 0.8;

    /** Доля награды при опоздании. */
    public const LATE_PAYOUT = 0.5;
}
