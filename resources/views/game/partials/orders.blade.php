<section class="panel">
    <h2 class="label border-b border-edge px-3 py-2">заявки на доставку</h2>

    @if ($orders === [])
        <p class="px-3 py-4 text-xs text-dim">
            Свободных заявок нет. Завершите сутки, чтобы принять новые.
        </p>
    @else
        <ul>
            @foreach ($orders as $order)
                <li class="border-b border-edge/60 last:border-0">
                    <button type="button"
                            @click="selectOrder({{ $order['id'] }}, {{ $order['outpost_id'] }})"
                            class="w-full px-3 py-2.5 text-left transition-colors hover:bg-edge/25 focus:bg-edge/25 focus:outline-none"
                            :class="selectedOrder === {{ $order['id'] }} ? 'bg-edge/40' : ''">
                        <span class="flex items-baseline justify-between gap-2">
                            <span class="text-sm">
                                <span class="text-amber" x-show="selectedOrder === {{ $order['id'] }}">▸</span>
                                <span x-show="selectedOrder !== {{ $order['id'] }}" class="text-edge">·</span>
                                {{ $order['outpost'] }}
                            </span>
                            <span class="tabular text-xs text-amber">{{ number_format($order['reward'], 0, '.', ' ') }} кр</span>
                        </span>

                        <span class="mt-1.5 flex gap-4 text-xs text-dim">
                            <span class="tabular">{{ $order['weight_kg'] }} кг</span>
                            <span class="tabular {{ $order['days_left'] <= 1 ? 'text-bad' : ($order['days_left'] <= 2 ? 'text-warn' : '') }}">
                                срок {{ $order['days_left'] }} сут
                            </span>
                        </span>
                    </button>
                </li>
            @endforeach
        </ul>
    @endif
</section>
