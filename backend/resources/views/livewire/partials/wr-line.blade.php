{{-- One indicator row of a /roadmaps measure card. Expects $r from RoadmapsPage's $lineRows.
     No per-line % cell: the card ring carries the aggregate, the tier bar carries the line.
     A line nobody has reported on shows the plan alone («РЕЖА 4,3 км») over a dotted track —
     not «— / 4,3 км» beside an empty slider, which read as a broken control. --}}
@php use App\Support\Roadmaps\MeasureDisplay; @endphp
<div class="wr-line t-{{ $r['tier'] }}">
  <span class="lb" title="{{ $r['label'] }}">{{ $r['label'] }}</span>
  <span class="bar" aria-hidden="true"><i style="width:{{ $r['width'] }}%"></i><span class="tick"></span></span>
  @if($r['actual'] === null)
    <span class="pv tnum"><span class="pl">режа</span> <b>{{ MeasureDisplay::fmt($r['plan']) }}</b> {{ $r['unit'] }}</span>
  @else
    <span class="pv tnum"><b>{{ MeasureDisplay::fmt($r['actual']) }}</b> / {{ MeasureDisplay::fmt($r['plan']) }} {{ $r['unit'] }}</span>
  @endif
</div>
