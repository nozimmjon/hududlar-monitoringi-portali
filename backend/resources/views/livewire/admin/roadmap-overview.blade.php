<div class="ad-shell ad-overview">
  <header class="ad-head">
    <div class="ad-head-row">
      <div class="ad-titles">
        <h2 class="ad-title">Йўл хариталари · мониторинг</h2>
      </div>
      {{-- no month control here for now: the list shows the current month, and ?davr= still opens another;
           the entry page and the upload drawer keep their own month selects --}}
    </div>
  </header>

  {{-- one table, sorted as a worklist: most overdue first, then the least filled --}}
  <div class="ad-table" role="table" aria-label="Вилоятлар бўйича ҳолат">
    <div class="ad-trow head" role="row">
      <span class="c-name" role="columnheader">Вилоят</span>
      <span class="c-prog" role="columnheader">Бажарилди</span>
      <span class="c-over" role="columnheader">Кечиккан</span>
      <span class="c-last" role="columnheader">Охирги ўзгариш</span>
      <span class="c-act" role="columnheader"></span>
    </div>
    @foreach($cards as $c)
      @include('livewire.admin.partials.region-row', ['c' => $c])
    @endforeach
  </div>

  <livewire:admin.progress-upload :period="$period" />
</div>
