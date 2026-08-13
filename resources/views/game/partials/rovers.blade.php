<section class="panel">
    <h2 class="label border-b border-edge px-3 py-2">парк роверов</h2>

    <ul>
        @foreach ($rovers as $rover)
            @php $bars = (int) round($rover['battery_percent'] / 12.5); @endphp

            <li class="border-b border-edge/60 last:border-0">
                <button type="button"
                        @click="selectRover({{ $rover['id'] }})"
                        class="w-full px-3 py-2.5 text-left transition-colors hover:bg-edge/25 focus:bg-edge/25 focus:outline-none"
                        :class="selectedRover === {{ $rover['id'] }} ? 'bg-edge/40' : ''"
                        {{ $rover['available'] ? '' : 'disabled' }}>
                    <span class="flex items-baseline justify-between gap-2">
                        <span class="text-sm {{ $rover['available'] ? '' : 'text-dim' }}">
                            <span class="text-amber" x-show="selectedRover === {{ $rover['id'] }}">▸</span>
                            <span x-show="selectedRover !== {{ $rover['id'] }}" class="text-edge">·</span>
                            {{ $rover['name'] }} {{ $rover['class_label'] }}
                        </span>
                        <span class="label {{ $rover['available'] ? '' : 'text-warn' }}">{{ $rover['status_label'] }}</span>
                    </span>

                    {{-- Абсолютный заряд рядом с полосой: у роверов разные
                         ёмкости, и один процент значит у них разное. --}}
                    <span class="mt-1.5 flex items-center gap-2 text-xs text-dim">
                        <span class="tracking-[-0.05em] {{ $rover['battery_percent'] < 30 ? 'text-bad' : 'text-good' }}">{{ str_repeat('■', $bars).str_repeat('□', 8 - $bars) }}</span>
                        <span class="tabular">{{ $rover['battery_level'] }}/{{ $rover['battery_capacity'] }}</span>
                        <span class="text-edge">|</span>
                        <span class="tabular">до {{ $rover['capacity_kg'] }} кг</span>
                    </span>

                    <span class="mt-1 flex items-center gap-2 text-[11px] text-dim">
                        <span class="tabular">запас хода {{ $rover['range'] }} кл</span>
                        <span class="text-edge">|</span>
                        <span class="text-dim/70">{{ $rover['class_note'] }}</span>
                    </span>
                </button>
            </li>
        @endforeach
    </ul>
</section>
