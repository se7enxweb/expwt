/*
 * expwt: the panels of the website sidebar, loaded when the sidebar is first opened (decision F12). Each panel is a
 * function( ctx, root ) that fills root (ctx: expwt.js context(): h, t, call, say, boot, node, state ...).
 *
 *   page       the page's fields on a draft: the sidebar form and editing on the page itself (decision 11, F1),
 *              save, preview, publish now or later, unpublish date, discard (decision 13); soft locks and the
 *              field by field merge (F11)
 *   create     new content below or beside the page with a quick title (decision 16)
 *   structure  hide and reveal, move, add a location (a picker on the site), the trash, sort by drag (decision 14)
 *   me         the account and the kernel's own pages for it, the sidebar's preferences (decision 9)
 */
(function () {
  'use strict';

  function pad(n) { return (n < 10 ? '0' : '') + n; }

  function toInput(kind, value) {
    if (value === '' || value === null || value === undefined) { return ''; }
    var n = parseInt(value, 10);
    if (isNaN(n)) { return ''; }
    if (kind === 'time') { return pad(Math.floor(n / 3600)) + ':' + pad(Math.floor(n % 3600 / 60)); }
    var d = new Date(n * 1000);
    var day = d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
    return kind === 'date' ? day : day + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
  }

  function fromInput(kind, text) {
    if (!text) { return ''; }
    if (kind === 'time') {
      var p = text.split(':');
      return String(parseInt(p[0], 10) * 3600 + parseInt(p[1], 10) * 60);
    }
    var d = kind === 'date' ? new Date(text + 'T00:00') : new Date(text);
    return isNaN(d.getTime()) ? '' : String(Math.floor(d.getTime() / 1000));
  }

  function when(ts) {
    return ts ? new Date(ts * 1000).toLocaleString() : '';
  }

  function failure(ctx, err) {
    ctx.say(err && err.message ? err.message : ctx.t('Something went wrong.'), 'bad');
  }

  /* ---------------------------------------------------------------------------------------------- page */

  function page(ctx, root) {
    var h = ctx.h;
    var t = ctx.t;
    var target = ctx.host.draftTarget || (ctx.node ? { nodeId: ctx.node } : null);
    if (!target) {
      root.appendChild(h('p', { text: t('This page is not content of the site. Open a page of the site to edit it.') }));
      return;
    }
    root.appendChild(h('p', { class: 'wt-muted', text: t('Loading ...') }));
    ctx.call('page.load', target).then(function (p) {
      root.textContent = '';
      renderPage(ctx, root, p);
    }, function (err) { root.textContent = ''; failure(ctx, err); });
  }

  function renderPage(ctx, root, p) {
    var h = ctx.h;
    var t = ctx.t;
    var base = {};
    var values = {};
    var inputs = {};
    var rows = {};
    var choices = {};
    var version = p.draft ? p.draft.version : null;
    var published = p.publishedVersion;
    var locked = false;
    var heartbeat = null;
    /* editing on the page is the host's state (ctx.editing()): on while the panel is closed, after a reload too */
    var inline = !!(ctx.editing && ctx.editing());
    ctx.host.pageInfo = p;

    p.fields.forEach(function (f) { base[f.identifier] = f.value; values[f.identifier] = f.value; });

    var chips = h('p', {}, []);
    function drawChips() {
      chips.textContent = '';
      chips.appendChild(h('span', { class: 'wt-chip', text: p.class.name }));
      chips.appendChild(document.createTextNode(' '));
      if (version) {
        chips.appendChild(h('span', { class: 'wt-chip is-draft', text: t('Draft') + ' ' + version }));
      } else if (published) {
        chips.appendChild(h('span', { class: 'wt-chip is-ok', text: t('Published') }));
      }
      if (p.hidden) {
        chips.appendChild(document.createTextNode(' '));
        chips.appendChild(h('span', { class: 'wt-chip', text: t('Hidden') }));
      }
    }
    drawChips();
    /* the page's name, its state and the tools: one row on top and bottom */
    var head = h('div', { class: 'wt-section wt-page-head' }, [h('h3', { text: p.name }), chips]);
    root.appendChild(head);

    var lockBox = h('div', {});
    root.appendChild(lockBox);
    function drawLock(holder) {
      lockBox.textContent = '';
      if (!holder) { return; }
      lockBox.appendChild(h('div', { class: 'wt-notice is-warn', role: 'status' }, [
        h('p', { text: t('%name is editing this page since %time.').replace('%name', holder.name).replace('%time', when(holder.since)) }),
        h('button', { type: 'button', class: 'wt-btn', text: t('Take over'), onclick: function () {
          ctx.call('lock.acquire', { objectId: p.objectId, takeOver: true }).then(function () { drawLock(null); locked = true; }, function (e) { failure(ctx, e); });
        } })
      ]));
    }
    drawLock(p.lock);

    if (!p.can.edit) {
      root.appendChild(h('p', { class: 'wt-notice', text: t('You may read this page but not edit it.') }));
      return;
    }

    /* Edit on the page: on or off for this browser tab (the host keeps it); where the panel lies over the page (a
       phone) it gets out of the way at once, the page shows its outlines and the pill "Editing · Done" */
    var inlineBtn = h('button', { type: 'button', class: 'wt-btn', 'aria-pressed': inline ? 'true' : 'false', id: 'wt-inline', text: t('Edit on the page'), onclick: function () {
      var on = ctx.editing(!inline);
      if (on && ctx.overlay()) { ctx.host.toggle(false); }
    } });
    var tools = h('div', { class: 'wt-row' }, [
      inlineBtn,
      h('a', { class: 'wt-btn', href: p.urls.preview, target: '_blank', rel: 'noopener', text: t('Preview') }),
      h('a', { class: 'wt-btn', href: p.urls.fullEdit, text: t('Full editor') })
    ]);
    head.appendChild(tools);

    var form = h('form', { class: 'wt-section wt-fields', novalidate: true, onsubmit: function (e) { e.preventDefault(); save(); } });
    var order = [];
    var others = [];
    var frames = {};
    p.fields.forEach(function (f) {
      if (f.embed) {
        /* rich text, tags, relations, images and every other datatype: open in its own editor (expwt/field in a frame:
           the configured rich text editor, the tag picker ...), ready to type (F32), loaded when it comes into view */
        var holder = h('div', { class: 'wt-editor-inline' });
        var embedRow = h('div', { class: 'wt-field is-embed' + (f.input === 'richtext' ? ' is-wide' : ''), 'data-field': f.identifier,
          role: 'group', 'aria-label': f.name }, [
          h('span', { text: f.name + (f.required ? ' (' + t('required') + ')' : '') }), holder
        ]);
        frames[f.identifier] = { field: f, row: embedRow, holder: holder, frame: null, ready: false, waiter: null, onReady: null };
        rows[f.identifier] = embedRow;
        order.push(embedRow);
        form.appendChild(embedRow);
        return;
      }
      if (!f.editable) { others.push(f.name); return; }
      var id = 'wt-f-' + f.identifier;
      var input = control(ctx, f, id, function (v) { change(f.identifier, v, true); });
      inputs[f.identifier] = input;
      var row = h('label', { class: 'wt-field', for: id, 'data-field': f.identifier,
        onmouseenter: function () { highlight(f.identifier, true); }, onmouseleave: function () { highlight(f.identifier, false); } },
        [h('span', { text: f.name + (f.required ? ' (' + t('required') + ')' : '') }), input.el]);
      if (f.input === 'richtext' || f.input === 'checkbox' || f.input === 'multiselect' || f.input === 'url') {
        row = h('div', { class: 'wt-field', 'data-field': f.identifier, role: 'group', 'aria-label': f.name }, [
          h('span', { id: id + '-label', text: f.name + (f.required ? ' (' + t('required') + ')' : '') }), input.el]);
      }
      if (f.input === 'richtext' || f.input === 'textarea' || f.input === 'multiselect') {
        row.classList.add('is-wide');
      }
      rows[f.identifier] = row;
      order.push(row);
      form.appendChild(row);
    });
    root.appendChild(form);

    var conflictBox = h('div', {});
    root.appendChild(conflictBox);

    /* Publish at and take down at write the class's date fields that the kernel's wait-until-date workflow and hide
       cronjob read (F21). A class without such a field shows why in place of the input. */
    var sched = p.schedule || { publish: null, takeDown: null };
    function dateField(id, label, field) {
      if (!field) {
        return { input: null, kind: null, row: h('div', { class: 'wt-field', role: 'group', 'aria-label': label }, [
          h('span', { text: label }), h('p', { class: 'wt-muted', text: t('Not possible here: this kind of page has no date field for it.') })]) };
      }
      var kind = field.precision === 'date' ? 'date' : 'datetime';
      var input = h('input', { type: kind === 'date' ? 'date' : 'datetime-local', id: id, value: toInput(kind, field.value) });
      return { input: input, kind: kind, row: h('label', { class: 'wt-field', for: id }, [h('span', { text: label }), input]) };
    }
    var publishAt = dateField('wt-publish-at', t('Publish at'), sched.publish);
    var unpublishAt = dateField('wt-unpublish-at', t('Take down at'), sched.takeDown);
    var schedule = h('details', { class: 'wt-section wt-schedule', open: publishAt.input && publishAt.input.value || unpublishAt.input && unpublishAt.input.value ? '' : null }, [
      h('summary', { text: t('Publish later or take down at a date') }), publishAt.row, unpublishAt.row
    ]);
    var othersNote = others.length ? h('p', { class: 'wt-muted', text: t('In the full editor: %fields').replace('%fields', others.join(', ')) }) : null;
    /* On top and bottom: the fields that fit the bar in the grid, the rest, the date to publish and the fields of the
       full editor behind "More", so the bar's main controls never need a scroll bar. On left and right as before. */
    var moreFields = h('div', { class: 'wt-fields' });
    var moreSummary = h('summary', { text: t('More') });
    /* opened: its fields scroll into the bar's view (they lie below the grid) */
    var more = h('details', { class: 'wt-section wt-more', ontoggle: function () {
      if (more.open) { window.requestAnimationFrame(function () { moreSummary.scrollIntoView({ block: 'start', behavior: 'smooth' }); }); }
    } }, [moreSummary, moreFields]);
    root.appendChild(more);
    var flat = h('div', {});
    root.appendChild(flat);

    /* On top and bottom (a wide window): every field in the grid first, then the last ones behind "More" until the
       bar's body needs no scroll bar (at least one field stays). Again whenever the bar changes its size. */
    function arrange() {
      /* a phone (the bar on top or bottom is a sheet as high as its contents, its body scrolls): the fields one
         below the other as on the sides, no "More" -- in its little room "More" opened below the visible part */
      var hz = ctx.horizontal() && window.innerWidth >= 640;
      /* A field's row holds its editor's frame, and a frame moved in the page loads again (its request aborted): rows
         move only when their place changes, with moveBefore() where the browser keeps the frame's page */
      var inPlace = order.every(function (row, i) { return row.parentNode === form && (i === 0 || order[i - 1].nextSibling === row); });
      if (!inPlace) {
        order.forEach(function (row) { put(form, row, null); });
      }
      if (hz) {
        if (othersNote) { put(more, othersNote, null); }
        put(more, schedule, null);
        more.open = false;
        more.hidden = false;
        moreSummary.textContent = t('More');
        var over = function () { return root.scrollHeight > root.clientHeight + 1; };
        var inGrid = order.slice();
        while (over() && inGrid.length > 1) {
          put(moreFields, inGrid.pop(), moreFields.firstChild);
          moreSummary.textContent = t('More') + ' (' + moreFields.children.length + ')';
        }
        /* a smaller field after a long one may still fit: back into the grid in its own place, while it fits */
        Array.prototype.slice.call(moreFields.children).forEach(function (row) {
          var after = order.slice(order.indexOf(row) + 1).filter(function (r) { return r.parentNode === form; })[0];
          put(form, row, after || null);
          if (over()) { put(moreFields, row, null); }
        });
        order.forEach(function (row) { if (row.parentNode === moreFields) { put(moreFields, row, null); } });
        var left = moreFields.children.length;
        moreSummary.textContent = t('More') + (left ? ' (' + left + ')' : '');
      } else {
        if (othersNote) { put(form, othersNote, null); }
        put(flat, schedule, null);
        more.hidden = true;
      }
    }
    /* node into parent before ref (null: at the end); nothing when it is there already */
    function put(parent, node, ref) {
      ref = ref || null;
      if (node.parentNode === parent && (ref ? node.nextSibling === ref : !node.nextSibling)) { return; }
      if (ctx.move) { ctx.move(parent, node, ref); } else { parent.insertBefore(node, ref); }
    }
    arrange();
    if (root.expwtArrange) { root.removeEventListener('expwt-orient', root.expwtArrange); }
    root.expwtArrange = arrange;
    root.addEventListener('expwt-orient', arrange);

    var saveBtn = h('button', { type: 'button', class: 'wt-btn', text: t('Save draft'), onclick: function () {
      saveFrames().then(function (savedSome) {
        if (Object.keys(dirtyFields()).length) { return save(); }
        if (!savedSome) { ctx.say(t('Nothing to save.')); }
        return null;
      }).catch(function () {});
    } });
    var publishBtn = h('button', { type: 'button', class: 'wt-btn is-primary', text: t('Publish'), onclick: function () { publish(); } });
    var discardBtn = h('button', { type: 'button', class: 'wt-btn is-danger', text: t('Discard draft'), onclick: function () { discard(); } });
    var mainActions = h('div', { class: 'wt-row wt-primary' }, [saveBtn, publishBtn, discardBtn]);
    root.appendChild(mainActions);
    if (root.getAttribute('data-panel') === 'page') { ctx.primary(mainActions); }
    discardBtn.hidden = !version;

    /* Every field that has an editor of its own is open in it (F32): a frame (expwt/field) per field, loaded when the
       field comes into view. It shows the user's draft, or the published page while there is none; the panel's one
       Save draft and Publish save each frame in turn (the first save makes the draft, the others go into it; a
       frame nobody changed sends nothing), then the form's fields. The frames tell their draft, their height and,
       after a save, the field's new value. */
    function frameSrc(f) {
      var fieldBase = String(ctx.boot.transport.rest || '').replace(/\/api$/, '/field');
      return fieldBase + '/' + p.objectId + '/' + (version || 0) + '/' + encodeURIComponent(p.language) + '/' + encodeURIComponent(f.identifier)
        + '?in=sidebar&theme=' + encodeURIComponent(ctx.host.getAttribute('data-theme') || '');
    }
    function load(entry) {
      if (!entry || entry.frame) { return; }
      entry.frame = h('iframe', { class: 'wt-frame', src: frameSrc(entry.field), title: entry.field.name, height: '160' });
      entry.holder.appendChild(entry.frame);
    }
    var watcher = window.IntersectionObserver ? new IntersectionObserver(function (seen) {
      seen.forEach(function (s) {
        if (!s.isIntersecting) { return; }
        load(frames[s.target.getAttribute('data-field')]);
        watcher.unobserve(s.target);
      });
    }, { rootMargin: '200px 0px' }) : null;
    Object.keys(frames).forEach(function (id) {
      if (watcher) { watcher.observe(frames[id].row); } else { load(frames[id]); }
    });

    /* a field's row in view in the panel, also when it is behind "More" on top and bottom */
    function reveal(row) {
      var more = row && row.closest('details');
      if (more) { more.open = true; }
      if (row) { row.scrollIntoView({ block: 'nearest' }); }
    }
    /* a field of the page clicked while editing on the page: its editor, in view, with the focus; with the panel out
       of the way (a phone) in the bottom sheet */
    function focusField(f) {
      var entry = frames[f.identifier];
      if (!entry) { return; }
      load(entry);
      if (ctx.useSheet && ctx.useSheet()) { inSheet(f, entry.frame, entry.holder, entry); return; }
      reveal(entry.row);
      entry.frame.focus();
    }

    /* The bottom sheet: the field's editor (its frame, or its row of the form) moved into it, with Save draft and
       Publish; closing it puts the editor back into the panel. Where moving a frame would reload it (no moveBefore),
       what was typed is saved to the draft first. */
    function inSheet(f, node, home, entry) {
      var note = h('span', { class: 'wt-sheet-note', role: 'status', 'aria-live': 'polite' });
      var next = node.nextSibling;
      var saveB = h('button', { type: 'button', class: 'wt-btn', text: t('Save draft'), onclick: function () {
        note.textContent = t('Saving ...');
        var run = entry ? saveOne(entry) : save().then(function () { return true; });
        run.then(function (saved) {
          if (entry && saved === false && Object.keys(dirtyFields()).length) { return save().then(function () { return true; }); }
          return saved;
        }).then(function (saved) {
          note.textContent = saved === false ? t('Nothing to save.') : t('The draft is saved.');
        }, function () { note.textContent = t('This value does not fit the field.'); });
      } });
      var publishB = h('button', { type: 'button', class: 'wt-btn is-primary', text: t('Publish'), onclick: function () { publish(); } });
      ctx.openSheet(f.name, node, [saveB, publishB, note], function (done) {
        var back = function () {
          if (home.isConnected) { ctx.move(home, node, next && next.parentNode === home ? next : null); }
          done();
        };
        if (entry && entry.ready && !ctx.move.keeps) { saveOne(entry).then(back, back); } else { back(); }
      });
      if (entry && entry.frame) { entry.frame.focus(); }
    }
    /* a file dropped on a field (an image on the page): its editor takes it and saves it to the draft */
    function dropFile(f, file) {
      var entry = frames[f.identifier];
      if (!entry) { return; }
      reveal(entry.row);
      load(entry);
      var send = function () { entry.frame.contentWindow.postMessage({ expwt: 'field-file', file: file }, window.location.origin); };
      if (entry.ready) { send(); } else { entry.onReady = send; }
    }

    function saveOne(entry) {
      return new Promise(function (resolve, reject) {
        entry.waiter = { resolve: resolve, reject: reject };
        entry.frame.contentWindow.postMessage({ expwt: 'field-save' }, window.location.origin);
        setTimeout(function () { if (entry.waiter) { entry.waiter = null; reject(new Error('timeout')); } }, 30000);
      });
    }
    /* each loaded frame in turn; true when one of them saved something */
    function saveFrames() {
      var list = Object.keys(frames).map(function (id) { return frames[id]; }).filter(function (e) { return e.frame && e.ready; });
      var savedSome = false;
      var chain = Promise.resolve();
      if (list.length) { ctx.say(t('Saving ...')); }
      list.forEach(function (e) {
        chain = chain.then(function () { return saveOne(e); }).then(function (saved) { savedSome = savedSome || saved; });
      });
      return chain.then(function () { return savedSome; });
    }

    function fromField(e) {
      var m = e.detail || {};
      if (m.objectId !== p.objectId) { return; }
      var entry = frames[m.identifier];
      if (m.expwt === 'field-size') {
        if (entry && entry.frame) {
          var height = String(Math.max(80, Math.min(4000, parseInt(m.height, 10) || 160)));
          /* only a real change of size arranges the wide bar again (a frame that loaded again after a move says the
             same height: no new arrangement, so no loop of moves and reloads) */
          if (entry.frame.getAttribute('height') === height) { return; }
          entry.frame.setAttribute('height', height);
          if (ctx.horizontal() && window.innerWidth >= 640) { clearTimeout(entry.fit); entry.fit = setTimeout(arrange, 200); }
        }
        return;
      }
      if (m.version && m.version !== version) {
        version = m.version;
        discardBtn.hidden = false;
        drawChips();
      }
      if (m.expwt === 'field-saved') {
        var f = entry ? entry.field : null;
        if (f && m.value) {
          f.value = m.value.value;
          f.names = m.value.names;
        }
        if (entry) { entry.ready = true; }
        ctx.say(t('The draft is saved.'), 'ok');
        if (entry && entry.waiter) { entry.waiter.resolve(true); entry.waiter = null; }
      } else if (m.expwt === 'field-unchanged') {
        if (entry && entry.waiter) { entry.waiter.resolve(false); entry.waiter = null; }
      } else if (m.expwt === 'field-ready') {
        if (entry) {
          entry.ready = true;
          if (entry.onReady) { var go = entry.onReady; entry.onReady = null; go(); }
        }
        if (entry && entry.waiter && m.invalid) {
          ctx.say(t('This value does not fit the field.'), 'bad');
          reveal(entry.row);
          entry.waiter.reject(new Error('invalid'));
          entry.waiter = null;
        }
      } else if (m.expwt === 'field-file-refused' && entry && entry.waiter) {
        entry.waiter.reject(new Error('file'));
        entry.waiter = null;
      }
    }
    if (root.expwtField) { root.removeEventListener('expwt-field', root.expwtField); }
    root.expwtField = fromField;
    root.addEventListener('expwt-field', fromField);


    function dirtyFields() {
      var out = {};
      Object.keys(values).forEach(function (k) { if (values[k] !== base[k]) { out[k] = values[k]; } });
      return out;
    }

    function change(id, value, fromForm) {
      values[id] = value;
      if (rows[id]) { rows[id].setAttribute('data-dirty', values[id] !== base[id] ? 'true' : 'false'); }
      if (fromForm && inline) { mirror(id); }
      if (!locked) {
        locked = true;
        ctx.call('lock.acquire', { objectId: p.objectId }).then(function (r) {
          if (!r.mine) { drawLock(r.heldBy); }
        }, function () {});
        heartbeat = setInterval(function () {
          if (!root.isConnected) { clearInterval(heartbeat); return; }
          ctx.call('lock.acquire', { objectId: p.objectId }).catch(function () {});
        }, 60000);
      }
    }

    function save() {
      var fields = dirtyFields();
      if (!Object.keys(fields).length) {
        ctx.say(t('Nothing to save.'));
        return Promise.resolve(version);
      }
      var sentBase = {};
      Object.keys(fields).forEach(function (k) { sentBase[k] = base[k]; });
      ctx.say(t('Saving ...'));
      return ctx.call('draft.save', { objectId: p.objectId, language: p.language, version: version, publishedVersion: published,
        fields: fields, base: sentBase, choices: choices }).then(function (r) {
        if (r.version) { version = r.version; }
        if (r.publishedVersion) { published = r.publishedVersion; }
        Object.keys(fields).forEach(function (k) { base[k] = values[k]; if (rows[k]) { rows[k].setAttribute('data-dirty', 'false'); } });
        choices = {};
        conflictBox.textContent = '';
        discardBtn.hidden = !version;
        p.urls.preview = r.previewUrl || p.urls.preview;
        drawChips();
        ctx.say(t('The draft is saved.'), 'ok');
        return version;
      }, function (err) {
        if (err.status === 409 && err.body && err.body.details) {
          conflicts(err.body.details);
        } else {
          failure(ctx, err);
        }
        throw err;
      });
    }

    function conflicts(details) {
      conflictBox.textContent = '';
      published = details.publishedVersion;
      conflictBox.appendChild(h('div', { class: 'wt-notice is-warn', role: 'alert', text: t('Someone else changed these fields in the meantime. Choose for each field which text to keep, then save again.') }));
      Object.keys(details.conflicts).forEach(function (id) {
        var c = details.conflicts[id];
        var name = 'wt-c-' + id;
        choices[id] = 'mine';
        conflictBox.appendChild(h('fieldset', { class: 'wt-conflict' }, [
          h('legend', { text: rows[id] ? rows[id].querySelector('span').textContent : id }),
          h('label', { class: 'wt-check' }, [h('input', { type: 'radio', name: name, checked: true, onchange: function () { choices[id] = 'mine'; } }), t('Keep mine')]),
          h('pre', { text: c.mine }),
          h('label', { class: 'wt-check' }, [h('input', { type: 'radio', name: name, onchange: function () { choices[id] = 'theirs'; } }), t('Take theirs')]),
          h('pre', { text: c.theirs })
        ]));
      });
      conflictBox.appendChild(h('button', { type: 'button', class: 'wt-btn is-primary', text: t('Save with these choices'), onclick: function () {
        Object.keys(details.conflicts).forEach(function (id) { base[id] = details.conflicts[id].theirs; });
        save().catch(function () {});
      } }));
    }

    function publish() {
      var first = saveFrames().then(function () { return Object.keys(dirtyFields()).length ? save() : version; });
      first.then(function (v) {
        if (!v) {
          ctx.say(t('Change something first: there is no draft to publish.'));
          return null;
        }
        var input = { objectId: p.objectId, version: v };
        var at = publishAt.input ? fromInput(publishAt.kind, publishAt.input.value) : '';
        if (at) { input.publishAt = at; }
        /* empty clears a take-down date the draft carries */
        if (unpublishAt.input) { input.unpublishAt = fromInput(unpublishAt.kind, unpublishAt.input.value); }
        ctx.say(t('Publishing ...'));
        return ctx.call('draft.publish', input).then(function (r) {
          if (r.status === 'PUBLISHED') {
            ctx.say(t('Published.'), 'ok');
            ctx.host.draftTarget = null;
            clearInterval(heartbeat);
            setTimeout(function () { window.location.href = r.url || window.location.href; }, 400);
          } else if (r.status === 'PENDING') {
            ctx.say(t('Sent for approval.'), 'ok');
          } else if (r.status === 'SCHEDULED') {
            ctx.say(t('Scheduled for %time.').replace('%time', when(r.publishAt)), 'ok');
          } else {
            ctx.say(t('The publishing was stopped by a workflow.'), 'bad');
          }
        });
      }).catch(function (err) {
        if (err && err.body && err.body.error === 'required') {
          ctx.say(t('Fill in the required fields first.'), 'bad');
        } else if (err && err.status !== 409) {
          failure(ctx, err);
        }
      });
    }

    function discard() {
      if (!version || !window.confirm(t('Throw this draft away?'))) { return; }
      ctx.call('draft.discard', { objectId: p.objectId, version: version }).then(function () {
        ctx.host.draftTarget = null;
        ctx.say(t('The draft is gone.'), 'ok');
        setInline(false);
        ctx.show('page');
      }, function (err) { failure(ctx, err); });
    }

    /* Editing on the page: the page's own text that shows a field's value becomes editable in place. Where the kernel
       marked the rendered attributes (F20: <!--exp-field data-exp-field data-exp-object ...-->, for a user who may
       edit with the sidebar) the field is found by its marker; else, as before, by its value. */
    var placed = {};
    function findOnPage(f) {
      var marked = fieldMarkers(p.objectId, f.identifier);
      var els = marked.map(function (range) { return markedTarget(range, f); }).filter(function (el) { return el; });
      return els.length ? els : findByValue(f);
    }

    function findByValue(f) {
      var want = String(f.value || '').trim();
      if (!want) { return []; }
      var found = [];
      var all = document.body.querySelectorAll('h1,h2,h3,h4,h5,h6,p,span,a,li,div,figcaption,strong,em,small,time,blockquote,section,article');
      Array.prototype.forEach.call(all, function (el) {
        if (el.closest('exp-wt') || found.length > 3) { return; }
        var same = f.input === 'richtext' ? el.innerHTML.trim() === want : el.textContent.trim() === want && el.children.length === 0;
        if (same && !found.some(function (x) { return x.contains(el) || el.contains(x); })) { found.push(el); }
      });
      return found;
    }

    function setInline(on) {
      Object.keys(placed).forEach(function (id) {
        placed[id].forEach(function (el) {
          el.removeAttribute('contenteditable');
          el.removeAttribute('data-expwt-field');
          el.removeAttribute('data-expwt-hl');
          el.removeEventListener('input', el.expwtInput);
          el.removeEventListener('click', el.expwtInput);
          el.removeEventListener('keydown', el.expwtInput);
          if (el.expwtDrag) { el.removeEventListener('dragover', el.expwtDrag); el.removeEventListener('dragleave', el.expwtDrag); el.removeEventListener('drop', el.expwtDrop); el.expwtDrag = el.expwtDrop = null; }
          (el.expwtAdded || []).forEach(function (name) { el.removeAttribute(name); });
          el.expwtAdded = null;
        });
      });
      placed = {};
      ctx.inlineCss('');
      if (!on) { return; }
      var count = 0;
      p.fields.forEach(function (f) {
        if (f.embed && rows[f.identifier]) {
          /* a field in its own editor (rich text, an image ...), marked by the kernel on the page, or an image of the
             page that is this field's (its file's path has /<attribute id>-<version>-): a click opens its editor */
          var targets = fieldMarkers(p.objectId, f.identifier).map(function (range) { return markedTarget(range, { input: 'richtext' }); })
            .filter(function (el) { return el; });
          if (!targets.length && f.input === 'image') { targets = imagesOf(f); }
          targets.forEach(function (el) {
            count++;
            (placed[f.identifier] = placed[f.identifier] || []).push(el);
            el.setAttribute('data-expwt-field', f.identifier);
            /* the page's element answers to the keyboard while editing on the page is on; taken off again after */
            el.expwtAdded = ['tabindex', 'role', 'aria-label'].filter(function (name) { return !el.hasAttribute(name); });
            el.expwtAdded.forEach(function (name) {
              el.setAttribute(name, { tabindex: '0', role: 'button', 'aria-label': t('Edit') + ': ' + f.name }[name]);
            });
            el.expwtInput = function (e) {
              if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') { return; }
              e.preventDefault();
              focusField(f);
              reveal(rows[f.identifier]);
            };
            el.addEventListener('click', el.expwtInput);
            el.addEventListener('keydown', el.expwtInput);
            if (f.input === 'image') {
              /* an image file dropped on the page's image: into the field's editor and saved to the draft */
              el.expwtDrag = function (e) {
                if (e.type === 'dragleave') { el.removeAttribute('data-expwt-drop'); return; }
                if (e.dataTransfer && Array.prototype.indexOf.call(e.dataTransfer.types, 'Files') >= 0) { e.preventDefault(); el.setAttribute('data-expwt-drop', ''); }
              };
              el.expwtDrop = function (e) {
                el.removeAttribute('data-expwt-drop');
                var file = e.dataTransfer && e.dataTransfer.files[0];
                if (!file || !/^image\//.test(file.type)) { return; }
                e.preventDefault();
                dropFile(f, file);
              };
              el.addEventListener('dragover', el.expwtDrag);
              el.addEventListener('dragleave', el.expwtDrag);
              el.addEventListener('drop', el.expwtDrop);
            }
          });
          return;
        }
        if (!f.editable) { return; }
        var els = f.inline ? findOnPage(f) : [];
        if (!els.length) {
          /* a marked field that is not its value as typed (a date, a number formatted by its template, a text the
             template shortens): outlined too, a click puts the focus into its input of the form */
          fieldMarkers(p.objectId, f.identifier).forEach(function (range) {
            var box = markedTarget(range, { input: 'richtext' });
            if (!box || !rows[f.identifier]) { return; }
            count++;
            (placed[f.identifier] = placed[f.identifier] || []).push(box);
            box.setAttribute('data-expwt-field', f.identifier);
            box.expwtAdded = ['tabindex', 'role', 'aria-label'].filter(function (name) { return !box.hasAttribute(name); });
            box.expwtAdded.forEach(function (name) {
              box.setAttribute(name, { tabindex: '0', role: 'button', 'aria-label': t('Edit') + ': ' + f.name }[name]);
            });
            box.expwtInput = function (e) {
              if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') { return; }
              e.preventDefault();
              var row = rows[f.identifier];
              if (ctx.useSheet && ctx.useSheet()) { inSheet(f, row, row.parentNode, null); }
              else { reveal(row); }
              var input = row.querySelector('input, select, textarea');
              if (input) { input.focus(); }
            };
            box.addEventListener('click', box.expwtInput);
            box.addEventListener('keydown', box.expwtInput);
          });
          return;
        }
        placed[f.identifier] = els;
        els.forEach(function (el) {
          count++;
          el.setAttribute('data-expwt-field', f.identifier);
          el.setAttribute('contenteditable', f.input === 'richtext' ? 'true' : 'plaintext-only');
          if (el.contentEditable !== 'plaintext-only' && f.input !== 'richtext') { el.setAttribute('contenteditable', 'true'); }
          el.expwtInput = function () {
            var v = f.input === 'richtext' ? el.innerHTML.trim() : el.textContent;
            inputs[f.identifier].set(v);
            change(f.identifier, v, false);
          };
          el.addEventListener('input', el.expwtInput);
        });
      });
      ctx.inlineCss('[data-expwt-field]{outline:2px dashed Highlight;outline-offset:2px;cursor:text}'
        + '[data-expwt-field][role=button]{cursor:pointer}[data-expwt-field]:hover{outline-style:solid}[data-expwt-field]:focus-visible{outline:3px solid Highlight}'
        + '[data-expwt-hl]{outline:3px solid Highlight;outline-offset:2px}[data-expwt-drop]{outline:3px solid Highlight;outline-offset:6px}');
      ctx.say(count ? t('Click a text on the page to edit it. It is saved with the draft.') : t('None of the fields shows on this page as it is; use the form.'));
    }

    /* the host turned editing on the page on or off (the button, the pill's Done): the outlines follow; the panel
       drawn next takes this panel's marks off the page first */
    if (root.expwtEditing) { root.removeEventListener('expwt-editing', root.expwtEditing); }
    root.expwtEditing = function (e) {
      inline = !!(e.detail && e.detail.on);
      inlineBtn.setAttribute('aria-pressed', inline ? 'true' : 'false');
      setInline(inline);
    };
    root.addEventListener('expwt-editing', root.expwtEditing);
    if (ctx.onLeave) { ctx.onLeave(function () { setInline(false); }); }
    if (inline) { setInline(true); }

    function mirror(id) {
      (placed[id] || []).forEach(function (el) {
        var f = p.fields.filter(function (x) { return x.identifier === id; })[0];
        if (f.input === 'richtext') { el.innerHTML = values[id]; } else { el.textContent = values[id]; }
      });
    }

    function highlight(id, on) {
      (placed[id] || findOnPage(p.fields.filter(function (x) { return x.identifier === id; })[0] || {})).forEach(function (el) {
        if (on) { el.setAttribute('data-expwt-hl', ''); } else { el.removeAttribute('data-expwt-hl'); }
      });
      if (!inline) { ctx.inlineCss(on ? '[data-expwt-hl]{outline:3px solid Highlight;outline-offset:2px}' : ''); }
    }
  }

  /* The page's images of an image field: an image file of the datatype lies in a directory named
     <attribute id>-<version>-<language> (var/<site>/storage/images/.../402-7-eng-US/name_alias.jpg) */
  function imagesOf(f) {
    if (!f.attribute) { return []; }
    var part = '/' + f.attribute + '-';
    return Array.prototype.filter.call(document.querySelectorAll('img'), function (img) {
      if (img.closest('exp-wt')) { return false; }
      var src = (img.currentSrc || img.src || '') + ' ' + (img.getAttribute('srcset') || '');
      return src.indexOf(part) >= 0 && /\/\d+-\d+-[a-z]{3}-[A-Z]{2}\//.test(src.slice(src.indexOf(part)));
    });
  }

  /* What an embedded field shows in the form: rich text as the page renders it (the server's own output of the
     stored XML), tags and related objects as chips of their names, anything else as its readable text. */
  function drawPreview(box, f) {
    var h = function (tag, attrs) { var el = document.createElement(tag); Object.keys(attrs || {}).forEach(function (k) { if (k === 'text') { el.textContent = attrs[k]; } else { el.setAttribute(k, attrs[k]); } }); return el; };
    box.textContent = '';
    if (f.names) {
      box.classList.add('wt-names');
      if (!f.names.length) { box.appendChild(h('span', { class: 'wt-muted', text: '–' })); }
      f.names.forEach(function (n) { box.appendChild(h('span', { class: 'wt-chip', text: n })); });
    } else if (f.input === 'richtext') {
      box.classList.add('wt-rich-preview');
      box.innerHTML = f.value || '';
    } else {
      box.appendChild(h('span', { class: f.value ? '' : 'wt-muted', text: f.value || '–' }));
    }
  }

  /* The kernel's markers of the rendered attributes (expContentFieldMarker): the ranges [open comment, close comment]
     of one field of one object, outermost first; a marker inside another one's output (an embedded object) is its
     own range. */
  function fieldMarkers(objectId, identifier) {
    var out = [];
    if (!document.createTreeWalker) { return out; }
    var walker = document.createTreeWalker(document.body, NodeFilter.SHOW_COMMENT);
    var stack = [];
    var node;
    while ((node = walker.nextNode())) {
      var text = node.data || '';
      if (text.indexOf('exp-field ') === 0) {
        var attrs = {};
        text.replace(/data-exp-([a-z]+)="([^"]*)"/g, function (m, k, v) { attrs[k] = v; return m; });
        stack.push({ open: node, attrs: attrs });
      } else if (text === '/exp-field' && stack.length) {
        var range = stack.pop();
        range.close = node;
        if (String(range.attrs.object) === String(objectId) && range.attrs.field === identifier && range.open.parentNode === node.parentNode) {
          out.push(range);
        }
      }
    }
    return out;
  }

  /* The element that shows a marked field and can be edited in place: the one element of its output (and, for a
     text, the innermost element that holds only it), or its parent when the output is the parent's whole content;
     a text in a field's output must read as the field's value. null when there is no such element. */
  function markedTarget(range, f) {
    var inside = [];
    for (var n = range.open.nextSibling; n && n !== range.close; n = n.nextSibling) {
      if (n.nodeType === 1 || (n.nodeType === 3 && n.data.trim())) { inside.push(n); }
    }
    var el = null;
    if (inside.length === 1 && inside[0].nodeType === 1) {
      el = inside[0];
    } else if (inside.length && range.open.parentNode && range.open.parentNode !== document.body) {
      var parent = range.open.parentNode;
      var only = Array.prototype.every.call(parent.childNodes, function (c) {
        return inside.indexOf(c) >= 0 || c === range.open || c === range.close || c.nodeType === 8 || (c.nodeType === 3 && !c.data.trim());
      });
      el = only ? parent : null;
    }
    if (!el || el.closest('exp-wt')) { return null; }
    if (f.input === 'richtext') { return el; }
    while (el.children.length === 1 && !Array.prototype.some.call(el.childNodes, function (c) { return c.nodeType === 3 && c.data.trim(); })) {
      el = el.children[0];
    }
    return el.children.length === 0 && el.textContent.trim() === String(f.value || '').trim() ? el : null;
  }

  /* One input of the sidebar form: el, set( value ) */
  function control(ctx, f, id, onChange) {
    var h = ctx.h;
    var t = ctx.t;
    var el;
    var set;
    switch (f.input) {
      case 'textarea':
        el = h('textarea', { id: id, value: f.value, oninput: function () { onChange(el.value); } });
        set = function (v) { el.value = v; };
        break;
      case 'checkbox':
        var box = h('input', { type: 'checkbox', id: id, checked: f.value === '1', onchange: function () { onChange(box.checked ? '1' : '0'); } });
        el = h('label', { class: 'wt-check', for: id }, [box, t('Yes')]);
        set = function (v) { box.checked = v === '1'; };
        break;
      case 'select':
      case 'multiselect':
        var multi = f.input === 'multiselect';
        var chosen = String(f.value || '').split('|');
        var sel = h('select', { id: id, multiple: multi, 'aria-labelledby': multi ? id + '-label' : null, onchange: function () {
          onChange(Array.prototype.filter.call(sel.options, function (o) { return o.selected; }).map(function (o) { return o.value; }).join('|'));
        } }, (multi ? [] : [h('option', { value: '', text: '' })]).concat((f.options || []).map(function (o) {
          return h('option', { value: o, text: o, selected: chosen.indexOf(o) >= 0 });
        })));
        el = sel;
        set = function () {};
        break;
      case 'date':
      case 'datetime':
      case 'time':
        var kind = f.input;
        var d = h('input', { type: kind === 'datetime' ? 'datetime-local' : kind, id: id, value: toInput(kind, f.value),
          onchange: function () { onChange(fromInput(kind, d.value)); } });
        el = d;
        set = function () {};
        break;
      case 'url':
        /* a link: its address and its text, two inputs (the datatype's string form "address|text" never shows) */
        var parts = String(f.value || '').split('|');
        var address = h('input', { type: 'url', id: id, value: parts[0] || '', 'aria-labelledby': id + '-label', oninput: function () { send(); } });
        var linkText = h('input', { type: 'text', id: id + '-text', value: parts.slice(1).join('|'), oninput: function () { send(); } });
        var send = function () { onChange(address.value.replace(/\|/g, '%7C') + (linkText.value ? '|' + linkText.value : '')); };
        el = h('div', { class: 'wt-url' }, [address,
          h('label', { class: 'wt-field', for: id + '-text' }, [h('span', { text: t('Text of the link') }), linkText])]);
        set = function (v) { var q = String(v || '').split('|'); address.value = q[0] || ''; linkText.value = q.slice(1).join('|'); };
        break;
      default:
        var type = { number: 'number', decimal: 'number', email: 'email' }[f.input] || 'text';
        var inp = h('input', { type: type, id: id, value: f.value, step: f.input === 'decimal' ? 'any' : null,
          oninput: function () { onChange(inp.value); } });
        el = inp;
        set = function (v) { inp.value = v; };
    }
    return { el: el, set: set };
  }

  /* ---------------------------------------------------------------------------------------------- create */

  function create(ctx, root) {
    var h = ctx.h;
    var t = ctx.t;
    if (!ctx.node) {
      root.appendChild(h('p', { text: t('This page is not content of the site. Open a page of the site to edit it.') }));
      return;
    }
    ctx.call('create.classes', { nodeId: ctx.node }).then(function (c) {
      var where = c.children.length ? 'children' : 'siblings';
      var classSel = h('select', { id: 'wt-class' });
      var title = h('input', { type: 'text', id: 'wt-title-new', required: true });
      function fill() {
        classSel.textContent = '';
        (where === 'children' ? c.children : c.siblings).forEach(function (k) {
          classSel.appendChild(h('option', { value: k.identifier, text: k.name }));
        });
      }
      if (!c.children.length && !c.siblings.length) {
        root.appendChild(h('p', { text: t('You may not create anything here.') }));
        return;
      }
      var group = h('fieldset', { class: 'wt-section wt-choice' }, [h('legend', { text: t('Where') })]);
      [['children', t('Below this page'), c.children.length], ['siblings', t('Beside this page'), c.siblings.length]].forEach(function (o) {
        if (!o[2]) { return; }
        group.appendChild(h('label', { class: 'wt-check' }, [
          h('input', { type: 'radio', name: 'wt-where', checked: where === o[0], onchange: function () { where = o[0]; fill(); } }), o[1]
        ]));
      });
      fill();
      root.appendChild(h('form', { class: 'wt-create', onsubmit: function (e) {
        e.preventDefault();
        if (!title.value.trim()) { title.focus(); ctx.say(t('Give a title.'), 'bad'); return; }
        ctx.call('create.new', { parentId: where === 'children' ? c.nodeId : c.parentId, classIdentifier: classSel.value, title: title.value.trim() })
          .then(function (r) {
            ctx.host.draftTarget = { objectId: r.objectId, version: r.version };
            ctx.say(t('A draft was made. Fill it in and publish it.'), 'ok');
            ctx.show('page');
          }, function (err) { failure(ctx, err); });
      } }, [
        group,
        h('label', { class: 'wt-field', for: 'wt-class' }, [h('span', { text: t('Type') }), classSel]),
        h('label', { class: 'wt-field', for: 'wt-title-new' }, [h('span', { text: t('Title') }), title]),
        h('button', { type: 'submit', class: 'wt-btn is-primary', text: t('Create draft') })
      ]));
    }, function (err) { failure(ctx, err); });
  }

  /* ---------------------------------------------------------------------------------------------- structure */

  function structure(ctx, root) {
    var h = ctx.h;
    var t = ctx.t;
    if (!ctx.node) {
      root.appendChild(h('p', { text: t('This page is not content of the site. Open a page of the site to edit it.') }));
      return;
    }
    Promise.all([ctx.call('page.load', { nodeId: ctx.node }), ctx.call('tree.children', { nodeId: ctx.node })]).then(function (r) {
      var p = r[0];
      var kids = r[1];
      var acts = h('div', { class: 'wt-row' });
      if (p.can.hide) {
        acts.appendChild(h('button', { type: 'button', class: 'wt-btn', text: p.hidden ? t('Reveal') : t('Hide'), onclick: function () {
          ctx.call(p.hidden ? 'tree.reveal' : 'tree.hide', { nodeId: ctx.node }).then(function (x) {
            ctx.say(x.hidden ? t('The page is hidden.') : t('The page is visible again.'), 'ok');
            ctx.show('structure');
          }, function (e) { failure(ctx, e); });
        } }));
      }
      if (p.can.move) {
        acts.appendChild(h('button', { type: 'button', class: 'wt-btn', text: t('Move ...'), onclick: function () { picker(ctx, root, 'tree.move', p); } }));
      }
      if (p.can.copy) {
        acts.appendChild(h('button', { type: 'button', class: 'wt-btn', text: t('Copy ...'), onclick: function () { picker(ctx, root, 'tree.copy', p); } }));
      }
      acts.appendChild(h('button', { type: 'button', class: 'wt-btn', text: t('Add location ...'), onclick: function () { picker(ctx, root, 'tree.addlocation', p); } }));
      if (p.can.remove) {
        acts.appendChild(h('button', { type: 'button', class: 'wt-btn is-danger', text: t('Move to trash'), onclick: function () {
          if (!window.confirm(t('Move this page to the trash?'))) { return; }
          ctx.call('tree.trash', { nodeId: ctx.node }).then(function (x) { window.location.href = x.parentUrl; }, function (e) { failure(ctx, e); });
        } }));
      }
      root.appendChild(h('div', { class: 'wt-section wt-node-head' }, [h('h3', { text: p.name }), acts]));
      root.appendChild(sortList(ctx, kids));
    }, function (err) { failure(ctx, err); });
  }

  /* The glyphs of "move up" (back) and "move down" (on): up and down in the panel's list, along the reading
     direction in the bar's strip (the stylesheet shows the one that fits; the button's label says it in words). */
  function arrow(h, way) {
    var g = way === 'back' ? ['↑', '←', '→'] : ['↓', '→', '←'];
    return [
      h('span', { class: 'wt-g-v', 'aria-hidden': 'true', text: g[0] }),
      h('span', { class: 'wt-g-h', 'aria-hidden': 'true', text: g[1] }),
      h('span', { class: 'wt-g-h-rtl', 'aria-hidden': 'true', text: g[2] })
    ];
  }

  function sortList(ctx, kids) {
    var h = ctx.h;
    var t = ctx.t;
    var box = h('div', { class: 'wt-section wt-sort' }, [h('h3', { text: t('Pages below this one') })]);
    if (!kids.children.length) {
      box.appendChild(h('p', { class: 'wt-muted', text: t('There are no pages below this one.') }));
      return box;
    }
    var list = h('ul', { class: 'wt-list wt-strip', tabindex: '0', 'aria-label': t('Pages below this one') });
    var order = kids.children.slice();
    var dragging = null;
    function draw() {
      list.textContent = '';
      order.forEach(function (c, i) {
        var li = h('li', { draggable: kids.sortable ? 'true' : null, 'data-node': c.nodeId,
          ondragstart: function (e) { dragging = i; e.dataTransfer.effectAllowed = 'move'; },
          ondragover: function (e) { if (dragging !== null) { e.preventDefault(); li.setAttribute('data-drop', 'true'); } },
          ondragleave: function () { li.removeAttribute('data-drop'); },
          ondrop: function (e) { e.preventDefault(); move(dragging, i); dragging = null; } },
          [h('span', { text: c.name + (c.hidden ? ' (' + t('Hidden') + ')' : '') })]);
        if (kids.sortable) {
          li.appendChild(h('button', { type: 'button', class: 'wt-btn is-icon', 'aria-label': t('Move up') + ': ' + c.name, disabled: i === 0,
            onclick: function () { move(i, i - 1); } }, arrow(h, 'back')));
          li.appendChild(h('button', { type: 'button', class: 'wt-btn is-icon', 'aria-label': t('Move down') + ': ' + c.name, disabled: i === order.length - 1,
            onclick: function () { move(i, i + 1); } }, arrow(h, 'on')));
        }
        list.appendChild(li);
      });
    }
    function move(from, to) {
      if (from === null || to < 0 || to >= order.length || from === to) { return; }
      var item = order.splice(from, 1)[0];
      order.splice(to, 0, item);
      draw();
      saveBtn.disabled = false;
      ctx.say(t('%name moved to place %n.').replace('%name', item.name).replace('%n', String(to + 1)));
    }
    var saveBtn = h('button', { type: 'button', class: 'wt-btn is-primary', disabled: true, text: t('Save the order'), onclick: function () {
      ctx.call('tree.sort', { nodeId: kids.nodeId, order: order.map(function (c) { return c.nodeId; }) }).then(function () {
        saveBtn.disabled = true;
        ctx.say(t('The order is saved.'), 'ok');
      }, function (e) { failure(ctx, e); });
    } });
    draw();
    box.appendChild(list);
    if (kids.sortable) {
      box.appendChild(h('div', { class: 'wt-row wt-sort-save' }, [saveBtn]));
    } else {
      box.appendChild(h('p', { class: 'wt-muted', text: t('These pages are sorted automatically, not by hand.') }));
    }
    return box;
  }

  /* The site-side picker: walk the tree from the page's parent, choose where to move or add the page. */
  function picker(ctx, root, action, p) {
    var h = ctx.h;
    var t = ctx.t;
    root.textContent = '';
    /* a copy: the picker opens at the page's parent, whose own Choose puts the copy beside the page */
    var title = action === 'tree.move' ? t('Move "%name" to ...') : action === 'tree.copy' ? t('Copy "%name" to ...') : t('Add "%name" also below ...');
    var heading = h('h3', { text: title.replace('%name', p.name) });
    var list = h('ul', { class: 'wt-list wt-strip', tabindex: '0', 'aria-label': title.replace('%name', p.name) });
    var where = h('p', { class: 'wt-muted' });
    root.appendChild(heading);
    root.appendChild(where);
    root.appendChild(list);
    root.appendChild(h('div', { class: 'wt-row' }, [h('button', { type: 'button', class: 'wt-btn', text: t('Cancel'), onclick: function () { ctx.show('structure'); } })]));
    function open(nodeId) {
      ctx.call('tree.children', { nodeId: nodeId }).then(function (r) {
        where.textContent = t('In: %name').replace('%name', r.name);
        list.textContent = '';
        if (r.parentId > 1) {
          list.appendChild(h('li', {}, [h('button', { type: 'button', class: 'wt-btn', text: t('Up one level'), onclick: function () { open(r.parentId); } })]));
        }
        list.appendChild(h('li', {}, [h('span', { text: r.name }),
          h('button', { type: 'button', class: 'wt-btn is-primary', text: t('Choose'), onclick: function () { choose(r.nodeId, r.name); } })]));
        r.children.filter(function (c) { return c.container && c.nodeId !== ctx.node; }).forEach(function (c) {
          list.appendChild(h('li', {}, [
            h('span', { text: c.name }),
            h('button', { type: 'button', class: 'wt-btn', text: t('Open'), 'aria-label': t('Open') + ': ' + c.name, onclick: function () { open(c.nodeId); } }),
            h('button', { type: 'button', class: 'wt-btn', text: t('Choose'), 'aria-label': t('Choose') + ': ' + c.name, onclick: function () { choose(c.nodeId, c.name); } })
          ]));
        });
      }, function (e) { failure(ctx, e); });
    }
    function choose(parentId, name) {
      var ask = action === 'tree.move' ? t('Move the page below "%name"?') : action === 'tree.copy' ? t('Copy the page below "%name"?') : t('Add the page also below "%name"?');
      if (!window.confirm(ask.replace('%name', name))) { return; }
      if (action === 'tree.copy') { ctx.say(t('Copying ...')); }
      ctx.call(action, { nodeId: ctx.node, parentId: parentId }).then(function (r) {
        if (action === 'tree.copy') {
          if (r && r.url) {
            ctx.say(t('The copy is made. It opens now.'), 'ok');
            setTimeout(function () { window.location.href = r.url; }, 400);
          } else {
            ctx.say(t('The copy waits for a workflow (an approval).'), 'ok');
          }
          return;
        }
        ctx.say(t('Done.'), 'ok');
        setTimeout(function () { window.location.reload(); }, 400);
      }, function (e) { failure(ctx, e); });
    }
    open(p.parentId || p.nodeId);
  }

  /* ---------------------------------------------------------------------------------------------- me */

  function me(ctx, root) {
    var h = ctx.h;
    var t = ctx.t;
    ctx.call('me', {}).then(function (m) {
      root.appendChild(h('div', { class: 'wt-card' }, [
        h('h3', { text: m.name }),
        h('p', { class: 'wt-muted', text: m.login + (m.email ? ' · ' + m.email : '') }),
        m.lastVisit ? h('p', { class: 'wt-muted', text: t('Last visit: %time').replace('%time', when(m.lastVisit)) }) : null
      ]));
      var labels = { account: t('My account'), password: t('Password'), passkeys: t('Passkeys'), sessions: t('Sessions and devices'),
        security: t('Security'), notifications: t('Notifications'), drafts: t('My drafts'), bookmarks: t('Bookmarks') };
      var nav = h('nav', { class: 'wt-section wt-links', 'aria-label': t('My account') });
      Object.keys(m.links).forEach(function (k) { nav.appendChild(h('a', { href: m.links[k], text: labels[k] || k })); });
      root.appendChild(nav);

      var prefs = h('div', { class: 'wt-section wt-prefs' }, [h('h3', { text: t('The sidebar') })]);
      var radios = function (legend, name, options, value, apply) {
        var fs = h('fieldset', { class: 'wt-section wt-choice' }, [h('legend', { text: legend })]);
        options.forEach(function (o) {
          fs.appendChild(h('label', { class: 'wt-check' }, [
            h('input', { type: 'radio', name: name, checked: value === o[0], onchange: function () { apply(o[0]); } }), o[1]]));
        });
        return fs;
      };
      /* Position: the four edges (F17, F23); on top and bottom, which end of the bar the tab is anchored at */
      var anchored = radios(t('Anchored at'), 'wt-align', [['start', t('Left')], ['end', t('Right')]], ctx.state.align,
        function (v) { ctx.setState({ align: v, offset: 0 }); });
      anchored.hidden = ctx.state.edge !== 'top' && ctx.state.edge !== 'bottom';
      prefs.appendChild(radios(t('Position'), 'wt-edge', [['left', t('Left')], ['right', t('Right')], ['top', t('Top')], ['bottom', t('Bottom')]],
        ctx.state.edge, function (v) {
          ctx.setState({ edge: v, offset: 0 });
          anchored.hidden = v !== 'top' && v !== 'bottom';
        }));
      prefs.appendChild(anchored);
      prefs.appendChild(radios(t('When open'), 'wt-mode', [['push', t('Beside the page')], ['overlay', t('Over the page')]], ctx.state.mode,
        function (v) { ctx.setState({ mode: v }); }));
      prefs.appendChild(ctx.host.themeChooser());
      var only = h('input', { type: 'checkbox', id: 'wt-device', checked: ctx.deviceOnly(), onchange: function () { ctx.deviceOnly(only.checked); } });
      prefs.appendChild(h('label', { class: 'wt-check', for: 'wt-device' }, [only, t('Keep these settings only on this device')]));
      root.appendChild(prefs);
      var signOut = h('div', { class: 'wt-row wt-primary' }, [h('a', { class: 'wt-btn', href: m.logout, text: t('Sign out') })]);
      root.appendChild(signOut);
      if (root.getAttribute('data-panel') === 'me') { ctx.primary(signOut); }
    }, function (err) { failure(ctx, err); });
  }

  window.ExpwtPanels = { page: page, create: create, structure: structure, me: me };
}());
