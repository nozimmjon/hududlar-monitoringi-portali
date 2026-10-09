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

  {{-- one table; the two groups are bands inside it, so the columns line up across them --}}
  <div class="ad-table" role="table" aria-label="Вилоятлар бўйича ҳолат">
    <div class="ad-trow head" role="row">
      <span class="c-name" role="columnheader">Вилоят</span>
      <span class="c-prog" role="columnheader">Тўлдирилди</span>
      <span class="c-over" role="columnheader">Кечиккан</span>
      <span class="c-last" role="columnheader">Охирги ўзгариш</span>
      <span class="c-act" role="columnheader"></span>
    </div>
    @foreach([['Диққат талаб қилади', $attention], ['Қолганлари', $rest]] as [$heading, $group])
      @if($group !== [])
        <div class="ad-tgroup" role="row" wire:key="grp-{{ $loop->index }}">
          <span role="cell">{{ $heading }} <b class="tnum">{{ count($group) }}</b></span>
        </div>
        @foreach($group as $c)
          @include('livewire.admin.partials.region-row', ['c' => $c])
        @endforeach
      @endif
    @endforeach
  </div>

  <livewire:admin.progress-upload :period="$period" />
</div>
