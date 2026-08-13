@extends('layouts.console')

@section('title', 'Moon Courier Crisis — пульт снабжения')

@section('content')
@php $repBars = (int) round($game->reputation / 12.5); @endphp

<div class="mx-auto max-w-[1440px] p-4 lg:p-6"
     x-data="missionConsole(@js($ordersByOutpost), @js(collect($rovers)->mapWithKeys(fn ($r) => [$r['id'] => array_values(array_filter([
         $r['battery_upgraded'] ? 'battery' : null,
         $r['capacity_upgraded'] ? 'capacity' : null,
     ]))])))">

    <header class="panel mb-4 flex flex-wrap items-center justify-between gap-x-8 gap-y-3 px-4 py-3">
        <div class="flex items-baseline gap-3">
            <span class="text-sm uppercase tracking-[0.22em] text-amber">Moon Courier</span>
            <span class="label hidden sm:inline">лунная курьерская служба</span>
        </div>

        <dl class="flex flex-wrap items-baseline gap-x-7 gap-y-2">
            <div class="flex items-baseline gap-2">
                <dt class="label">сутки</dt>
                <dd class="tabular text-base">{{ str_pad($game->day, 2, '0', STR_PAD_LEFT) }}<span class="text-dim">/{{ \App\Domain\Lunar\Rules::TOTAL_DAYS }}</span></dd>
            </div>
            <div class="flex items-baseline gap-2">
                <dt class="label">кредиты</dt>
                <dd class="tabular text-base">{{ number_format($game->credits, 0, '.', ' ') }}</dd>
            </div>
            <div class="flex items-baseline gap-2">
                <dt class="label">в рейсе</dt>
                <dd class="tabular text-base">{{ $inTransit }}</dd>
            </div>
            <div class="flex items-baseline gap-2">
                <dt class="label">рейтинг базы</dt>
                <dd class="flex items-baseline gap-2">
                    <span class="tracking-[-0.05em] {{ $game->reputation > 50 ? 'text-good' : ($game->reputation > 25 ? 'text-warn' : 'text-bad') }}">{{ str_repeat('█', max(0, $repBars)).str_repeat('░', max(0, 8 - $repBars)) }}</span>
                    <span class="tabular text-base">{{ $game->reputation }}</span>
                </dd>
            </div>
        </dl>
    </header>

    @if (session('error'))
        <p class="panel mb-4 border-bad px-4 py-2.5 text-xs text-bad">{{ session('error') }}</p>
    @endif

    @if ($game->status !== 'active')
        <div class="mb-4">
            @include('game.partials.summary')
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_340px]">
        <div class="space-y-4">
            @include('game.partials.map')
            @include('game.partials.log')
        </div>

        <div class="space-y-4">
            @if ($game->status === 'active')
                @include('game.partials.mission')
            @endif

            @include('game.partials.rovers')
            @include('game.partials.orders')

            @if ($game->status === 'active')
                @include('game.partials.garage')
            @endif

            @if ($game->status === 'active')
                <form method="POST" action="{{ route('day.advance') }}">
                    @csrf
                    <button type="submit"
                            class="w-full border border-edge bg-panel px-3 py-3.5 text-[11px] uppercase tracking-[0.2em] transition-colors hover:border-amber hover:text-amber focus:border-amber focus:text-amber focus:outline-none">
                        завершить сутки ▸
                    </button>
                </form>

                <p class="px-1 font-sans text-[11px] leading-relaxed text-dim">
                    Выберите ровер и заявку — маршрут появится на карте вместе с расчётом.
                    Ровер обязан вернуться на базу, поэтому заряд считается на дорогу в обе стороны.
                </p>
            @endif
        </div>
    </div>
</div>

<script>
    function missionConsole(ordersByOutpost, installed) {
        return {
            ordersByOutpost,
            installed,
            selectedRover: null,
            selectedOrder: null,
            selectedOutpost: null,
            route: [],
            plan: null,
            loading: false,

            selectRover(id) {
                this.selectedRover = this.selectedRover === id ? null : id;
                this.refresh();
            },

            selectOrder(id, outpostId) {
                const same = this.selectedOrder === id;
                this.selectedOrder = same ? null : id;
                this.selectedOutpost = same ? null : outpostId;
                this.refresh();
            },

            selectOutpost(outpostId) {
                const orderId = this.ordersByOutpost[outpostId];

                if (!orderId) {
                    return;
                }

                this.selectOrder(orderId, outpostId);
            },

            async refresh() {
                if (!this.selectedRover || !this.selectedOrder) {
                    this.plan = null;
                    this.route = [];
                    return;
                }

                this.loading = true;

                const params = new URLSearchParams({
                    rover_id: this.selectedRover,
                    order_id: this.selectedOrder,
                });

                try {
                    const response = await fetch(`/mission/estimate?${params}`, {
                        headers: { Accept: 'application/json' },
                    });

                    this.plan = await response.json();
                    this.route = this.plan.route ?? [];
                } finally {
                    this.loading = false;
                }
            },
        };
    }
</script>
@endsection
