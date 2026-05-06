@php
    use App\Support\DashboardCatalog;
    $caps = DashboardCatalog::INFLATION_PRICE_CAPS;
    $limits = DashboardCatalog::INFLATION_LIMITS;
    $warehouseCount = $warehouses->count();
@endphp

<div class="drivers">
    <div class="lagging">
        <div class="lagging-title"><strong>Инфляция чегаралари</strong></div>
        <div class="driver-grid" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
            @foreach($limits as $limit)
                <div class="driver-card">
                    <span>{{ $limit['period'] }}</span>
                    <strong>{{ $limit['cap'] }}</strong>
                    <small>{{ $limit['note'] }}</small>
                </div>
            @endforeach
        </div>
        <p class="data-note">Амалдаги инфляция маълумоти киритилмаган.</p>
    </div>

    <div class="composition">
        <div class="lagging-title"><strong>Асосий озиқ-овқат нархлари</strong></div>
        <div class="composition-grid">
            @foreach($caps as $cap)
                <button class="component-card product-card" type="button">
                    <span class="product-icon" aria-hidden="true">🥚</span>
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
                    <button class="component-card product-card" type="button">
                        <span class="product-icon" aria-hidden="true">🥬</span>
                        <span class="product-body">
                            <span class="product-name">{{ $row->product }}</span>
                            <strong class="product-value">{{ number_format(((float) $row->local_supply_ratio) * 100, 1) }}%</strong>
                            <small class="product-note">маҳаллий таъминланиш · ресурс {{ number_format((float) $row->resource_total, 1) }} минг т · импорт {{ $row->import_volume !== null ? number_format((float) $row->import_volume, 1) : '—' }} минг т</small>
                        </span>
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    @if($warehouseCount > 0)
        <div class="lagging">
            <div class="lagging-title"><strong>Омборлар туманлар кесимида</strong></div>
            <div class="driver-grid" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
                <div class="driver-card">
                    <span>Совутгичли омборлар</span>
                    <strong>{{ $warehouseCount }} та</strong>
                    <small>туман кесимида</small>
                </div>
                <div class="driver-card">
                    <span>Захира жамғармаси</span>
                    <strong>50 млрд сўм</strong>
                    <small>йиллик режа</small>
                </div>
            </div>
        </div>
    @endif

    <p class="finance-source">Манба: 2.1-2.2-жадваллар ва кафолат хати II-бўлим.</p>
</div>
