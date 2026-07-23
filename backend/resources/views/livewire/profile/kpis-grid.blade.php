<article class="panel profile-secondary">
    <div class="panel-head">
        <div>
            <h3>Шу туман бўйича KPIлар</h3>
            <p>Кўрсаткични босинг: юқоридаги профиль шу KPIга мослашади.</p>
        </div>
        <span class="chip blue">{{ $district->name_full }}</span>
    </div>
    <div class="panel-body">
        <div class="district-kpis">
            @foreach($availableKpis as $i)
                @php
                    $fact = $facts->get($i->code);
                    $value = match (true) {
                        $fact?->pct_of_plan !== null => number_format((float) $fact->pct_of_plan, 1, ',', ' ') . '%',
                        $fact?->growth_pct !== null  => number_format((float) $fact->growth_pct, 1, ',', ' ') . '%',
                        $fact?->plan_value !== null  => number_format((float) $fact->plan_value, 1, ',', ' '),
                        default                      => '—',
                    };
                    $note = \App\Support\DashboardCatalog::unitLabel($i->default_unit);
                @endphp
                <button class="district-kpi {{ $i->code === $kpi ? 'active' : '' }}"
                        wire:click="selectKpi('{{ $i->code }}')"
                        type="button">
                    <span>{{ $i->label_short }}</span>
                    <strong>{{ $value }}</strong>
                    <small>{{ $note }}</small>
                </button>
            @endforeach
        </div>
    </div>
</article>
