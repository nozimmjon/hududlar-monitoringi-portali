<div>
    {{-- Period tabs --}}
    <div class="segmented" style="margin-bottom: 24px;">
        @foreach($periodLabels as $p => $label)
            <button class="seg-btn {{ $period === $p ? 'active' : '' }}"
                    wire:click="$set('period', '{{ $p }}')"
                    type="button">{{ $label }}</button>
        @endforeach
    </div>

    @if($facts->isEmpty())
        <div style="padding: 32px; text-align: center; color: var(--muted);">
            <p>Маълумот топилмади. Базани тўлдириш учун <code>import:promote</code> буйруғини ишга туширинг.</p>
        </div>
    @else
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px;">
            @foreach(['budget', 'budget_investment', 'investment', 'export'] as $code)
                @php
                    $fact = $facts->get($code);
                    $ind  = $indicators->get($code);
                    if (! $ind) continue;
                @endphp
                <div style="background: var(--paper); border: 1px solid var(--line); border-radius: 12px; padding: 20px;">
                    <p style="margin: 0 0 8px; font-size: 12px; color: var(--muted); text-transform: uppercase; letter-spacing: .05em;">
                        {{ $ind->label_short }}
                    </p>
                    <p style="margin: 0 0 4px; font-size: 13px; color: var(--muted);">{{ $ind->label_full }}</p>

                    @if(! $fact)
                        <p style="margin: 12px 0 0; color: var(--muted); font-size: 13px;">Маълумот йўқ</p>
                    @else
                        <div style="margin-top: 12px; display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                            @if($fact->plan_value !== null)
                                <div style="padding: 8px; background: var(--surface); border-radius: 6px;">
                                    <p style="margin: 0; font-size: 11px; color: var(--muted);">Режа</p>
                                    <p style="margin: 2px 0 0; font-size: 15px; font-weight: 700;">
                                        {{ number_format((float) $fact->plan_value, 1) }}
                                        <span style="font-size: 11px; font-weight: 400;">{{ $fact->unit }}</span>
                                    </p>
                                </div>
                            @endif
                            @if($fact->expected_value !== null)
                                <div style="padding: 8px; background: var(--surface); border-radius: 6px;">
                                    <p style="margin: 0; font-size: 11px; color: var(--muted);">Кутилаётган</p>
                                    <p style="margin: 2px 0 0; font-size: 15px; font-weight: 700;">
                                        {{ number_format((float) $fact->expected_value, 1) }}
                                        <span style="font-size: 11px; font-weight: 400;">{{ $fact->unit }}</span>
                                    </p>
                                </div>
                            @endif
                            @if($fact->actual_hokimyat !== null)
                                <div style="padding: 8px; background: var(--surface); border-radius: 6px;">
                                    <p style="margin: 0; font-size: 11px; color: var(--muted);">Амалда</p>
                                    <p style="margin: 2px 0 0; font-size: 15px; font-weight: 700;">
                                        {{ number_format((float) $fact->actual_hokimyat, 1) }}
                                        <span style="font-size: 11px; font-weight: 400;">{{ $fact->unit }}</span>
                                    </p>
                                </div>
                            @endif
                            @if($fact->pct_of_plan !== null)
                                <div style="padding: 8px; background: var(--surface); border-radius: 6px;">
                                    <p style="margin: 0; font-size: 11px; color: var(--muted);">Ижро %</p>
                                    <p style="margin: 2px 0 0; font-size: 15px; font-weight: 700;">
                                        {{ number_format((float) $fact->pct_of_plan, 2) }}
                                    </p>
                                </div>
                            @endif
                            @if($fact->growth_pct !== null)
                                <div style="padding: 8px; background: var(--surface); border-radius: 6px;">
                                    <p style="margin: 0; font-size: 11px; color: var(--muted);">Ўсиш</p>
                                    <p style="margin: 2px 0 0; font-size: 15px; font-weight: 700;">
                                        {{ number_format((float) $fact->growth_pct, 1) }}%
                                    </p>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
