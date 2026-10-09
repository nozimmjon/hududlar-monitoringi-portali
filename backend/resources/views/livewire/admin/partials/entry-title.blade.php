{{-- The title cell of a measure row: title, then a quiet second line only when there is something to
     say (an overdue or unusual deadline, the state of this month's values), then the document's own
     detail lines behind «Батафсил». Expects $m, $deadline, $dueNote, $stateLabels. --}}
<div class="c-label ttl">
  <div class="t">{{ $m['title'] }}</div>
  <div class="meta">
    @if($dueNote === 'over')
      <span class="over">муддати ўтган · {{ $deadline['label'] }}</span>
    @elseif($dueNote === 'due')
      <span class="due">муддат: {{ $deadline['label'] }}</span>
    @endif
    <span class="ad-state" x-show="mstate({{ $m['id'] }}) !== 'empty'" x-text="mstateLabel({{ $m['id'] }})" x-cloak>{{ $stateLabels[$m['state']] }}</span>
  </div>
  @if($m['details'] !== [])
    <button type="button" class="wr-more" x-on:click="det = !det" :aria-expanded="det">
      <span class="c" :class="det && 'open'">▸</span>
      <span x-text="det ? 'Ёпиш' : 'Батафсил ({{ count($m['details']) }} банд)'">Батафсил ({{ count($m['details']) }} банд)</span>
    </button>
    <div class="wr-details" x-show="det" x-cloak>@foreach($m['details'] as $line)<p>{{ $line }}</p>@endforeach</div>
  @endif
</div>
