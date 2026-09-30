/* Entry grid for /admin/roadmaps/{code}.
   Holds the edit buffer, computes live % / ring / status, drives keyboard flow and the
   client-side filters, and sends the buffer to Livewire on save. Every value is read
   from the DOM (data-* on the inputs) at evaluation time — Livewire re-keys the cells
   after a write, so nothing here caches server state. `tick` is bumped after each
   server re-render (the `ad-refresh` window event) so DOM-derived counts recompute.

   DOM access goes through `root`, captured once in init(), never through `$root`: the
   view nests x-data on each measure tbody and on the save bar, and inside those scopes
   `$root` is that element, not the grid. `idx` is the per-tick index of that root. */
document.addEventListener('alpine:init', () => {
  Alpine.data('adEntry', () => {
    let root = null;                                   // the component root element, captured in init()
    let idx  = { tick: -1, tbodies: [], byMeasure: new Map(), byLine: new Map(), probe: null };   // DOM index, rebuilt per tick

    return {
      edits: {},        // lineId -> { actual?: string, note?: string } — changed cells only
      invalid: {},      // lineId -> message for a cell that does not parse
      filter: 'all',    // all | unfilled | edited | carry | saved
      search: '',
      saving: false,
      jsToast: '',
      tick: 0,
      _timer: null,
      _unload: null,

      init() {
        root = this.$el;
        window.addEventListener('beforeunload', this._unload = (e) => {
          if (this.editedLines > 0) { e.preventDefault(); e.returnValue = ''; }
        });
      },
      destroy() { window.removeEventListener('beforeunload', this._unload); clearTimeout(this._timer); },

      // ---- numbers (mirror of App\Support\Roadmaps\EntryValue::parse; the grid formats server-side) ----
      parse(raw) {
        const s = String(raw ?? '').trim().replace(/[ \u00A0\u202F\u2009]/g, '').replace(/,/g, '.');
        if (s === '') return null;
        if (!/^-?\d+(\.\d+)?$/.test(s)) throw new Error('рақам эмас');
        const v = parseFloat(s);
        if (v < 0) throw new Error('манфий бўлмасин');
        if (v > 1e12) throw new Error('жуда катта');
        return Math.round(v * 1e6) / 1e6 + 0;   // 6 decimals like the column; + 0 turns -0 into 0 (mirrors EntryValue::parse)
      },
      sameNumber(a, b) { try { return this.parse(a) === this.parse(b); } catch { return false; } },

      // ---- DOM access ----
      /* One pass over the grid per render: the getters below run per measure and per line on every
         keystroke, so each must not re-query the document. Rebuilt whenever `tick` moved — that is
         exactly when Livewire replaced the cells — and whenever the indexed cells were detached
         anyway (selectPeriod re-keys them without an `ad-refresh`), which one probe settles: the
         cell keys all carry $version, so the morph replaces every value cell or none. The memo
         lives in the closure, out of the reactive object, so reading it registers no dependency. */
      _index() {
        if (idx.tick === this.tick && (idx.probe === null || idx.probe.isConnected)) return idx;
        const tbodies = Array.from(root.querySelectorAll('tbody.ad-m'));
        const byMeasure = new Map();
        const byLine = new Map();
        let probe = null;
        for (const tbody of tbodies) byMeasure.set(tbody.dataset.measure, { tbody, actuals: [] });
        for (const el of root.querySelectorAll('input[data-line]')) {
          const m = byMeasure.get(el.dataset.measure);
          if (m && el.dataset.field === 'actual') m.actuals.push(el);   // every line row has one, so this is the line set
          byLine.set(el.dataset.line, { ...(byLine.get(el.dataset.line) || {}), [el.dataset.field]: el });
          probe ??= el;
        }
        idx = { tick: this.tick, tbodies, byMeasure, byLine, probe };
        return idx;
      },
      lineEl(id) { return this._index().byLine.get(String(id))?.actual ?? null; },
      inputs(mid) { return this._index().byMeasure.get(String(mid))?.actuals ?? []; },
      measureEls() { return this._index().tbodies; },
      tbodyOf(mid) { return this._index().byMeasure.get(String(mid))?.tbody ?? null; },
      actualOf(el) {
        const e = this.edits[el.dataset.line];
        if (e && 'actual' in e) { try { return this.parse(e.actual); } catch { return null; } }
        return el.dataset.loaded === '' ? null : parseFloat(el.dataset.loaded);
      },

      // ---- per line ----
      pct(id) {
        const el = this.lineEl(id);
        if (!el) return null;
        const plan = el.dataset.plan === '' ? null : parseFloat(el.dataset.plan);
        const actual = this.actualOf(el);
        if (plan === null || plan === 0 || actual === null) return null;
        return actual / plan * 100;
      },
      pctText(id) { void this.tick; const p = this.pct(id); return p === null ? '—' : Math.round(p) + '%'; },
      pctClass(id) { void this.tick; const p = this.pct(id); return p === null ? 't-none' : (p >= 100 ? 't-green' : (p >= 50 ? 't-amber' : 't-red')); },
      cellClass(id, field) {
        void this.tick;
        if (field === 'actual' && this.invalid[id]) return 'invalid';
        const e = this.edits[id];
        return e && field in e ? 'edited' : '';
      },

      // ---- per measure (mirror of MeasureRecomputer::aggregate) ----
      agg(mid) {
        void this.tick;
        const tbody = this.tbodyOf(mid);
        const deadline = !!tbody && tbody.dataset.deadline === '1';
        let total = 0, done = 0, hasActual = false, sum = 0;
        for (const el of this.inputs(mid)) {
          const plan = el.dataset.plan === '' ? null : parseFloat(el.dataset.plan);
          const actual = this.actualOf(el);
          if (actual !== null && actual !== 0) hasActual = true;
          if (plan === null) continue;
          total++;
          const p = (actual === null || plan === 0) ? null : actual / plan * 100;
          if (p !== null && p >= 100) done++;
          sum += Math.max(0, Math.min(100, p ?? 0));
        }
        let status = !hasActual ? 'in_progress' : (total > 0 && done === total ? 'done' : 'open');
        if (total === 0) status = 'in_progress';
        else if (status === 'open' && !deadline) status = 'in_progress';
        return { status, pct: hasActual && total > 0 ? sum / total : null };
      },
      shown(mid) {
        const a = this.agg(mid);
        if (a.pct === null) return null;
        const r = Math.round(a.pct);
        return a.status === 'done' ? r : Math.min(99, r);
      },
      ringText(mid) { const s = this.shown(mid); return s === null ? '—' : s + '%'; },
      ringStyle(mid) { const s = this.shown(mid) ?? 0; return `stroke-dasharray:113.1;stroke-dashoffset:${(113.1 * (1 - s / 100)).toFixed(1)}`; },
      statusClass(mid) { return { done: 'ok', in_progress: 'wait', open: 'bad' }[this.agg(mid).status]; },
      statusLabel(mid) { return { done: 'Бажарилди', in_progress: 'Бажарилмоқда', open: 'Бажарилмаган' }[this.agg(mid).status]; },
      mstate(mid) {
        void this.tick;
        for (const el of this.inputs(mid)) {
          if (this.edits[el.dataset.line]) return 'edited';
        }
        const tbody = this.tbodyOf(mid);
        return tbody ? tbody.dataset.state : 'empty';
      },
      mstateLabel(mid) { return { saved: 'сақланган', carry: 'ўтган ойдан', empty: 'бўш', edited: 'ўзгартирилган' }[this.mstate(mid)]; },
      unfilled(mid) {
        for (const el of this.inputs(mid)) { if (this.actualOf(el) === null) return true; }
        return false;
      },

      // ---- filters and counts ----
      show(tbody) {
        void this.tick;
        const q = this.search.trim().toLowerCase();
        if (q !== '' && !tbody.dataset.search.includes(q)) return false;
        const mid = tbody.dataset.measure;
        switch (this.filter) {
          case 'unfilled': return this.unfilled(mid);
          case 'edited': return this.mstate(mid) === 'edited';
          case 'carry': return this.mstate(mid) === 'carry';
          case 'saved': return this.mstate(mid) === 'saved';
          default: return true;
        }
      },
      showGroup(tbody) {
        void this.tick; void this.search; void this.filter;
        for (let el = tbody.nextElementSibling; el && el.classList.contains('ad-m'); el = el.nextElementSibling) {
          if (this.show(el)) return true;
        }
        return false;
      },
      count(kind) {
        void this.tick;
        let n = 0;
        for (const tbody of this.measureEls()) {
          const mid = tbody.dataset.measure;
          if (kind === 'unfilled' ? this.unfilled(mid) : this.mstate(mid) === kind) n++;
        }
        return n;
      },
      get editedLines() { return Object.keys(this.edits).length; },

      // ---- typing ----
      onInput(ev) {
        const el = ev.target, id = el.dataset.line, field = el.dataset.field, v = el.value;
        const loaded = el.dataset.loadedText ?? '';
        const same = field === 'actual' ? this.sameNumber(v, loaded) : v === loaded;
        const cur = { ...(this.edits[id] || {}) };
        if (same) delete cur[field]; else cur[field] = v;
        if (Object.keys(cur).length === 0) delete this.edits[id]; else this.edits[id] = cur;
        if (field === 'actual') {
          // «3,» on the way to «3,5» is a number in progress, not an error — the full check is onBlur() and save().
          if (/^-?\d*[.,]?\d*$/.test(v.trim())) { delete this.invalid[id]; }
          else { try { this.parse(v); delete this.invalid[id]; } catch (e) { this.invalid[id] = e.message; } }
        }
      },
      onBlur(ev) {
        const el = ev.target;
        if (el.dataset.field !== 'actual') return;
        try { this.parse(el.value); delete this.invalid[el.dataset.line]; } catch (e) { this.invalid[el.dataset.line] = e.message; }
      },
      cellKey(ev) {
        const el = ev.target;
        if (ev.key === 'Escape') { el.value = el.dataset.loadedText ?? ''; this.onInput({ target: el }); return; }
        if (el.dataset.field !== 'actual') return;
        if (ev.key === 'Enter' || ev.key === 'ArrowDown') { ev.preventDefault(); this.move(el, 1); }
        else if (ev.key === 'ArrowUp') { ev.preventDefault(); this.move(el, -1); }
      },
      move(el, dir) {
        const all = Array.from(root.querySelectorAll('input[data-field="actual"]')).filter(i => !i.disabled && i.offsetParent !== null);
        const next = all[all.indexOf(el) + dir];
        if (next) { next.focus(); next.select(); }
      },
      hotkey(ev) {
        // ev.key is «ы» on a Cyrillic layout, so the physical key decides; ev.key stays for keyboards without codes.
        if ((ev.ctrlKey || ev.metaKey) && (ev.code === 'KeyS' || ev.key.toLowerCase() === 's')) { ev.preventDefault(); this.save(); }
      },
      changePeriod(ev) {
        if (this.editedLines > 0) {
          ev.target.value = ev.target.dataset.current;
          this.toast('Аввал сақланг ёки «Бекор қилиш»ни босинг');
          return;
        }
        this.$wire.selectPeriod(ev.target.value);
      },
      changeRegion(ev) {
        if (this.editedLines > 0) {
          ev.target.value = ev.target.dataset.current;
          this.toast('Аввал сақланг ёки «Бекор қилиш»ни босинг');
          return;
        }
        this.$wire.switchRegion(ev.target.value);
      },
      discard() {
        for (const el of root.querySelectorAll('input[data-line]')) el.value = el.dataset.loadedText ?? '';
        this.edits = {};
        this.invalid = {};
      },
      // After a server re-render replaced the value cells (a confirm, a panel save, an upload), put the
      // still-unsaved edits back into the fresh inputs so the cells and the save bar agree. A successful
      // save() clears the buffer right after the same event, so it must not re-type what was just saved.
      reapply() {
        if (this.saving) return;
        for (const [id, e] of Object.entries(this.edits)) {
          for (const field of ['actual', 'note']) {
            if (!(field in e)) continue;
            const el = this._index().byLine.get(String(id))?.[field];
            if (el) el.value = e[field];
            else { delete this.edits[id]; delete this.invalid[id]; break; }   // the line no longer exists
          }
        }
      },

      // ---- save ----
      async save() {
        if (this.saving || this.editedLines === 0) return;
        const badId = Object.keys(this.invalid)[0];
        if (badId) {
          const el = this.lineEl(badId);
          if (el) el.focus();
          this.toast('Аввал хато катакни тузатинг: ' + this.invalid[badId]);
          return;
        }
        // The answer re-keys the cells, so the caret would land nowhere: remember where it was.
        const focused = document.activeElement?.dataset?.line, field = document.activeElement?.dataset?.field;
        this.saving = true;
        try {
          const r = await this.$wire.save(this.edits);
          if (r && r.ok) { this.edits = {}; this.invalid = {}; }
          else if (r && r.message) this.toast(r.message);
        } catch (e) {
          this.toast('Сақлаб бўлмади — алоқани текшириб, қайта уриниб кўринг');
        } finally {
          this.saving = false;
          if (focused) this.$nextTick(() => root.querySelector(`input[data-field="${field}"][data-line="${focused}"]`)?.focus());
        }
      },
      toast(msg) {
        this.jsToast = msg;
        clearTimeout(this._timer);
        this._timer = setTimeout(() => { this.jsToast = ''; }, 4000);
      },
    };
  });
});
