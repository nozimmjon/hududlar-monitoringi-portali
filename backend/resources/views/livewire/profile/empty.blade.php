@if(! $districtCode)
    <div style="padding: 32px; text-align: center; color: var(--muted);">
        <p>Туман танланмаган. <a href="{{ route('districts') }}">Туманлар</a> саҳифасидан туманни танланг.</p>
    </div>
@else
    <div style="padding: 32px; text-align: center; color: var(--muted);">
        <p>Туман топилмади: <code>{{ $districtCode }}</code></p>
    </div>
@endif
