{{-- Custom dropdown (native <select> popups can't be styled). Skins: dark sidebar (base)
     and light .wr-topbar — see the Region Switcher block in portal.css. --}}
<div class="region-switcher"
     x-data="{
         open: false,
         toggle() {
             this.open = ! this.open;
             if (this.open) this.$nextTick(() => (this.$refs.pop.querySelector('.rs-opt.on') ?? this.$refs.pop.querySelector('.rs-opt'))?.focus());
         },
         close(focusBtn = false) {
             if (! this.open) return;
             this.open = false;
             if (focusBtn) this.$refs.btn.focus();
         },
         move(d) {
             const os = [...this.$refs.pop.querySelectorAll('.rs-opt')];
             const i = os.indexOf(document.activeElement);
             (os[i + d] ?? os[d > 0 ? 0 : os.length - 1])?.focus();
         },
     }"
     x-on:click.outside="close()"
     x-on:keydown.escape.stop="close(true)"
     x-on:keydown.arrow-down.prevent="open ? move(1) : toggle()"
     x-on:keydown.arrow-up.prevent="open ? move(-1) : toggle()">
    <span class="rs-label" id="region-switcher-label">
        <svg viewBox="0 0 256 256" width="13" height="13" fill="currentColor" aria-hidden="true">
            <path d="M128,64a40,40,0,1,0,40,40A40,40,0,0,0,128,64Zm0,64a24,24,0,1,1,24-24A24,24,0,0,1,128,128Zm0-112a88.1,88.1,0,0,0-88,88c0,31.4,14.51,64.68,42,96.25a254.19,254.19,0,0,0,41.45,38.3,8,8,0,0,0,9.18,0A254.19,254.19,0,0,0,174,200.25c27.45-31.57,42-64.85,42-96.25A88.1,88.1,0,0,0,128,16Zm0,206c-16.53-13-72-60.75-72-118a72,72,0,0,1,144,0C200,161.23,144.53,209,128,222Z"/>
        </svg>
        Вилоят
    </span>
    <button type="button" class="rs-btn" x-ref="btn" x-on:click="toggle()"
            aria-haspopup="listbox" :aria-expanded="open" aria-labelledby="region-switcher-label">
        <span class="rs-cur">{{ $current->name_full }}</span>
        <svg class="rs-chev" :class="open && 'flip'" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <polyline points="6 9 12 15 18 9"/>
        </svg>
    </button>
    {{-- CSS animation, not x-transition: Alpine transitions wait on rAF and freeze
         mid-toggle in a backgrounded tab, leaving the popup stuck half-open. --}}
    <div class="rs-pop" x-ref="pop" x-show="open" x-cloak
         role="listbox" aria-labelledby="region-switcher-label">
        @foreach($regions as $r)
            <button type="button" class="rs-opt {{ $r->code === $regionCode ? 'on' : '' }}"
                    role="option" aria-selected="{{ $r->code === $regionCode ? 'true' : 'false' }}"
                    title="{{ $r->name_full }}"
                    wire:key="rs-{{ $r->code }}" wire:click="select({{ $r->code }})">
                <span class="t">{{ $r->name_full }}</span>
                <svg class="ck" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
            </button>
        @endforeach
    </div>
</div>
