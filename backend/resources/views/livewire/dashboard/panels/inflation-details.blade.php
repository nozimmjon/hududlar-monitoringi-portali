@php
    use App\Support\DashboardCatalog;
    $caps = DashboardCatalog::INFLATION_PRICE_CAPS;
    $reported = collect($limits)->contains(fn ($l) => $l['actual'] !== null);
@endphp

<div class="drivers">
    <div class="lagging">
        <div class="lagging-title"><strong>Инфляция чегаралари</strong></div>
        <div class="driver-grid" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
            @foreach($limits as $limit)
                <div class="driver-card">
                    <span>{{ $limit['period'] }}</span>
                    @if($limit['actual'] !== null)
                        {{-- Reported rate leads; inflation is lower-is-better, so at or
                             below the cap is on target. --}}
                        <strong class="{{ $limit['onTarget'] ? 'is-ok' : 'is-over' }}">
                            {{ DashboardCatalog::fmt($limit['actual'], 1) }}%
                        </strong>
                        <small>амалда · режа {{ $limit['cap'] }}</small>
                    @else
                        <strong>{{ $limit['cap'] }}</strong>
                        <small>{{ $limit['note'] }}</small>
                    @endif
                </div>
            @endforeach
        </div>
        @unless($reported)
            <p class="data-note">Амалдаги инфляция маълумоти киритилмаган.</p>
        @endunless
    </div>

    <div class="composition">
        <div class="lagging-title"><strong>Асосий озиқ-овқат нархлари</strong></div>
        <div class="composition-grid">
            @foreach($caps as $cap)
                <button class="component-card product-card" type="button">
                    <span class="product-icon" aria-hidden="true">@include('partials.icon', ['name' => DashboardCatalog::foodIcon($cap['name'])])</span>
                    <span class="product-body">
                        <span class="product-name">{{ $cap['name'] }}</span>
                        <strong class="product-value">{{ $cap['cap'] }}</strong>
                        <small class="product-note">йиллик нарх чегараси</small>
                    </span>
                </button>
            @endforeach
        </div>
    </div>

    @if($sensitiveFoods->isNotEmpty())
        <div class="composition">
            <div class="lagging-title"><strong>Озиқ-овқат балансида эътибор талаб қиладиган маҳсулотлар</strong></div>
            <div class="composition-grid">
                @foreach($sensitiveFoods as $row)
                    @php
                        $ratioPct = ((float) ($row->local_supply_ratio ?? 0)) * 100;
                        $importVal = $row->import_volume !== null
                            ? DashboardCatalog::fmt((float) $row->import_volume, 1) . ' минг т'
                            : '—';
                        $resourceVal = DashboardCatalog::fmt((float) $row->resource_total, 1) . ' минг т';
                    @endphp
                    <button class="component-card product-card" type="button">
                        <span class="product-icon" aria-hidden="true">@include('partials.icon', ['name' => DashboardCatalog::foodIcon($row->product)])</span>
                        <span class="product-body">
                            <span class="product-name">{{ $row->product }}</span>
                            <strong class="product-value">{{ DashboardCatalog::fmt($ratioPct, 1) }}%</strong>
                            <small class="product-note">маҳаллий таъминланиш · ресурс {{ $resourceVal }} · импорт {{ $importVal }}</small>
                        </span>
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    <div class="lagging">
        <div class="lagging-title"><strong>Омборлар туманлар кесимида</strong></div>
        <div class="driver-grid" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
            <div class="driver-card">
                <span>Совутгичли омборлар</span>
                <strong>33 та</strong>
                <small>II чорак: 4 та, 1 300 т · йил: 8 810 т</small>
            </div>
            <div class="driver-card">
                <span>Захира жамғармаси</span>
                <strong>50 млрд сўм</strong>
                <small>йиллик режа</small>
            </div>
        </div>
    </div>
</div>
