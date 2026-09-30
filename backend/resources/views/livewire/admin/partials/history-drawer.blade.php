{{-- Side panel with one measure's latest changes. Expects $history (Collection) and $historyTitle from RoadmapEntry. --}}
@php use App\Models\RoadmapChangeLog; @endphp
<aside class="ad-hist" role="dialog" aria-label="Ўзгаришлар тарихи">
  <div class="h">
    <b>Ўзгаришлар тарихи</b>
    <button type="button" class="ad-ico" wire:click="closeHistory" aria-label="Ёпиш">✕</button>
  </div>
  <p class="t">{{ $historyTitle }}</p>
  @forelse($history as $h)
    <div class="e" wire:key="h-{{ $h->id }}">
      <div class="w"><span class="tnum">{{ $h->created_at->timezone('Asia/Tashkent')->format('d.m.Y H:i') }}</span> · {{ $h->user?->name ?? 'терминал' }}</div>
      @php $lineLabel = $h->line_label ?? $h->line?->label; @endphp
      <div class="a">{{ RoadmapChangeLog::ACTION_LABELS[$h->action] ?? $h->action }}@if($lineLabel !== null) · {{ mb_strimwidth($lineLabel, 0, 60, '…') }}@endif</div>
      @if($h->old_value !== null || $h->new_value !== null)
        <div class="v"><s>{{ $h->old_value ?? '—' }}</s> → <b>{{ $h->new_value ?? '—' }}</b></div>
      @endif
    </div>
  @empty
    <p class="muted">Ҳали ўзгариш йўқ.</p>
  @endforelse
</aside>
