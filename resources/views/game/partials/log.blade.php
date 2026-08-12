@php
    $tone = [
        'dispatch' => 'text-ink',
        'delivery' => 'text-good',
        'delay' => 'text-warn',
        'expired' => 'text-bad',
    ];
@endphp

<section class="panel">
    <h2 class="label border-b border-edge px-3 py-2">журнал смены</h2>

    @if ($events === [])
        <p class="px-3 py-4 text-xs text-dim">Записей пока нет. Отправьте ровер, чтобы начать смену.</p>
    @else
        <ul class="max-h-52 overflow-y-auto">
            @foreach ($events as $event)
                <li class="flex gap-3 border-b border-edge/40 px-3 py-1.5 text-xs last:border-0">
                    <span class="tabular shrink-0 text-dim">{{ str_pad($event['day'], 2, '0', STR_PAD_LEFT) }}</span>
                    <span class="{{ $tone[$event['type']] ?? 'text-ink' }}">{{ $event['message'] }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</section>
