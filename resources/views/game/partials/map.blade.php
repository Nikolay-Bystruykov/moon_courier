@php
    $cell = 32;
    // Оттенки грунта: чем труднее клетка, тем светлее и холоднее пыль,
    // вечная тень — почти чёрная.
    $fill = [
        'mare' => '#151b25',
        'regolith' => '#1d2532',
        'crater' => '#2a3444',
        'rille' => '#3a2c33',
        'shadow' => '#0c1016',
    ];
@endphp

<section class="panel p-4">
    <header class="mb-3 flex flex-wrap items-baseline justify-between gap-3">
        <h2 class="label">карта поверхности</h2>
        <p class="label">клетка 5 км · база в квадрате {{ $base['x'] }}·{{ $base['y'] }}</p>
    </header>

    <svg viewBox="0 0 {{ $map['width'] * $cell }} {{ $map['height'] * $cell }}"
         class="w-full"
         role="img"
         aria-label="Карта лунной поверхности с базой, аванпостами и роверами">
        @foreach ($map['tiles'] as $tile)
            <rect class="cell"
                  x="{{ $tile['x'] * $cell }}" y="{{ $tile['y'] * $cell }}"
                  width="{{ $cell }}" height="{{ $cell }}"
                  fill="{{ $fill[$tile['terrain']] }}"
                  stroke="#0a0d12" stroke-width="1">
                <title>{{ $tile['label'] }} ({{ $tile['x'] }}·{{ $tile['y'] }})</title>
            </rect>
        @endforeach

        <template x-for="step in route" :key="step.x + ':' + step.y">
            <rect :x="step.x * {{ $cell }}" :y="step.y * {{ $cell }}"
                  width="{{ $cell }}" height="{{ $cell }}"
                  fill="#f0a92e" fill-opacity="0.16"
                  stroke="#f0a92e" stroke-opacity="0.5" stroke-width="1"/>
        </template>

        <g>
            <rect x="{{ $base['x'] * $cell + 7 }}" y="{{ $base['y'] * $cell + 7 }}"
                  width="{{ $cell - 14 }}" height="{{ $cell - 14 }}"
                  fill="#dbe4ef"/>
            <text x="{{ $base['x'] * $cell + $cell / 2 }}" y="{{ $base['y'] * $cell - 5 }}"
                  text-anchor="middle" fill="#dbe4ef"
                  font-size="9" font-family="IBM Plex Mono, monospace" letter-spacing="1.5"
                  paint-order="stroke" stroke="#0a0d12" stroke-width="3">БАЗА</text>
        </g>

        @foreach ($outposts as $outpost)
            @php
                $cx = $outpost['x'] * $cell + $cell / 2;
                $cy = $outpost['y'] * $cell + $cell / 2;
                $active = $outpost['pending'] > 0;
                // Подпись уходит вверх у нижнего края карты, а совпадающие
                // уровни разводятся по вертикали.
                $offset = 13 + $outpost['label_level'] * 11;
                $ly = $outpost['label_above'] ? $cy - $offset + 3 : $cy + $offset;
            @endphp

            <g class="cursor-pointer" @click="selectOutpost({{ $outpost['id'] }})"
               role="button" tabindex="0"
               @keydown.enter="selectOutpost({{ $outpost['id'] }})">
                <circle cx="{{ $cx }}" cy="{{ $cy }}" r="6.5"
                        fill="{{ $active ? '#f0a92e' : 'none' }}"
                        fill-opacity="{{ $active ? '0.18' : '0' }}"
                        stroke="{{ $active ? '#f0a92e' : '#6b7d93' }}" stroke-width="1.5"/>
                <circle cx="{{ $cx }}" cy="{{ $cy }}" r="11"
                        fill="none" stroke="#f0a92e" stroke-width="1"
                        :stroke-opacity="selectedOutpost === {{ $outpost['id'] }} ? 0.9 : 0"/>

                {{-- Обводка цветом фона отделяет подпись от клеток под ней. --}}
                <text x="{{ $cx }}" y="{{ $ly }}"
                      text-anchor="middle" font-size="9.5"
                      font-family="IBM Plex Mono, monospace"
                      paint-order="stroke" stroke="#12171f" stroke-width="3.5"
                      fill="{{ $active ? '#f0a92e' : '#6b7d93' }}">{{ $outpost['name'] }}</text>

                <title>{{ $outpost['name'] }}: плечо {{ number_format($outpost['route_cost'], 1) }}, заявок {{ $outpost['pending'] }}</title>
            </g>
        @endforeach

        @foreach ($roversOnMap as $rover)
            @php
                // На базе роверы выстраиваются в ряд, в рейсе — стоят у цели.
                $rx = $rover['en_route']
                    ? $rover['x'] * $cell + $cell - 4
                    : $rover['x'] * $cell + 4 + $rover['slot'] * 9;
                $ry = $rover['en_route']
                    ? $rover['y'] * $cell + 6
                    : $rover['y'] * $cell + $cell - 4;
            @endphp

            <text x="{{ $rx }}" y="{{ $ry }}"
                  text-anchor="{{ $rover['en_route'] ? 'end' : 'start' }}"
                  font-size="8.5" font-family="IBM Plex Mono, monospace"
                  paint-order="stroke" stroke="#0a0d12" stroke-width="3"
                  fill="{{ $rover['en_route'] ? '#f0a92e' : '#5fa87d' }}">{{ $rover['name'] }}</text>
        @endforeach
    </svg>

    <ul class="mt-4 flex flex-wrap items-center gap-x-5 gap-y-2 border-t border-edge pt-3">
        @foreach ($legend as $item)
            <li class="flex items-center gap-2 text-[11px] text-dim">
                <span class="inline-block h-3 w-3 border border-edge" style="background: {{ $fill[$item['value']] }}"></span>
                {{ $item['label'] }}
                <span class="tabular text-dim/70">×{{ number_format($item['cost'], 1) }}</span>
            </li>
        @endforeach

        <li class="flex items-center gap-2 text-[11px] text-dim">
            <span class="text-good">R</span> на базе
        </li>
        <li class="flex items-center gap-2 text-[11px] text-dim">
            <span class="text-amber">R</span> в рейсе
        </li>
    </ul>
</section>
