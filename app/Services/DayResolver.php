<?php

namespace App\Services;

use App\Domain\Lunar\MissionOutcome;
use App\Domain\Lunar\MissionResolver;
use App\Domain\Lunar\Rules;
use App\Domain\Lunar\SeededRandom;
use App\Models\Delivery;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Завершение суток: возвращение роверов, сгорание просроченных заказов,
 * зарядка, ремонт, поступление новых заявок и проверка конца партии.
 */
class DayResolver
{
    public function __construct(private readonly OrderGenerator $orders)
    {
    }

    public function advance(Game $game): void
    {
        if (! $game->isActive()) {
            return;
        }

        DB::transaction(function () use ($game) {
            $game->increment('day');
            $game->refresh();

            $this->resolveArrivals($game);
            $this->expireOrders($game);
            $this->recharge($game);
            $this->finishRepairs($game);
            $this->issueOrders($game);
            $this->checkEnd($game);
        });
    }

    private function resolveArrivals(Game $game): void
    {
        $arrivals = Delivery::with(['rover', 'order.outpost'])
            ->where('game_id', $game->id)
            ->where('status', 'in_transit')
            ->where('return_day', '<=', $game->day)
            ->get();

        foreach ($arrivals as $delivery) {
            // Исход выводится из зерна и потому пересчитывается одинаково
            // при каждом обращении — хранить его заранее не нужно.
            $outcome = MissionResolver::resolve($delivery->risk, new SeededRandom($delivery->seed));

            if ($this->applyDelay($game, $delivery, $outcome)) {
                continue;
            }

            $this->applyOutcome($game, $delivery, $outcome);
        }
    }

    /**
     * Инцидент может задержать ровер в пути. Флаг нужен, чтобы задержка
     * применилась ровно один раз: иначе рейс продлевался бы при каждой
     * проверке и никогда не заканчивался.
     *
     * @return bool нужно ли оставить рейс в пути ещё на сутки
     */
    private function applyDelay(Game $game, Delivery $delivery, MissionOutcome $outcome): bool
    {
        if ($outcome->extraDays === 0 || $delivery->delay_applied) {
            return false;
        }

        $delivery->update([
            'return_day' => $delivery->return_day + $outcome->extraDays,
            'delay_applied' => true,
        ]);

        GameEvent::create([
            'game_id' => $game->id,
            'day' => $game->day,
            'type' => 'delay',
            'message' => sprintf(
                '%s задерживается на %d сут: %s',
                $delivery->rover->name,
                $outcome->extraDays,
                $outcome->incident->label(),
            ),
            'payload' => ['delivery_id' => $delivery->id],
        ]);

        return $delivery->refresh()->return_day > $game->day;
    }

    private function applyOutcome(Game $game, Delivery $delivery, MissionOutcome $outcome): void
    {
        $rover = $delivery->rover;
        $order = $delivery->order;

        $rover->update([
            'battery_level' => max(0.0, $rover->battery_level - $delivery->battery_cost - $outcome->extraBatteryDrain),
            'status' => $outcome->repairDays > 0 ? 'repair' : 'idle',
            'repair_days_left' => $outcome->repairDays,
        ]);

        $late = $game->day > $order->deadline_day;

        if ($outcome->orderFailed) {
            $order->update(['status' => 'failed']);
            $this->adjustReputation($game, Rules::REPUTATION_FAILED);

            $result = 'failed';
            $message = sprintf(
                '%s: заказ на %s провален — %s',
                $rover->name,
                $order->outpost->name,
                $outcome->incident->label(),
            );
        } elseif ($late) {
            $payout = (int) round($order->reward * Rules::LATE_PAYOUT);

            $order->update(['status' => 'delivered']);
            $game->increment('credits', $payout);
            $this->adjustReputation($game, Rules::REPUTATION_LATE);

            $result = 'late';
            $message = sprintf(
                '%s: груз доставлен на %s с опозданием, оплата %d кр',
                $rover->name,
                $order->outpost->name,
                $payout,
            );
        } else {
            $payout = $outcome->dropsUrgencyBonus
                ? (int) round($order->reward * Rules::COMMS_LOSS_PAYOUT)
                : $order->reward;

            $order->update(['status' => 'delivered']);
            $game->increment('credits', $payout);
            $this->adjustReputation($game, Rules::REPUTATION_ON_TIME);

            $result = 'delivered';
            $message = sprintf(
                '%s: груз доставлен на %s, оплата %d кр',
                $rover->name,
                $order->outpost->name,
                $payout,
            );
        }

        if ($outcome->incidentOccurred && ! $outcome->orderFailed) {
            $message .= sprintf(' (%s)', $outcome->incident->label());
        }

        if ($outcome->repairCost > 0) {
            $game->decrement('credits', $outcome->repairCost);
            $message .= sprintf('. Ремонт: %d кр', $outcome->repairCost);
        }

        $delivery->update([
            'status' => 'completed',
            'outcome' => $result,
            'incident' => $outcome->incident?->value,
            'resolved_day' => $game->day,
        ]);

        GameEvent::create([
            'game_id' => $game->id,
            'day' => $game->day,
            'type' => 'delivery',
            'message' => $message,
            'payload' => [
                'delivery_id' => $delivery->id,
                'incident' => $outcome->incident?->value,
            ],
        ]);
    }

    private function expireOrders(Game $game): void
    {
        $expired = Order::with('outpost')
            ->where('game_id', $game->id)
            ->where('status', 'pending')
            ->where('deadline_day', '<', $game->day)
            ->get();

        foreach ($expired as $order) {
            $order->update(['status' => 'expired']);
            $this->adjustReputation($game, Rules::REPUTATION_EXPIRED);

            GameEvent::create([
                'game_id' => $game->id,
                'day' => $game->day,
                'type' => 'expired',
                'message' => sprintf('Заказ на %s сгорел: никто не выехал', $order->outpost->name),
                'payload' => ['order_id' => $order->id],
            ]);
        }
    }

    private function recharge(Game $game): void
    {
        foreach ($game->rovers()->where('status', '!=', 'en_route')->get() as $rover) {
            $rover->update([
                'battery_level' => min(
                    (float) $rover->battery_capacity,
                    $rover->battery_level + $rover->battery_capacity * Rules::RECHARGE_RATE,
                ),
            ]);
        }
    }

    private function finishRepairs(Game $game): void
    {
        foreach ($game->rovers()->where('repair_days_left', '>', 0)->get() as $rover) {
            $left = $rover->repair_days_left - 1;

            $rover->update([
                'repair_days_left' => $left,
                'status' => $left === 0 ? 'idle' : 'repair',
            ]);
        }
    }

    private function issueOrders(Game $game): void
    {
        // Зерно суток выводится из зерна партии, поэтому поток заявок
        // повторяется при повторном прохождении той же игры.
        $rng = new SeededRandom($game->seed + $game->day * 7919);

        $this->orders->generate(
            $game,
            $rng,
            $rng->nextInt(Rules::ORDERS_PER_DAY_MIN, Rules::ORDERS_PER_DAY_MAX),
        );
    }

    private function adjustReputation(Game $game, int $delta): void
    {
        $game->update([
            'reputation' => min(Rules::MAX_REPUTATION, $game->reputation + $delta),
        ]);
    }

    private function checkEnd(Game $game): void
    {
        if ($game->reputation <= 0) {
            $game->update(['status' => 'lost', 'reputation' => 0]);

            return;
        }

        if ($game->day > Rules::TOTAL_DAYS) {
            $game->update(['status' => 'won']);
        }
    }
}
