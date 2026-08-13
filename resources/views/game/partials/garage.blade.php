<section class="panel">
    <h2 class="label border-b border-edge px-3 py-2">гараж базы</h2>

    <div class="border-b border-edge/60 p-3">
        <p class="label mb-2">пополнить парк</p>

        @if ($garage['full'])
            <p class="text-xs text-dim">Парк заполнен: база обслуживает не более {{ $garage['fleet_limit'] }} роверов.</p>
        @else
            <ul class="space-y-1.5">
                @foreach ($garage['models'] as $model)
                    <li>
                        <form method="POST" action="{{ route('garage.buy') }}">
                            @csrf
                            <input type="hidden" name="rover_class" value="{{ $model['value'] }}">
                            <button type="submit"
                                    class="flex w-full items-baseline justify-between gap-2 border border-edge px-2.5 py-1.5 text-xs transition-colors hover:border-amber hover:text-amber focus:border-amber focus:outline-none disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:border-edge disabled:hover:text-ink"
                                    {{ $model['affordable'] ? '' : 'disabled' }}>
                                <span>{{ $model['label'] }}</span>
                                <span class="tabular text-dim">{{ $model['capacity_kg'] }} кг · {{ $model['battery'] }} ёмк</span>
                                <span class="tabular">{{ number_format($model['price'], 0, '.', ' ') }} кр</span>
                            </button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- Улучшения ставятся на выбранный ровер: так не нужно дублировать
         кнопки в каждой строке парка. --}}
    <div class="p-3">
        <p class="label mb-2">улучшить выбранный ровер</p>

        <p class="text-xs text-dim" x-show="!selectedRover">
            Выберите ровер в списке парка.
        </p>

        <ul class="space-y-1.5" x-show="selectedRover" x-cloak>
            @foreach ($garage['upgrades'] as $upgrade)
                <li>
                    <form method="POST" action="{{ route('garage.upgrade') }}">
                        @csrf
                        <input type="hidden" name="rover_id" :value="selectedRover">
                        <input type="hidden" name="upgrade" value="{{ $upgrade['value'] }}">
                        <button type="submit"
                                class="flex w-full items-baseline justify-between gap-2 border border-edge px-2.5 py-1.5 text-xs transition-colors hover:border-amber hover:text-amber focus:border-amber focus:outline-none disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:border-edge disabled:hover:text-ink"
                                :disabled="installed[selectedRover]?.includes('{{ $upgrade['value'] }}') || {{ $upgrade['affordable'] ? 'false' : 'true' }}">
                            <span x-text="installed[selectedRover]?.includes('{{ $upgrade['value'] }}') ? '{{ $upgrade['label'] }} — установлен' : '{{ $upgrade['label'] }}'"></span>
                            <span class="tabular text-dim">{{ $upgrade['note'] }}</span>
                            <span class="tabular">{{ number_format($upgrade['cost'], 0, '.', ' ') }} кр</span>
                        </button>
                    </form>
                </li>
            @endforeach
        </ul>
    </div>
</section>
