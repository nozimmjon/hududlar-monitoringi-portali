/* Entry grid for /admin/roadmaps/{code}.
   Holds the edit buffer, computes live % / ring / status, drives keyboard flow and the
   client-side filters, and sends the buffer to Livewire on save. Every value is read
   from the DOM (data-* on the inputs) at evaluation time — Livewire re-keys the cells
   after a write, so nothing here caches server state. `tick` is bumped after each
   server re-render (the `ad-refresh` window event) so DOM-derived counts recompute.

   DOM access goes through `root`, captured once in init(), never through `$root`: the
   view nests x-data on each measure article and on the save bar, and inside those scopes
   `$root` is that element, not the grid. `idx` is the per-tick index of that root. */
document.addEventListener('alpine:init', () => {
  Alpine.data('adEntry', () => {
    let root = null;                                   // the component root element, captured in init()
    let idx  = { tick: -1, tbodies: [], byMeasure: new Map(), bySheet: new Map(), byLine: new Map(), probe: null };   // DOM index, rebuilt per tick
    /* A measure is addressed by the attribute contract, never by `.ad-m`: Alpine rewrites that class
       attribute (`:class="'st-' + statusClass(…)"`). The value inputs carry data-measure too — they are
       the ones with a data-line, so excluding it leaves exactly the measure elements. */
    const MEASURE = '[data-measure]:not([data-line])';

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
        /* Every answer from this component can re-key the value cells, and not all of them end in an
           `ad-refresh` (selectPeriod does not), so the memoised index has to be dropped on any commit
           of this component. The probe check below still covers a morph that arrives without a commit. */
        if (window.Livewire && Livewire.hook) {
          Livewire.hook('commit', ({ component, succeed }) => {
            if (component.el === root) succeed(() => { this.tick++; });
          });
        }
        window.addEventListener('beforeunload', this._unload = (e) => {
          if (this.editedLines > 0) { e.preventDefault(); e.returnValue = ''; }
        });
      },
      destroy() { window.removeEventListener('beforeunload', this._unload); clearTimeout(this._timer); },

      // ---- numbers (mirror of App\Support\Roadmaps\EntryValue::parse; the grid formats server-side) ----
      parse(raw) {
        // edge trim mirrors EntryValue::EDGE_BLANKS (JS \s lacks U+200B)
        const s = String(raw ?? '').replace(/^[\s\u200B]+|[\s\u200B]+$/g, '').replace(/[ \u00A0\u202F\u2009]/g, '').replace(/,/g, '.');
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
        const tbodies = Array.from(root.querySelectorAll(MEASURE));
        const byMeasure = new Map();
        const bySheet = new Map();
        const byLine = new Map();
        let probe = null;
        for (const tbody of tbodies) {
          byMeasure.set(tbody.dataset.measure, { tbody, actuals: [] });
          const sheet = tbody.parentElement;                           // its section sheet, for showGroup()
          if (sheet) { const l = bySheet.get(sheet); l ? l.push(tbody) : bySheet.set(sheet, [tbody]); }
        }
        for (const el of root.querySelectorAll('input[data-line]')) {
          const m = byMeasure.get(el.dataset.measure);
          if (m && el.dataset.field === 'actual') m.actuals.push(el);   // every line row has one, so this is the line set
          byLine.set(el.dataset.line, { ...(byLine.get(el.dataset.line) || {}), [el.dataset.field]: el });
          probe ??= el;
        }
        idx = { tick: this.tick, tbodies, byMeasure, bySheet, byLine, probe };
        return idx;
      },
      lineEl(id) { return this._index().byLine.get(String(id))?.actual ?? null; },
      inputs(mid) { return this._index().byMeasure.get(String(mid))?.actuals ?? []; },
      measureEls() { return this._index().tbodies; },
      measureOf(mid) { return this._index().byMeasure.get(String(mid))?.tbody ?? null; },
      actualOf(el) {
        const e = this.edits[el.dataset.line];
        if (e && 'actual' in e) { try { return this.parse(e.actual); } catch { return null; } }
        return el.dataset.loaded === '' ? null : parseFloat(el.dataset.loaded);
      },

      /* The getters below (pct, statusClass, mstateLabel, mstateChip, unfilled) read DOM values without
         touching `this.tick` themselves, and are correct only because every template entry point into
         them goes through a getter that does read it (`void this.tick` in pctText/pctClass/agg/mstate/
         show/count). A new getter that reads the DOM must read `this.tick` too — or be reached exclusively
         through one that does — otherwise Alpine registers no dependency and the value freezes at the
         render it was first evaluated in. */

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
        const measure = this.measureOf(mid);
        const deadline = !!measure && measure.dataset.deadline === '1';
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
      statusClass(mid) { return { done: 'ok', in_progress: 'wait', open: 'bad' }[this.agg(mid).status]; },
      mstate(mid) {
        void this.tick;
        for (const el of this.inputs(mid)) {
          if (this.edits[el.dataset.line]) return 'edited';
        }
        const measure = this.measureOf(mid);
        return measure ? measure.dataset.state : 'empty';
      },
      mstateLabel(mid) { return { saved: 'сақланган', carry: 'ўтган ойдан', empty: 'бўш', edited: 'ўзгартирилган' }[this.mstate(mid)]; },
      /** chip colour for a measure's state: saved reads positive, carry and edited read as pending */
      mstateChip(mid) {
        const s = this.mstate(mid);
        return s === 'saved' ? 'ok' : (s === 'empty' ? '' : 'edit');
      },
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
      /** a section sheet is shown when any measure inside it passes the filter */
      showGroup(sheet) {
        void this.tick; void this.search; void this.filter;
        // read from the index, never a fresh query: this runs per sheet on every keystroke. The map is
        // keyed by the sheet node, so a morph that replaced that node falls back to reading it live.
        for (const el of this._index().bySheet.get(sheet) ?? sheet.querySelectorAll(MEASURE)) { if (this.show(el)) return true; }
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
      // Drives the «no measure matches the filter» sheet: with every sheet hidden the page would be blank.
      get visibleMeasures() {
        void this.tick; void this.filter; void this.search;
        let n = 0;
        for (const tbody of this.measureEls()) { if (this.show(tbody)) n++; }
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
        // ev.key is «ы» on a Cyrillic layout, so the physical key decides; ev.key stays for keyboards without
        // codes (and can be undefined on a composition event). Ctrl+Shift+S / Ctrl+Alt+S belong to the browser.
        if (!(ev.ctrlKey || ev.metaKey) || ev.shiftKey || ev.altKey) return;
        if (ev.code === 'KeyS' || (ev.key || '').toLowerCase() === 's') { ev.preventDefault(); this.save(); }
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

      /* An Excel import rewrote the values server-side, so the unsaved buffer was typed against a sheet
         that no longer exists: re-applying it (what `ad-refresh` does) would paint over the import and
         the next save would silently undo it. Drop the buffer instead — the morph brings the imported
         values with it. The entry page locks its «Excel’дан юклаш» button while the buffer is non-empty,
         so this normally runs with nothing to discard; an import from a second tab still lands here. */
      onImported() {
        const n = this.editedLines;
        this.tick++;
        this.edits = {};
        this.invalid = {};
        this.$nextTick(() => this.discard());   // the fresh cells arrive with the morph: clear the typed text there too
        if (n > 0) this.toast('Excel’дан юкланди — сақланмаган ўзгаришлар бекор қилинди');
      },

      // ---- save ----
      async save() {
        if (this.saving || this.editedLines === 0) return;
        /* keydown beats blur, so Ctrl+S can arrive while a cell still holds text only onInput() has seen —
           and that one tolerates a number in progress («3,», «-»). Run the real parser over the buffer
           first, so the offending cell turns red and takes the caret instead of the server refusing it. */
        for (const [id, e] of Object.entries(this.edits)) {
          if (!('actual' in e)) continue;
          try { this.parse(e.actual); delete this.invalid[id]; } catch (x) { this.invalid[id] = x.message; }
        }
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
          if (focused && field) {
            this.$nextTick(() => {
              const el = root.querySelector(`input[data-field="${field}"][data-line="${focused}"]`);
              if (el) { el.focus(); el.select(); }
            });
          }
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
