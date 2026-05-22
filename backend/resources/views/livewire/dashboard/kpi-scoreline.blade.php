<div class="{{ trim('scoreline execution-strip '.($module === 'macro' ? 'is-macro' : '')) }}">
    <div class="scoreline-copy">
        <span>Чора-тадбирлар ижроси</span>
        <strong>{{ $scope }}</strong>
        <small>Ушбу йўналишга тегишли чора-тадбирлар ҳолати.</small>
    </div>
    <div class="exec-status-grid">
        <a class="exec-status-pill" href="{{ route('tasks') }}?module={{ $module }}">
            <span>Жами</span>
            <strong>{{ $total }}</strong>
        </a>
        <a class="exec-status-pill green" href="{{ route('tasks') }}?module={{ $module }}&status=done">
            <span>Бажарилди</span>
            <strong>{{ $done }}</strong>
        </a>
        <a class="exec-status-pill red" href="{{ route('tasks') }}?module={{ $module }}&status=open">
            <span>Бажарилмади</span>
            <strong>{{ $open }}</strong>
        </a>
    </div>
    <div class="exec-progress-box">
        <div class="exec-donut" style="--pct:{{ $pct }}"><strong>{{ $pct }}%</strong></div>
        <small>бажарилиш</small>
    </div>
</div>
