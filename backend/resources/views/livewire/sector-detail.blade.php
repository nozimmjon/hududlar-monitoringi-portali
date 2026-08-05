<div class="sec-shell sec-detail">
    <header class="sec-top on">
        <span class="sec-pline" style="--p:{{ min(1, ($agg['pct'] ?? 0) / 120) }}"></span>
        <div class="sec-tin">
            <a class="sec-back" href="{{ route('sectors') }}">← Барча корхоналар</a>
            <h1>{{ $sector->cardName() }}</h1>
            <span class="sp"></span>
        </div>
    </header>
    <div class="sec-detail-wrap">
        @include('livewire.partials.sector-tasks', [
            'sector'   => $sector,
            'tasks'    => $tasks,
            'counts'   => $counts,
            'agg'      => $agg,
            'filter'   => $filter,
            'expanded' => $expanded,
            'inDrawer' => false,
        ])
    </div>
</div>
