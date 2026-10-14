{{-- One indicator row of a /roadmaps measure card. Expects $r from RoadmapsPage's $lineRows.
     No per-line % cell: the card ring carries the aggregate, the tier bar carries the line. --}}
@php use App\Support\Roadmaps\MeasureDisplay; @endphp
<div class="wr-line t-{{ $r['tier'] }}">
  <span class="lb" title="{{ $r['label'] }}">{{ $r['label'] }}</span>
  <span class="bar" aria-hidden="true"><i style="width:{{ $r['width'] }}%"></i><span class="tick"></span></span>
  <span class="pv tnum"><b>{{ MeasureDisplay::fmt($r['actual']) }}</b> / {{ MeasureDisplay::fmt($r['plan']) }} {{ $r['unit'] }}</span>
</div>
