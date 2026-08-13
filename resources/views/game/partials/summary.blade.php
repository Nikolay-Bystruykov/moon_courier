@php
    $won = $game->status === 'won';
    $score = App\Domain\Lunar\Scoring::total($game->credits, $fleetValue, $game->reputation);
@endphp

<section class="panel {{ $won ? 'border-good' : 'border-bad' }} p-5">
    <h2 class="label {{ $won ? 'text-good' : 'text-bad' }}">
        {{ $won ? 'смена завершена' : 'база потеряла доверие' }}
    </h2>

    <p class="mt-2 max-w-2xl font-sans text-sm text-dim">
        {{ $won
            ? 'Четырнадцать суток отработаны, снабжение аванпостов не сорвано.'
            : 'Рейтинг базы упал до нуля: подрядчик отстранён от снабжения.' }}
    </p>

    <dl class="mt-5 flex flex-wrap gap-x-10 gap-y-4">
        @foreach ([
            ['итог', number_format($score, 0, '.', ' ')],
            ['кредиты', number_format($game->credits, 0, '.', ' ')],
            ['парк', number_format($fleetValue, 0, '.', ' ')],
            ['рейтинг', $game->reputation],
            ['доставлено', $delivered],
        ] as [$label, $value])
            <div>
                <dt class="label">{{ $label }}</dt>
                <dd class="tabular mt-1 text-2xl">{{ $value }}</dd>
            </div>
        @endforeach
    </dl>

    <p class="mt-4 font-sans text-xs text-dim">
        Итог складывается из кредитов, стоимости парка и рейтинга базы,
        умноженного на {{ App\Domain\Lunar\Scoring::REPUTATION_WEIGHT }}.
        Купленная техника входит в счёт, поэтому вложения в гараж не пропадают.
    </p>

    <form method="POST" action="{{ route('game.new') }}" class="mt-6">
        @csrf
        <button type="submit"
                class="border border-amber px-4 py-2.5 text-[11px] uppercase tracking-[0.2em] text-amber transition-colors hover:bg-amber hover:text-void focus:bg-amber focus:text-void focus:outline-none">
            ▸ новая смена
        </button>
    </form>
</section>
