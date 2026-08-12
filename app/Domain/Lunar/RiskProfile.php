<?php

namespace App\Domain\Lunar;

readonly class RiskProfile
{
    public function __construct(
        public float $route,
        public float $overload,
        public float $lowBattery,
        public float $lateReturn,
        public float $total,
    ) {
    }

    /**
     * Составляющие риска для интерфейса. Показываются только сработавшие:
     * список нулевых надбавок ничего игроку не объясняет.
     *
     * @return array<int, array{code: string, label: string, value: float}>
     */
    public function components(): array
    {
        $all = [
            ['code' => 'route', 'label' => 'местность на маршруте', 'value' => $this->route],
            ['code' => 'overload', 'label' => 'загрузка выше 85%', 'value' => $this->overload],
            ['code' => 'low_battery', 'label' => 'возврат на низком заряде', 'value' => $this->lowBattery],
            ['code' => 'late_return', 'label' => 'возврат позже срока', 'value' => $this->lateReturn],
        ];

        return array_values(array_filter($all, fn (array $item) => $item['value'] > 0.0));
    }
}
