{{-- Расчёт приходит с сервера: формулы живут в домене и не дублируются в JS. --}}
<section class="panel border-amber/40" x-show="selectedRover && selectedOrder" x-cloak>
    <h2 class="label border-b border-edge px-3 py-2">расчёт рейса</h2>

    <p class="px-3 py-4 text-xs text-dim" x-show="loading">Прокладка маршрута…</p>

    <div class="p-3" x-show="!loading && plan">
        <p class="flex items-baseline justify-between gap-2 text-sm">
            <span x-text="plan.rover.name + ' → ' + plan.order.outpost"></span>
            <span class="tabular text-amber" x-text="plan.order.reward.toLocaleString('ru') + ' кр'"></span>
        </p>

        <dl class="mt-3 space-y-1.5 text-xs" x-show="plan.estimate">
            <div class="flex justify-between gap-3">
                <dt class="text-dim">длина маршрута</dt>
                <dd class="tabular" x-text="plan.estimate?.distance_km + ' км в одну сторону'"></dd>
            </div>
            <div class="flex justify-between gap-3">
                <dt class="text-dim">расход заряда</dt>
                <dd class="tabular">
                    <span x-text="'−' + plan.estimate?.battery_cost"></span>
                    <span class="text-dim" x-text="'(останется ' + plan.estimate?.battery_percent_after + '%)'"></span>
                </dd>
            </div>
            <div class="flex justify-between gap-3">
                <dt class="text-dim">время в пути</dt>
                <dd class="tabular" x-text="plan.estimate?.days + ' сут, возврат на ' + plan.estimate?.return_day"></dd>
            </div>
            <div class="flex justify-between gap-3">
                <dt class="text-dim">загрузка</dt>
                <dd class="tabular" x-text="plan.order.weight_kg + ' / ' + plan.rover.capacity_kg + ' кг'"></dd>
            </div>
        </dl>

        <div class="mt-3 border-t border-edge pt-3" x-show="plan.estimate">
            <p class="flex items-baseline justify-between gap-2">
                <span class="label">риск рейса</span>
                <span class="tabular text-xl"
                      :class="plan.estimate?.risk > 50 ? 'text-bad' : (plan.estimate?.risk > 25 ? 'text-warn' : 'text-good')"
                      x-text="plan.estimate?.risk + '%'"></span>
            </p>

            {{-- Игрок должен видеть, из чего сложился риск, а не одно число. --}}
            <ul class="mt-2 space-y-1 text-[11px]">
                <template x-for="part in plan.estimate?.risk_components ?? []" :key="part.label">
                    <li class="flex justify-between gap-3 text-dim">
                        <span x-text="part.label"></span>
                        <span class="tabular" x-text="'+' + part.value + '%'"></span>
                    </li>
                </template>
            </ul>
        </div>

        <div class="mt-3 border-t border-edge pt-3" x-show="!plan.allowed">
            <p class="label text-bad">рейс невозможен</p>
            <ul class="mt-1.5 space-y-1 text-xs text-warn">
                <template x-for="reason in plan.reasons" :key="reason">
                    <li x-text="'— ' + reason"></li>
                </template>
            </ul>
        </div>

        <form method="POST" action="{{ route('mission.dispatch') }}" class="mt-4" x-show="plan.allowed">
            @csrf
            <input type="hidden" name="rover_id" :value="selectedRover">
            <input type="hidden" name="order_id" :value="selectedOrder">
            <button type="submit"
                    class="w-full border border-amber px-3 py-2.5 text-[11px] uppercase tracking-[0.2em] text-amber transition-colors hover:bg-amber hover:text-void focus:bg-amber focus:text-void focus:outline-none">
                ▸ отправить ровер
            </button>
        </form>
    </div>
</section>
