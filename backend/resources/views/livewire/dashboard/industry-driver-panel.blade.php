<aside class="industry-driver-panel" aria-label="Саноат драйверлари">
    <div class="industry-driver-head">
        <strong>Саноат драйверлари</strong>
    </div>
    <div class="industry-driver-list">
        @foreach($industryDrivers as $item)
            <div class="industry-driver-card {{ $item['cls'] }}">
                <span class="driver-icon {{ $item['cls'] }}">
                    @include('partials.icon', ['name' => $item['icon']])
                </span>
                <span class="industry-driver-body">
                    <span class="industry-driver-title">
                        <strong>{{ $item['title'] }}</strong>
                        <span>{{ $item['desc'] }}</span>
                    </span>
                    <span class="industry-driver-metrics">
                        <span class="industry-driver-metric">
                            <span>I ярим йиллик</span>
                            <strong>{{ $item['h1'] }}</strong>
                            @if($item['h1Note'] !== '')
                                <small>{{ $item['h1Note'] }}</small>
                            @endif
                        </span>
                        <span class="industry-driver-divider" aria-hidden="true"></span>
                        <span class="industry-driver-metric">
                            <span>Йиллик кутилиш</span>
                            <strong>{{ $item['year'] }}</strong>
                            @if($item['yearNote'] !== '')
                                <small>{{ $item['yearNote'] }}</small>
                            @endif
                        </span>
                    </span>
                </span>
            </div>
        @endforeach
    </div>
</aside>
