<div>
    {{-- Controls --}}
    <div style="display:flex; gap:12px; align-items:center; margin-bottom:20px; flex-wrap:wrap;">
        <div class="segmented">
            @foreach($periodLabels as $p => $label)
                <button class="seg-btn {{ $period === $p ? 'active' : '' }}"
                        wire:click="$set('period', '{{ $p }}')"
                        type="button">{{ $label }}</button>
            @endforeach
        </div>

        <select wire:model.live="indicatorCode"
                style="padding:6px 10px; border:1px solid var(--line); border-radius:8px; font-size:13px;">
            @foreach($availableIndicators as $code => $label)
                <option value="{{ $code }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>

    @if($facts->isEmpty())
        <div style="padding: 32px; text-align: center; color: var(--muted);">
            <p>Маълумот топилмади. Базани тўлдириш учун <code>import:promote</code> буйруғини ишга туширинг.</p>
        </div>
    @else
        {{-- Rollup row --}}
        @if($rollup)
            <div class="kpi-tile" style="margin-bottom:12px; padding:12px 16px; background:var(--paper); border-radius:10px; border:1px solid var(--line);">
                <strong>Андижон вилояти (жами)</strong>
                <span style="margin-left:16px; color:var(--muted);">
                    @if($rollup->growth_pct !== null)
                        Ўсиш: {{ number_format((float)$rollup->growth_pct, 1) }}%
                    @endif
                    @if($rollup->plan_value !== null)
                        · Режа: {{ number_format((float)$rollup->plan_value, 1) }} {{ $rollup->unit }}
                    @endif
                    @if($rollup->actual_hokimyat !== null)
                        · Амалда: {{ number_format((float)$rollup->actual_hokimyat, 1) }} {{ $rollup->unit }}
                    @endif
                </span>
            </div>
        @endif

        {{-- District table --}}
        <table style="width:100%; border-collapse:collapse; font-size:13px;">
            <thead>
                <tr style="border-bottom:1px solid var(--line); text-align:left;">
                    <th style="padding:8px 12px; color:var(--muted); font-weight:500;">Туман</th>
                    <th style="padding:8px 12px; color:var(--muted); font-weight:500;">Режа</th>
                    <th style="padding:8px 12px; color:var(--muted); font-weight:500;">Амалда</th>
                    <th style="padding:8px 12px; color:var(--muted); font-weight:500;">Ўсиш %</th>
                    <th style="padding:8px 12px; color:var(--muted); font-weight:500;">Ижро %</th>
                </tr>
            </thead>
            <tbody>
                @foreach($districts as $districtCode => $district)
                    @php $fact = $facts->get($districtCode); @endphp
                    <tr style="border-bottom:1px solid var(--line);">
                        <td style="padding:8px 12px;">
                            <a href="{{ route('profile') }}?districtCode={{ $districtCode }}"
                               style="color:var(--ink); text-decoration:none; font-weight:500;">
                                {{ $district->name_short }}
                            </a>
                        </td>
                        <td style="padding:8px 12px; color:var(--muted);">
                            {{ $fact && $fact->plan_value !== null ? number_format((float)$fact->plan_value, 1) : '—' }}
                        </td>
                        <td style="padding:8px 12px; color:var(--muted);">
                            {{ $fact && $fact->actual_hokimyat !== null ? number_format((float)$fact->actual_hokimyat, 1) : '—' }}
                        </td>
                        <td style="padding:8px 12px; color:var(--muted);">
                            {{ $fact && $fact->growth_pct !== null ? number_format((float)$fact->growth_pct, 1).'%' : '—' }}
                        </td>
                        <td style="padding:8px 12px; color:var(--muted);">
                            {{ $fact && $fact->pct_of_plan !== null ? number_format((float)$fact->pct_of_plan, 2) : '—' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
