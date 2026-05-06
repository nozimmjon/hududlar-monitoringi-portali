<div>
    @if(! $districtCode)
        <div style="padding: 32px; text-align: center; color: var(--muted);">
            <p>Туман танланмаган. <a href="{{ route('districts') }}">Туманлар</a> саҳифасидан туманни танланг.</p>
        </div>
    @elseif(! $district)
        <div style="padding: 32px; text-align: center; color: var(--muted);">
            <p>Туман топилмади: <code>{{ $districtCode }}</code></p>
        </div>
    @else
        <div style="margin-bottom: 24px;">
            <h3 style="margin: 0 0 4px; font-size: 20px;">{{ $district->name_full ?? $district->name_short }}</h3>
            <p style="color: var(--muted); margin: 0;">Андижон вилояти · 2026 йил · Йиллик кўрсаткичлар</p>
        </div>

        @if($facts->isEmpty())
            <div style="padding: 24px; color: var(--muted);">
                <p>Мазкур туман учун маълумот топилмади.</p>
            </div>
        @else
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 12px;">
                @foreach($indicators as $code => $ind)
                    @php $fact = $facts->get($code); if (! $fact) continue; @endphp
                    <div style="background: var(--paper); border: 1px solid var(--line); border-radius: 10px; padding: 14px 16px;">
                        <p style="margin: 0 0 6px; font-size: 12px; color: var(--muted); text-transform: uppercase; letter-spacing: .04em;">{{ $ind->label_short }}</p>
                        <div style="font-size: 18px; font-weight: 700; color: var(--ink);">
                            @if($fact->growth_pct !== null)
                                {{ number_format((float) $fact->growth_pct, 1) }}%
                            @elseif($fact->plan_value !== null)
                                {{ number_format((float) $fact->plan_value, 1) }}
                                <span style="font-size: 12px; font-weight: 400; color: var(--muted);">{{ $fact->unit }}</span>
                            @else
                                —
                            @endif
                        </div>
                        @if($fact->is_sentinel)
                            <p style="margin: 4px 0 0; font-size: 11px; color: var(--muted);">{{ $fact->sentinel_label }}</p>
                        @endif
                        @if($fact->plan_value !== null && $fact->actual_hokimyat !== null)
                            <p style="margin: 4px 0 0; font-size: 11px; color: var(--muted);">
                                Режа: {{ number_format((float) $fact->plan_value, 1) }} · Амалда: {{ number_format((float) $fact->actual_hokimyat, 1) }}
                            </p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        <div style="margin-top: 20px;">
            <a href="{{ route('districts') }}" style="color: var(--muted); font-size: 13px;">← Туманлар рўйхатига қайтиш</a>
        </div>
    @endif
</div>
