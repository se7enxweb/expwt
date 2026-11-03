/*
 * expwt: the panels of the website sidebar, loaded when the sidebar is first opened (decision F12). Each panel is a
 * function( ctx, root ) that fills root (ctx: expwt.js context(): h, t, call, say, boot, node, state ...).
 *
 *   page       the page's fields on a draft: the sidebar form and editing on the page itself (decision 11, F1),
 *              save, preview, publish now or later, unpublish date, discard (decision 13); soft locks and the
 *              field by field merge (F11)
 *   create     new content below or beside the page with a quick title (decision 16)
 *   structure  hide and reveal, move, add a location (a picker on the site), the trash, sort by drag (decision 14)
 *   sites      the page on the other sites and in its other languages, side by side with this one (F27)
 *   seo        the SEO check of the rendered page and of its internal links; a finding opens its field (F26)
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

  /* Lost work (F28): what is typed is saved to the server's draft every few seconds, only when something changed and
     never into the field's editor that has the focus (saving it reloads its frame); and it is kept on this device
     (localStorage) until the server has it. Coming back to the page, the Page panel offers what the server never got.
       expwt.copy.<object>.<language>               the panel's own fields: {at, node, name, fields, base}
       expwt.copyf.<object>.<language>.<field>      a field's editor (its frame writes it): {at, entries: [[name, value]]}
     Pure, for the tests (tests/js/autosave.test.js). */
  var Autosave = {
    EVERY: 5000,
    QUIET: 1500,
    KEEP: 14 * 86400000,
    key: function (objectId, language) { return 'expwt.copy.' + objectId + '.' + language; },
    frameKey: function (objectId, language, identifier) { return 'expwt.copyf.' + objectId + '.' + language + '.' + identifier; },
    read: function (storage, key) {
      try { return JSON.parse(storage.getItem(key) || 'null'); } catch (e) { return null; }
    },
    write: function (storage, key, value) {
      try {
        if (value === null) { storage.removeItem(key); } else { storage.setItem(key, JSON.stringify(value)); }
        return true;
      } catch (e) { return false; }
    },
    /* the keys of the storage that start with prefix */
    keys: function (storage, prefix) {
      var out = [];
      try {
        for (var i = 0; i < storage.length; i++) {
          var k = storage.key(i);
          if (k && k.indexOf(prefix) === 0) { out.push(k); }
        }
      } catch (e) { return []; }
      return out;
    },
    fresh: function (copy, now) { return !!(copy && copy.at && now - copy.at >= 0 && now - copy.at < Autosave.KEEP); },
    /* the panel's own fields are saved once typing paused for QUIET */
    formDue: function (dirty, lastChange, now) { return dirty > 0 && now - lastChange >= Autosave.QUIET; },
    /* the editors to save now: changed, loaded, and not the one with the focus */
    framesDue: function (entries, active) {
      return entries.filter(function (e) { return e.changed && e.ready && e.frame && e.frame !== active; });
    },
    /* the fields of a copy whose value is not what the server has now (what the user would get back) */
    differs: function (copyFields, current) {
      return Object.keys(copyFields || {}).filter(function (k) {
        return Object.prototype.hasOwnProperty.call(current, k) && copyFields[k] !== current[k];
      });
    },
    /* the copies of other pages that are too old: removed when a page is opened */
    expired: function (storage, now) {
      Autosave.keys(storage, 'expwt.copy').forEach(function (k) {
        if (!Autosave.fresh(Autosave.read(storage, k), now)) { Autosave.write(storage, k, null); }
      });
    }
  };
  window.ExpwtAutosave = Autosave;

  /* Exponential Intelligence on a field (E1). Pure, for the tests (tests/js/ai.test.js).
       actionsFor  the actions the page's offer has for a datatype, none where the user may not edit the page
       plainText   an HTML answer (rich text) as text to read: each block on its own line, no markup */
  var Ai = {
    actionsFor: function (offer, can, datatype) {
      if (!offer || !offer.actions || !can || !can.edit) { return []; }
      return offer.actions.filter(function (a) { return (a.datatypes || []).indexOf(datatype) >= 0; });
    },
    plainText: function (html) {
      return String(html || '')
        .replace(/<\s*br\s*\/?\s*>/gi, '\n')
        .replace(/<\/\s*(p|li|h[1-6]|div|blockquote|tr|pre)\s*>/gi, '\n')
        .replace(/<[^>]*>/g, '')
        .replace(/&nbsp;/g, ' ').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"').replace(/&#0*39;/g, '\'')
        .replace(/&amp;/g, '&')
        .replace(/[ \t]+\n/g, '\n').replace(/\n{3,}/g, '\n\n').trim();
    }
  };
  var plainText = Ai.plainText;
  window.ExpwtAi = Ai;

  function local() {
    try { return window.localStorage; } catch (e) { return null; }
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
    /* another translation of the page, chosen in the Sites panel (F27) */
    if (ctx.host.pageLanguage && !ctx.host.draftTarget) { target = Object.assign({ language: ctx.host.pageLanguage }, target); }
    root.appendChild(h('p', { class: 'wt-muted', text: t('Loading ...') }));
    ctx.call('page.load', target).then(function (p) {
      root.textContent = '';
      renderPage(ctx, root, p);
      /* the page's comments, moderated in place (E4): below the fields, on the page itself (not a new draft) */
      if (ctx.boot && ctx.boot.comments && ctx.node && !ctx.host.draftTarget) { comments(ctx, root); }
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
      /* a translation chosen in the Sites panel: its language, and the way back to the page's own */
      if (ctx.host.pageLanguage) {
        chips.appendChild(document.createTextNode(' '));
        chips.appendChild(h('span', { class: 'wt-chip is-draft', text: p.language }));
        chips.appendChild(document.createTextNode(' '));
        chips.appendChild(h('button', { type: 'button', class: 'wt-btn wt-chip-btn', text: t('Back to this site\'s language'), onclick: function () {
          ctx.host.pageLanguage = null;
          ctx.show('page');
        } }));
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
    /* the review hold (E14): held or not, by whom and why, Hold for review / Release for those the kernel lets */
    if (p.hold || p.holdMissing) { root.appendChild(hold(ctx, p)); }
    /* what this device kept and the server never got (F28): offered back, see offer() */
    var offerBox = h('div', {});
    root.appendChild(offerBox);

    if (!p.can.edit) {
      ctx.host.pendingField = null;
      if (!(p.hold && p.hold.held)) {
        root.appendChild(h('p', { class: 'wt-notice', text: t('You may read this page but not edit it.') }));
      }
      return;
    }

    /* Edit on the page: on or off for this browser tab (the host keeps it); where the panel lies over the page (a
       phone) it gets out of the way at once, the page shows its outlines and the pill "Editing · Done" */
    var inlineBtn = h('button', { type: 'button', class: 'wt-btn', 'aria-pressed': inline ? 'true' : 'false', id: 'wt-inline', 'aria-keyshortcuts': 'Alt+Shift+' + ctx.host.keys().inline, text: t('Edit on the page'), onclick: function () {
      var on = ctx.editing(!inline);
      if (on && ctx.overlay()) { ctx.host.toggle(false); }
    } });
    var tools = h('div', { class: 'wt-row' }, [
      inlineBtn,
      h('a', { class: 'wt-btn', href: p.urls.preview, target: '_blank', rel: 'noopener', text: t('Preview') }),
      /* the device preview (E8): the page and the draft in frames of several devices, with expwt/edit */
      p.nodeId && ctx.boot.capabilities && ctx.boot.capabilities.edit
        ? h('button', { type: 'button', class: 'wt-btn', id: 'wt-devices', 'aria-haspopup': 'dialog', text: t('Devices'), onclick: function () { devices(ctx, p); } })
        : null,
      h('a', { class: 'wt-btn', href: p.urls.fullEdit, text: t('Full editor') })
    ]);
    head.appendChild(tools);

    var form = h('form', { class: 'wt-section wt-fields', novalidate: true, onsubmit: function (e) { e.preventDefault(); save(); } });
    var order = [];
    var others = [];
    var frames = {};
    /* The fields of a category other than the default one (content.ini [ClassAttributeSettings], "Meta": the page's
       metadata) in a group of their own at the end, closed until opened, as content/edit has them; a field of the
       page clicked while editing on the page opens its group (reveal()) */
    var categories = {};
    (p.categories || []).forEach(function (c) { categories[c.id] = c; });
    var groups = {};
    var groupOrder = [];
    function groupOf(f) {
      var c = categories[f.category];
      if (!c || c['default']) { return null; }
      if (!groups[c.id]) {
        var fields = h('div', { class: 'wt-fields wt-group-fields' });
        groups[c.id] = { el: h('details', { class: 'wt-field wt-group', 'data-group': c.id }, [
          h('summary', { text: c.name || c.id }), fields]), fields: fields };
        groupOrder.push(c.id);
      }
      return groups[c.id];
    }
    function place(f, row) {
      var group = groupOf(f);
      if (group) { group.fields.appendChild(row); return; }
      order.push(row);
      form.appendChild(row);
    }
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
        place(f, embedRow);
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
      } else if (aiFor(f).length) {
        /* a field with Exponential Intelligence: a group (its buttons are no part of the input's label) */
        row = h('div', { class: 'wt-field', 'data-field': f.identifier, role: 'group', 'aria-label': f.name,
          onmouseenter: function () { highlight(f.identifier, true); }, onmouseleave: function () { highlight(f.identifier, false); } }, [
          h('label', { class: 'wt-field-name', for: id, text: f.name + (f.required ? ' (' + t('required') + ')' : '') }), input.el]);
      }
      if (f.input === 'richtext' || f.input === 'textarea' || f.input === 'multiselect') {
        row.classList.add('is-wide');
      }
      rows[f.identifier] = row;
      place(f, row);
    });
    p.fields.forEach(function (f) { aiTools(f); });
    /* the Images part of each image field (E7), told when its editor saved a new image */
    var imageParts = {};
    p.fields.forEach(function (f) { imageTools(f); });
    groupOrder.forEach(function (id) {
      order.push(groups[id].el);
      form.appendChild(groups[id].el);
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
    /* "More" is open until the user closes it (More again, Escape, a click outside it): nothing else closes it. Opened,
       its fields scroll into the bar's view (they lie below the grid); a new arrangement of the bar waits until it is
       closed (its frames load when they come into view, and their sizes would arrange the bar while it is open). */
    var arrangeLater = false;
    var more = h('details', { class: 'wt-section wt-more',
      onkeydown: function (e) { if (e.key === 'Escape' && more.open) { e.stopPropagation(); more.open = false; moreSummary.focus(); } },
      ontoggle: function () {
        if (more.open) {
          window.requestAnimationFrame(function () { moreSummary.scrollIntoView({ block: 'start', behavior: 'smooth' }); });
        } else if (arrangeLater) {
          arrangeLater = false;
          arrange();
        }
      } }, [moreSummary, moreFields]);
    root.appendChild(more);
    var outside = function (e) {
      if (!root.isConnected) { document.removeEventListener('pointerdown', outside, true); return; }
      if (more.open && (e.composedPath ? e.composedPath() : []).indexOf(more) < 0) { more.open = false; }
    };
    document.addEventListener('pointerdown', outside, true);
    var flat = h('div', {});
    root.appendChild(flat);

    /* On top and bottom (a wide window): every field in the grid first, then the last ones behind "More" until the
       bar's body needs no scroll bar (at least one field stays). Again whenever the bar changes its size. */
    function arrange() {
      /* a phone (the bar on top or bottom is a sheet as high as its contents, its body scrolls): the fields one
         below the other as on the sides, no "More" -- in its little room "More" opened below the visible part */
      var hz = ctx.horizontal() && window.innerWidth >= 640;
      if (hz && more.open) { arrangeLater = true; return; }
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
        /* the groups of fields (Meta) are behind "More" on top and bottom: opened in the grid they would need the bar's
           scroll bar, behind "More" they open below the grid */
        var isGroup = function (row) { return row.classList.contains('wt-group'); };
        order.filter(isGroup).forEach(function (row) { put(moreFields, row, null); });
        var inGrid = order.filter(function (row) { return !isGroup(row); });
        while (over() && inGrid.length > 1) {
          put(moreFields, inGrid.pop(), moreFields.firstChild);
          moreSummary.textContent = t('More') + ' (' + moreFields.children.length + ')';
        }
        /* a smaller field after a long one may still fit: back into the grid in its own place, while it fits */
        Array.prototype.slice.call(moreFields.children).filter(function (row) { return !isGroup(row); }).forEach(function (row) {
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

    /* the saves one after the other (the first makes the draft, the others go into it): the buttons and autosave */
    var queue = Promise.resolve();
    function serial(fn) {
      var run = queue.then(fn, fn);
      queue = run.catch(function () {});
      return run;
    }
    var saveBtn = h('button', { type: 'button', class: 'wt-btn', 'data-action': 'save', 'aria-keyshortcuts': 'Alt+Shift+' + ctx.host.keys().save, text: t('Save draft'), onclick: function () {
      serial(function () {
        return saveFrames().then(function (savedSome) {
          if (Object.keys(dirtyFields()).length) { return save(); }
          if (!savedSome) { ctx.say(t('Nothing to save.')); }
          return null;
        });
      }).catch(function () {});
    } });
    var publishBtn = h('button', { type: 'button', class: 'wt-btn is-primary', text: t('Publish'), onclick: function () { serial(publish); } });
    var discardBtn = h('button', { type: 'button', class: 'wt-btn is-danger', text: t('Discard draft'), onclick: function () { discard(); } });
    var mainActions = h('div', { class: 'wt-row wt-primary' }, [saveBtn, publishBtn, discardBtn]);
    root.appendChild(mainActions);
    if (root.getAttribute('data-panel') === 'page') { ctx.primary(mainActions); }
    discardBtn.hidden = !version;

    /* Exponential Intelligence on the fields (E1, where expai is on for the user): the actions for each field's
       datatype (expai's Write, Rewrite, Shorten, Translate, Suggest; Summarize; the alt text of an image), asked
       for the user's draft. The answer is a suggestion under the field; "Use it" puts it into the field: a field of
       the form takes it into its input (saved with the draft, autosave included), a field of its own editor gets
       it written into the draft and loads again. Nothing is published. */
    function aiFor(f) {
      return Ai.actionsFor(p.ai, p.can, f.datatype);
    }
    function aiTools(f) {
      var acts = aiFor(f);
      var row = rows[f.identifier];
      if (!acts.length || !row) { return; }
      var key = 'wt-ai-' + f.identifier;
      var chosen = null;
      var instruction = null;
      var target = null;
      var last = null;
      var extra = h('div', { class: 'wt-ai-input', hidden: true });
      var note = h('p', { class: 'wt-ai-note', role: 'status', 'aria-live': 'polite' });
      var answer = h('div', { class: 'wt-ai-answer', hidden: true });
      var ask = h('button', { type: 'button', class: 'wt-btn is-primary', text: t('Ask'), onclick: function () { run(chosen); } });
      var buttons = acts.map(function (a) {
        return h('button', { type: 'button', class: 'wt-btn wt-chip-btn', 'data-action': a.name, 'aria-pressed': 'false', text: a.label,
          onclick: function () { pick(a); } });
      });
      function busy(on) {
        buttons.concat([ask]).forEach(function (b) { b.disabled = on; });
        row.setAttribute('aria-busy', on ? 'true' : 'false');
      }
      function pick(a) {
        chosen = a;
        instruction = null;
        target = null;
        extra.textContent = '';
        answer.hidden = true;
        buttons.forEach(function (b) { b.setAttribute('aria-pressed', b.getAttribute('data-action') === a.name ? 'true' : 'false'); });
        if (a.input === 'none') { extra.hidden = true; run(a); return; }
        if (a.input === 'language') {
          target = h('select', { id: key + '-l' }, p.ai.languages.map(function (l) {
            return h('option', { value: l.locale, text: l.name, selected: l.locale === p.language });
          }));
          extra.appendChild(h('label', { class: 'wt-field-name', for: key + '-l', text: t('Into which language?') }));
          extra.appendChild(target);
        } else {
          instruction = h('textarea', { id: key + '-i', rows: 2 });
          extra.appendChild(h('label', { class: 'wt-field-name', for: key + '-i',
            text: a.input === 'instruction' ? t('What should be written?') : t('What should change? (optional)') }));
          extra.appendChild(instruction);
        }
        extra.appendChild(h('div', { class: 'wt-row' }, [ask]));
        extra.hidden = false;
        (instruction || target).focus();
      }
      /* a field of its own editor typed into: saved to the draft first, so the request and the answer see it */
      function flush() {
        var entry = frames[f.identifier];
        return entry && entry.frame && entry.ready && entry.changed ? serial(function () { return saveOne(entry); }) : Promise.resolve();
      }
      function run(a) {
        if (!a) { return; }
        if (a.input === 'instruction' && instruction && !instruction.value.trim()) {
          note.textContent = t('Say what should be written.');
          instruction.focus();
          return;
        }
        var body = { objectId: p.objectId, language: p.language, field: f.identifier, action: a.name };
        if (version) { body.version = version; }
        if (instruction) { body.instruction = instruction.value; }
        if (target) { body.targetLanguage = target.value; }
        if (inputs[f.identifier]) { body.value = values[f.identifier]; }
        busy(true);
        note.textContent = t('Asking ...');
        flush().then(function () { return ctx.call('ai.field', body); }).then(function (r) {
          busy(false);
          if (r.version && r.version !== version) {
            version = r.version;
            discardBtn.hidden = false;
            drawChips();
          }
          show(r);
        }, function (err) {
          busy(false);
          note.textContent = err && err.message ? err.message : t('Something went wrong on the server.');
        });
      }
      function show(r) {
        last = r;
        answer.textContent = '';
        answer.appendChild(h('p', { class: 'wt-ai-label', text: t('Suggestion') }));
        answer.appendChild(h('div', { class: 'wt-ai-text', text: f.input === 'richtext' ? plainText(r.value) : r.value }));
        if (r.note) { answer.appendChild(h('p', { class: 'wt-muted', text: r.note })); }
        answer.appendChild(h('div', { class: 'wt-row' }, [
          h('button', { type: 'button', class: 'wt-btn is-primary', text: t('Use it'), onclick: use }),
          h('button', { type: 'button', class: 'wt-btn', text: t('Discard'), onclick: function () {
            answer.hidden = true;
            note.textContent = '';
          } })
        ]));
        answer.hidden = false;
        note.textContent = t('A suggestion: the field keeps its value until you use it.');
      }
      function use() {
        if (!last) { return; }
        var value = last.value;
        if (inputs[f.identifier]) {
          inputs[f.identifier].set(value);
          change(f.identifier, value, true);
          answer.hidden = true;
          note.textContent = t('It is in the field: Save draft keeps it, or autosave does.');
          return;
        }
        busy(true);
        flush().then(function () {
          return serial(function () {
            return ctx.call('ai.apply', { objectId: p.objectId, version: version, language: p.language, field: f.identifier, value: value });
          });
        }).then(function (r) {
          busy(false);
          if (r.version && r.version !== version) { version = r.version; discardBtn.hidden = false; drawChips(); }
          var entry = frames[f.identifier];
          if (entry && entry.frame) {
            entry.ready = false;
            entry.changed = false;
            entry.frame.src = frameSrc(f);
          }
          answer.hidden = true;
          note.textContent = t('It is in the draft.');
          ctx.say(t('The draft is saved.'), 'ok');
        }, function (err) {
          busy(false);
          note.textContent = err && err.message ? err.message : t('Something went wrong on the server.');
        });
      }
      row.appendChild(h('div', { class: 'wt-ai', role: 'group', 'aria-label': t('Exponential Intelligence') + ': ' + f.name }, [
        h('div', { class: 'wt-ai-actions' }, [h('span', { class: 'wt-ai-mark', title: t('Exponential Intelligence'), text: t('AI') })].concat(buttons)),
        extra, note, answer
      ]));
    }

    /* The focal point and crop of an image field (E7), with a kernel that stores them (p.images): below the field's
       editor, closed until opened. Click or drag on the image sets the focal point (arrow keys move it); Crop adds a
       box, moved by dragging it or with the arrow keys, sized by its corners or with Shift and the arrow keys. Every
       alias the page's templates use is previewed live (Focal.plan(), what the kernel computes); Save draft writes
       both into the user's draft (images.save), where the kernel makes the aliases again around the point; the
       previews then show those files. Nothing reaches the published page before the draft is published. */
    function imageTools(f) {
      var row = rows[f.identifier];
      if (!p.images || f.input !== 'image' || !row) { return; }
      var key = 'wt-focal-' + f.identifier;
      var data = null;
      var focal = null;
      var crop = null;
      var saved = false;
      var drag = null;
      var dirty = false;
      var body = h('div', { class: 'wt-focal-body' });
      var part = h('details', { class: 'wt-focal', 'data-focal': f.identifier }, [h('summary', { text: t('Focal point and crop') }), body]);
      var note = h('p', { class: 'wt-ai-note', role: 'status', 'aria-live': 'polite' });
      row.appendChild(part);
      /* a new image saved by the field's editor: read again, unless a focal point waits to be saved here */
      imageParts[f.identifier] = { reload: function () { if (part.open && !dirty) { load(); } else if (!part.open) { data = null; } } };
      part.addEventListener('toggle', function () { if (part.open && !data) { load(); } });

      function load() {
        body.textContent = '';
        body.appendChild(h('p', { class: 'wt-muted', text: t('Loading ...') }));
        var input = { objectId: p.objectId, language: p.language, field: f.identifier };
        if (version) { input.version = version; }
        ctx.call('images.load', input).then(function (r) { saved = false; draw(r); }, function (err) {
          body.textContent = '';
          body.appendChild(h('p', { class: 'wt-notice is-bad', text: err && err.message ? err.message : t('Something went wrong on the server.') }));
        });
      }
      function draw(r) {
        data = r;
        dirty = false;
        focal = r.focal;
        crop = r.crop;
        body.textContent = '';
        if (!r.supported) {
          body.appendChild(h('p', { class: 'wt-muted', text: t('This image cannot have a focal point.') }));
          return;
        }
        var img = h('img', { class: 'wt-focal-img', src: r.original.url, alt: r.original.alt || '', draggable: 'false',
          width: r.original.width || null, height: r.original.height || null });
        var marker = h('button', { type: 'button', class: 'wt-focal-point', id: key + '-point', 'aria-label': t('Focal point'),
          'aria-describedby': key + '-help' });
        var corners = ['nw', 'ne', 'sw', 'se'].map(function (c) { return h('span', { class: 'wt-focal-handle is-' + c, 'data-corner': c }); });
        var box = h('div', { class: 'wt-focal-crop', tabindex: '0', role: 'group', 'aria-label': t('Crop box'), 'aria-describedby': key + '-help' }, corners);
        var stage = h('div', { class: 'wt-focal-stage' }, [img, box, marker]);
        var cropBtn = h('button', { type: 'button', class: 'wt-btn', 'aria-pressed': crop ? 'true' : 'false', text: t('Crop'), onclick: function () {
          crop = crop ? null : Focal.start();
          if (crop && focal) { focal = Focal.nudge(focal, 0, 0, crop); }
          changed();
        } });
        var resetBtn = h('button', { type: 'button', class: 'wt-btn', text: t('Reset'), onclick: function () { focal = null; crop = null; changed(); } });
        var saveBtn = h('button', { type: 'button', class: 'wt-btn is-primary', text: t('Save draft'), onclick: save });
        var previews = h('div', { class: 'wt-focal-previews' });
        body.appendChild(h('p', { class: 'wt-muted', id: key + '-help', text: t('Click or drag on the image to set the focal point. Arrow keys move it; on the crop box, Shift and an arrow key change its size.') }));
        body.appendChild(stage);
        body.appendChild(h('div', { class: 'wt-row' }, [cropBtn, resetBtn, saveBtn]));
        body.appendChild(note);
        body.appendChild(h('h4', { class: 'wt-focal-title', text: t('How each size will look') }));
        body.appendChild(previews);

        function place() {
          /* without a focal point the marker waits in the middle, faint, so the keyboard reaches it too */
          var at = focal || { x: 0.5, y: 0.5 };
          marker.classList.toggle('is-unset', !focal);
          marker.style.setProperty('--wt-fx', (at.x * 100) + '%');
          marker.style.setProperty('--wt-fy', (at.y * 100) + '%');
          box.hidden = !crop;
          if (crop) {
            box.style.setProperty('--wt-cx', (crop.x * 100) + '%');
            box.style.setProperty('--wt-cy', (crop.y * 100) + '%');
            box.style.setProperty('--wt-cw', (crop.width * 100) + '%');
            box.style.setProperty('--wt-ch', (crop.height * 100) + '%');
          }
          cropBtn.setAttribute('aria-pressed', crop ? 'true' : 'false');
          stage.classList.toggle('is-cropping', !!crop);
        }
        function preview() {
          previews.textContent = '';
          if (!r.aliases.length) {
            previews.appendChild(h('p', { class: 'wt-muted', text: t('The templates of this page use no size of this image yet.') }));
            return;
          }
          r.aliases.forEach(function (a) {
            var plan = Focal.plan(a.filters, r.original.width, r.original.height, focal, crop);
            var view = saved ? null : Focal.view(plan, r.original.width, r.original.height);
            var frame;
            var size = '';
            if (view) {
              /* live: the original placed in a frame of the alias's shape, as the kernel will cut it */
              var shown = h('img', { class: 'wt-focal-live', src: r.original.url, alt: '', draggable: 'false' });
              shown.style.setProperty('--wt-iw', view.imageWidth + '%');
              shown.style.setProperty('--wt-il', view.left + '%');
              shown.style.setProperty('--wt-it', view.top + '%');
              frame = h('div', { class: 'wt-focal-frame' }, [shown]);
              frame.style.setProperty('--wt-ratio', String(view.ratio));
              frame.style.setProperty('--wt-max', Math.max(1, plan.width) + 'px');
              size = plan.width + ' × ' + plan.height;
            } else if (a.url) {
              /* saved, or a size the preview cannot follow: the alias's file as the kernel made it */
              frame = h('div', { class: 'wt-focal-frame is-file' }, [h('img', { class: 'wt-focal-file', src: a.url, alt: '', width: a.width || null, height: a.height || null })]);
              frame.style.setProperty('--wt-max', Math.max(1, a.width) + 'px');
              size = a.width + ' × ' + a.height;
            } else {
              frame = h('p', { class: 'wt-muted', text: t('This size keeps its own crop.') });
            }
            previews.appendChild(h('figure', { class: 'wt-focal-preview', 'data-alias': a.name }, [frame,
              h('figcaption', {}, [h('span', { class: 'wt-focal-name', text: a.name }), h('span', { class: 'wt-muted', text: size })])]));
          });
        }
        function changed() {
          saved = false;
          dirty = true;
          note.textContent = '';
          place();
          preview();
        }

        /* the pointer on the image: the focal point, the crop box moved, or one of its corners */
        stage.addEventListener('pointerdown', function (e) {
          if (e.button !== undefined && e.button !== 0) { return; }
          var at = Focal.point(img.getBoundingClientRect(), e.clientX, e.clientY);
          var corner = e.target.getAttribute && e.target.getAttribute('data-corner');
          if (crop && corner) {
            drag = { kind: 'corner', corner: corner };
          } else if (crop && e.target === box) {
            drag = { kind: 'box', from: at, start: crop };
          } else {
            drag = { kind: 'point' };
            focal = Focal.nudge(at, 0, 0, crop);
            changed();
          }
          stage.setPointerCapture(e.pointerId);
          e.preventDefault();
        });
        stage.addEventListener('pointermove', function (e) {
          if (!drag) { return; }
          var at = Focal.point(img.getBoundingClientRect(), e.clientX, e.clientY);
          if (drag.kind === 'point') { focal = Focal.nudge(at, 0, 0, crop); }
          if (drag.kind === 'box') { crop = Focal.move(drag.start, at.x - drag.from.x, at.y - drag.from.y); }
          if (drag.kind === 'corner') { crop = Focal.resize(crop, drag.corner, at); }
          if (drag.kind !== 'point' && focal) { focal = Focal.nudge(focal, 0, 0, crop); }
          changed();
        });
        function stop() { drag = null; }
        stage.addEventListener('pointerup', stop);
        stage.addEventListener('pointercancel', stop);
        marker.addEventListener('keydown', function (e) {
          var d = Focal.step(e.key, e.shiftKey);
          if (!d) { return; }
          e.preventDefault();
          focal = Focal.nudge(focal || { x: 0.5, y: 0.5 }, d[0], d[1], crop);
          changed();
        });
        box.addEventListener('keydown', function (e) {
          var d = Focal.step(e.key, e.shiftKey);
          if (!d || !crop) { return; }
          e.preventDefault();
          crop = e.shiftKey ? Focal.grow(crop, d[0], d[1]) : Focal.move(crop, d[0], d[1]);
          if (focal) { focal = Focal.nudge(focal, 0, 0, crop); }
          changed();
        });
        place();
        preview();

        function save() {
          var entry = frames[f.identifier];
          var first = entry && entry.frame && entry.ready && entry.changed ? serial(function () { return saveOne(entry); }) : Promise.resolve();
          saveBtn.disabled = true;
          note.textContent = t('Saving ...');
          first.then(function () {
            return serial(function () {
              var send = Focal.body(focal, crop);
              return ctx.call('images.save', { objectId: p.objectId, version: version, language: p.language, field: f.identifier, focal: send.focal, crop: send.crop });
            });
          }).then(function (res) {
            saveBtn.disabled = false;
            if (res.version && res.version !== version) { version = res.version; discardBtn.hidden = false; drawChips(); }
            /* the field's editor shows the draft's image again; the previews show the files the kernel made */
            if (entry && entry.frame) { entry.ready = false; entry.changed = false; entry.frame.src = frameSrc(f); }
            saved = true;
            draw(res);
            note.textContent = t('Saved: the sizes are made again around the focal point.');
            ctx.say(t('The draft is saved.'), 'ok');
            keep();
          }, function (err) {
            saveBtn.disabled = false;
            note.textContent = err && err.message ? err.message : t('Something went wrong on the server.');
          });
        }
      }
    }

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

    /* a field's row in view in the panel, also when it is in a closed group or behind "More" on top and bottom */
    function reveal(row) {
      var closed = row && row.parentElement ? row.parentElement.closest('details') : null;
      while (closed) {
        closed.open = true;
        closed = closed.parentElement ? closed.parentElement.closest('details') : null;
      }
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
        var run = serial(function () { return entry ? saveOne(entry) : save().then(function () { return true; }); });
        run.then(function (saved) {
          if (entry && saved === false && Object.keys(dirtyFields()).length) { return save().then(function () { return true; }); }
          return saved;
        }).then(function (saved) {
          note.textContent = saved === false ? t('Nothing to save.') : t('The draft is saved.');
        }, function () { note.textContent = t('This value does not fit the field.'); });
      } });
      var publishB = h('button', { type: 'button', class: 'wt-btn is-primary', text: t('Publish'), onclick: function () { serial(publish); } });
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

    /* quiet: autosave, which says nothing in the status line (the panel shows "Saved automatically at ...") */
    function saveOne(entry, quiet) {
      return new Promise(function (resolve, reject) {
        entry.waiter = { resolve: resolve, reject: reject };
        entry.quiet = !!quiet;
        entry.frame.contentWindow.postMessage({ expwt: 'field-save', auto: !!quiet }, window.location.origin);
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
        if (entry) { entry.ready = true; entry.changed = false; }
        if (entry && entry.quiet) { autosaved(); } else { ctx.say(t('The draft is saved.'), 'ok'); }
        if (entry && entry.waiter) { entry.waiter.resolve(true); entry.waiter = null; }
        if (entry && imageParts[entry.field.identifier]) { imageParts[entry.field.identifier].reload(); }
        keep();
      } else if (m.expwt === 'field-changed') {
        /* typed into: autosave saves it once the focus has left it; the frame keeps its copy on this device */
        if (entry) { entry.changed = !!m.changed; lastChange = Date.now(); }
        keep();
      } else if (m.expwt === 'field-unchanged') {
        if (entry) { entry.changed = false; }
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


    /* ---- lost work (F28): autosave to the server's draft and the copy on this device */
    var store = local();
    var copyKey = Autosave.key(p.objectId, p.language);
    var framePrefix = Autosave.frameKey(p.objectId, p.language, '');
    var lastChange = 0;
    var keepTimer = null;
    var autoBusy = false;
    var autoNote = h('p', { class: 'wt-muted wt-autosaved' });
    head.appendChild(autoNote);
    /* the panel's fields not yet on the server, and which editors keep a copy of their own: on this device */
    function keep() {
      clearTimeout(keepTimer);
      keepTimer = null;
      if (!store) { return; }
      var fields = dirtyFields();
      var from = {};
      Object.keys(fields).forEach(function (k) { from[k] = base[k]; });
      var editors = Object.keys(frames).filter(function (id) { return frames[id].changed; });
      if (!Object.keys(fields).length && !editors.length) { Autosave.write(store, copyKey, null); unmark(); return; }
      Autosave.write(store, copyKey, { at: Date.now(), node: ctx.node || null, name: p.name, fields: fields, base: from, frames: editors });
    }
    function keepSoon() {
      if (!keepTimer) { keepTimer = setTimeout(keep, 400); }
    }
    /* published or discarded: nothing of this page is kept on the device any more */
    /* the mark on the closed tab ("Unsaved changes on this page") goes with the copy */
    function unmark() {
      var mark = ctx.host.tab ? ctx.host.tab.querySelector('[data-badge="unsaved"]') : null;
      if (mark) { mark.remove(); }
    }
    function forget() {
      unmark();
      if (!store) { return; }
      Autosave.write(store, copyKey, null);
      Autosave.keys(store, framePrefix).forEach(function (k) { Autosave.write(store, k, null); });
    }
    function autosaved() {
      var d = new Date();
      autoNote.textContent = t('Saved automatically at %time').replace('%time', pad(d.getHours()) + ':' + pad(d.getMinutes()));
    }
    function autosave() {
      if (!root.isConnected) { clearInterval(autoTimer); window.removeEventListener('pagehide', keep); return; }
      if (autoBusy || conflictBox.firstChild) { return; }
      var active = root.getRootNode ? root.getRootNode().activeElement : null;
      var due = Autosave.framesDue(Object.keys(frames).map(function (id) { return frames[id]; }), active);
      var form = Autosave.formDue(Object.keys(dirtyFields()).length, lastChange, Date.now());
      if (!due.length && !form) { return; }
      autoBusy = true;
      serial(function () {
        var chain = Promise.resolve();
        due.forEach(function (e) { chain = chain.then(function () { return saveOne(e, true); }); });
        return chain.then(function () { return form ? save(true) : null; });
      }).then(function () { autoBusy = false; }, function () { autoBusy = false; });
    }
    var autoTimer = setInterval(autosave, Autosave.EVERY);
    window.addEventListener('pagehide', keep);

    /* Coming back: what this device kept of the page and the server does not have, offered back (not for copies older
       than two weeks). Restore puts it into the fields and saves it to the draft; Discard forgets it. */
    function offer() {
      if (!store) { return; }
      var now = Date.now();
      Autosave.expired(store, now);
      var copy = Autosave.read(store, copyKey);
      var current = {};
      p.fields.forEach(function (f) { current[f.identifier] = f.value; });
      var back = copy ? Autosave.differs(copy.fields, current).filter(function (k) { return inputs[k]; }) : [];
      var editors = Autosave.keys(store, framePrefix).map(function (k) { return k.slice(framePrefix.length); })
        .filter(function (id) { return frames[id]; });
      if (!back.length && !editors.length) {
        if (copy) { Autosave.write(store, copyKey, null); }
        return;
      }
      var at = copy ? copy.at : 0;
      editors.forEach(function (id) { var c = Autosave.read(store, framePrefix + id); at = Math.max(at, c && c.at || 0); });
      var names = p.fields.filter(function (f) { return back.indexOf(f.identifier) >= 0 || editors.indexOf(f.identifier) >= 0; })
        .map(function (f) { return f.name; });
      offerBox.textContent = '';
      offerBox.appendChild(h('div', { class: 'wt-notice is-warn wt-offer', role: 'status' }, [
        h('p', { text: t('Changes made on this device at %time were not saved: %fields').replace('%time', when(Math.floor(at / 1000)))
          .replace('%fields', names.join(', ')) }),
        h('div', { class: 'wt-row' }, [
          h('button', { type: 'button', class: 'wt-btn is-primary', text: t('Restore'), onclick: function () {
            offerBox.textContent = '';
            back.forEach(function (k) {
              if (copy.base && Object.prototype.hasOwnProperty.call(copy.base, k)) { base[k] = copy.base[k]; }
              inputs[k].set(copy.fields[k]);
              change(k, copy.fields[k], true);
            });
            /* each editor takes its copy and saves it to the draft (its frame posts it as its own form would) */
            editors.forEach(function (id) {
              var e = frames[id];
              load(e);
              var go = function () { e.frame.contentWindow.postMessage({ expwt: 'field-restore' }, window.location.origin); };
              if (e.ready) { go(); } else { e.onReady = go; }
            });
            ctx.say(t('Restored. It is saved to the draft.'), 'ok');
            if (back.length) { serial(function () { return save(true); }).catch(function () {}); }
          } }),
          h('button', { type: 'button', class: 'wt-btn', text: t('Discard'), onclick: function () {
            offerBox.textContent = '';
            forget();
          } })
        ])
      ]));
    }
    offer();

    function dirtyFields() {
      var out = {};
      Object.keys(values).forEach(function (k) { if (values[k] !== base[k]) { out[k] = values[k]; } });
      return out;
    }

    function change(id, value, fromForm) {
      values[id] = value;
      lastChange = Date.now();
      keepSoon();
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

    function save(quiet) {
      var fields = dirtyFields();
      if (!Object.keys(fields).length) {
        if (!quiet) { ctx.say(t('Nothing to save.')); }
        return Promise.resolve(version);
      }
      var sentBase = {};
      Object.keys(fields).forEach(function (k) { sentBase[k] = base[k]; });
      if (!quiet) { ctx.say(t('Saving ...')); }
      return ctx.call('draft.save', { objectId: p.objectId, language: p.language, version: version, publishedVersion: published,
        fields: fields, base: sentBase, choices: choices }).then(function (r) {
        if (r.version) { version = r.version; }
        if (r.publishedVersion) { published = r.publishedVersion; }
        /* what was sent is saved; what was typed while it was on its way stays to be saved */
        Object.keys(fields).forEach(function (k) {
          base[k] = fields[k];
          if (rows[k]) { rows[k].setAttribute('data-dirty', values[k] !== base[k] ? 'true' : 'false'); }
        });
        choices = {};
        conflictBox.textContent = '';
        discardBtn.hidden = !version;
        p.urls.preview = r.previewUrl || p.urls.preview;
        drawChips();
        if (quiet) { autosaved(); } else { ctx.say(t('The draft is saved.'), 'ok'); }
        keep();
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
      return first.then(function (v) {
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
          forget();
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
        forget();
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

    /* a finding of another panel (the SEO check, F26) asked for a field: its row opened and in view, its editor
       focused; the row marked for a moment */
    if (ctx.host.pendingField) {
      var wanted = ctx.host.pendingField;
      ctx.host.pendingField = null;
      var wantedRow = rows[wanted];
      if (wantedRow) {
        if (frames[wanted]) { load(frames[wanted]); }
        reveal(wantedRow);
        wantedRow.setAttribute('data-wanted', 'true');
        setTimeout(function () { wantedRow.removeAttribute('data-wanted'); }, 2500);
        var into = frames[wanted] ? frames[wanted].frame : wantedRow.querySelector('input, select, textarea, [contenteditable]');
        if (into) { into.focus({ preventScroll: true }); }
      } else {
        ctx.say(t('This field cannot be edited here.'));
      }
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
        /* a value put back (a copy restored, F28) */
        set = function (v) {
          var want = String(v || '').split('|');
          Array.prototype.forEach.call(sel.options, function (o) { o.selected = want.indexOf(o.value) >= 0; });
        };
        break;
      case 'date':
      case 'datetime':
      case 'time':
        var kind = f.input;
        var d = h('input', { type: kind === 'datetime' ? 'datetime-local' : kind, id: id, value: toInput(kind, f.value),
          onchange: function () { onChange(fromInput(kind, d.value)); } });
        el = d;
        set = function (v) { d.value = toInput(kind, v); };
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
      root.appendChild(remap(ctx));
      var signOut = h('div', { class: 'wt-row wt-primary' }, [h('a', { class: 'wt-btn', href: m.logout, text: t('Sign out') })]);
      root.appendChild(signOut);
      if (root.getAttribute('data-panel') === 'me') { ctx.primary(signOut); }
    }, function (err) { failure(ctx, err); });
  }

  /* ---------------------------------------------------------------------------------------------- shortcuts */

  /* The names of the shortcuts a user may change (F30) */
  function keyNames(t) {
    return { open: t('Open the sidebar or go into it'), save: t('Save the draft'), inline: t('Edit on the page'), help: t('Keyboard shortcuts') };
  }

  /* The Me panel's shortcuts (F30): each one's letter, checked before it is kept (one letter, not one of the browser's
     or the system's, not one another shortcut has); a letter the page gives one of its links is kept, with a word that
     the page wins here. Kept with the sidebar's settings (on the server, or on this device). */
  function remap(ctx) {
    var h = ctx.h;
    var t = ctx.t;
    var K = window.ExpwtKeys;
    var names = keyNames(t);
    var box = h('fieldset', { class: 'wt-section wt-remap' }, [h('legend', { text: t('Keyboard shortcuts') })]);
    var inputs = {};
    function why(action, key, map) {
      var r = K.check(action, key, map);
      if (r === 'format') { return t('Use one letter, A to Z.'); }
      if (r === 'reserved') { return t('Alt+Shift+%key belongs to the browser or the system.').replace('%key', key.toUpperCase()); }
      if (r) { return t('Alt+Shift+%key is taken: %action.').replace('%key', key.toUpperCase()).replace('%action', names[r]); }
      return '';
    }
    K.ACTIONS.forEach(function (action) {
      var id = 'wt-key-' + action;
      var note = h('p', { class: 'wt-muted wt-key-note', id: id + '-note', role: 'status', 'aria-live': 'polite' });
      var input = h('input', { type: 'text', id: id, maxlength: '1', size: '2', autocomplete: 'off', autocapitalize: 'characters', spellcheck: 'false',
        'aria-describedby': id + '-note', value: ctx.host.keys()[action], onchange: function () {
          var key = input.value.trim().toUpperCase();
          var map = ctx.host.keys();
          var problem = why(action, key, map);
          input.setAttribute('aria-invalid', problem ? 'true' : 'false');
          if (problem) { note.textContent = problem; return; }
          input.value = key;
          var mine = Object.assign({}, ctx.state.keys || {});
          mine[action] = key;
          var plain = K.map(ctx.host.cfg.key, {});
          if (plain[action] === key) { delete mine[action]; }
          ctx.setState({ keys: mine });
          ctx.host.labelKeys();
          note.textContent = K.pageKeys(document).indexOf(key) >= 0
            ? t('This page gives Alt+Shift+%key to one of its links: here the page wins.').replace('%key', key) : t('Saved.');
        } });
      inputs[action] = input;
      box.appendChild(h('div', { class: 'wt-field wt-key' }, [
        h('label', { for: id, text: names[action] }),
        h('span', { class: 'wt-key-input' }, [h('kbd', { text: 'Alt+Shift+' }), input]),
        note]));
    });
    box.appendChild(h('div', { class: 'wt-row' }, [
      h('button', { type: 'button', class: 'wt-btn', text: t('Back to the defaults'), onclick: function () {
        ctx.setState({ keys: {} });
        ctx.host.labelKeys();
        K.ACTIONS.forEach(function (a) { inputs[a].value = ctx.host.keys()[a]; inputs[a].removeAttribute('aria-invalid'); });
      } }),
      h('button', { type: 'button', class: 'wt-btn', text: t('All shortcuts'), onclick: function () { shortcuts(ctx); } })
    ]));
    return box;
  }

  /* The cheat sheet (decision 38, F30): every shortcut of the sidebar in effect for this user on this page, a key the
     page has taken marked, and the keys of the tab. Alt+Shift+H, or All shortcuts in the Me panel. */
  function shortcuts(ctx) {
    var h = ctx.h;
    var t = ctx.t;
    var host = ctx.host;
    var keys = host.keys();
    var page = window.ExpwtKeys.pageKeys(document);
    var names = keyNames(t);
    var list = h('dl', { class: 'wt-keys' });
    function row(combo, what, taken) {
      list.appendChild(h('dt', {}, [h('kbd', { text: combo })]));
      list.appendChild(h('dd', {}, [document.createTextNode(what), taken ? h('span', { class: 'wt-muted wt-key-taken', text: ' ' + t('This page uses this key for one of its links: here the page wins.') }) : null]));
    }
    window.ExpwtKeys.ACTIONS.forEach(function (a) { row('Alt+Shift+' + keys[a], names[a], page.indexOf(keys[a]) >= 0); });
    host.available().slice(0, 9).forEach(function (p, i) { row('Alt+Shift+' + (i + 1), t('Panel: %name').replace('%name', p.label), page.indexOf(String(i + 1)) >= 0); });
    row('Escape', t('Close the sidebar'));
    var tab = h('dl', { class: 'wt-keys' });
    [['Enter', t('Open the sidebar')], ['←  →  ↑  ↓', t('Move the tab along its edge, or to the opposite edge')],
     ['Shift+←  →  ↑  ↓', t('Bigger steps')], ['Ctrl+←  →  ↑  ↓', t('Dock to that edge')],
     ['Page Up, Page Down', t('Go round the edges')], ['Home, End', t('To the start or the end of the edge')]].forEach(function (r) {
      tab.appendChild(h('dt', {}, [h('kbd', { text: r[0] })]));
      tab.appendChild(h('dd', { text: r[1] }));
    });
    var close = function () { dialog.close(); };
    var dialog = h('dialog', { class: 'wt-dialog', 'aria-labelledby': 'wt-keys-title', onclose: function () { dialog.remove(); } }, [
      h('div', { class: 'wt-dialog-head' }, [
        h('h2', { id: 'wt-keys-title', text: t('Keyboard shortcuts') }),
        h('button', { type: 'button', class: 'wt-btn wt-close', 'aria-label': t('Close'), onclick: close }, ['×'])]),
      h('div', { class: 'wt-dialog-body' }, [
        list,
        h('h3', { text: t('On the tab') }), tab,
        h('p', { class: 'wt-muted', text: t('On a Mac, Option+Shift with a letter types a character in a text field: there the field gets it.') }),
        /* the credit stays whatever the branding of the siteaccess (E10) */
        h('p', { class: 'wt-muted wt-credit', text: t('Website sidebar of Exponential') }),
        h('div', { class: 'wt-row' }, [h('button', { type: 'button', class: 'wt-btn', text: t('Change them in the Me panel'), onclick: function () {
          close();
          if (!host.state.open) { host.toggle(true); }
          host.show('me');
        } })])
      ])
    ]);
    var old = host.shadowRoot.querySelector('dialog.wt-dialog');
    if (old) { old.remove(); }
    host.shadowRoot.appendChild(dialog);
    dialog.showModal();
  }

  /* ---------------------------------------------------------------------------------------------- inbox */

  /* The approvals waiting for the user (F29): the kernel's approve workflow (collaboration items of ezapprove), each
     with its page, author and last comment, the draft to open, and for an approver a comment and Approve or Deny,
     decided through the kernel's own collaboration view (inbox.decide). */
  function inbox(ctx, root) {
    var t = ctx.t;
    root.appendChild(ctx.h('p', { class: 'wt-muted', text: t('Loading ...') }));
    ctx.call('inbox.load', {}).then(function (r) {
      root.textContent = '';
      drawInbox(ctx, root, r);
    }, function (err) { root.textContent = ''; failure(ctx, err); });
  }

  function drawInbox(ctx, root, r) {
    var h = ctx.h;
    var t = ctx.t;
    var total = r.total;
    ctx.waiting(r.count !== undefined ? r.count : total);
    var heading = h('h3', {});
    var empty = h('p', { class: 'wt-muted', text: t('Nothing is waiting for your approval.') });
    var list = h('ul', { class: 'wt-inbox' });
    function count() {
      heading.textContent = t('%n waiting for your approval').replace('%n', total);
      heading.hidden = total === 0;
      empty.hidden = total > 0;
    }
    if (r.collaborates !== false) { root.appendChild(h('div', { class: 'wt-section' }, [heading, empty, list])); }
    count();
    r.items.forEach(function (it) {
      var id = 'wt-inbox-' + it.id;
      var comment = h('textarea', { id: id, rows: '3' });
      var note = h('p', { class: 'wt-muted', role: 'status', 'aria-live': 'polite' });
      var buttons = [];
      var item;
      function decide(decision) {
        buttons.forEach(function (b) { b.disabled = true; });
        ctx.call('inbox.decide', { itemId: it.id, decision: decision, comment: comment.value }).then(function (a) {
          ctx.say(a.message || '', 'ok');
          total = Math.max(0, total - 1);
          ctx.waiting(a.count !== undefined ? a.count : a.waiting);
          item.remove();
          count();
        }, function (err) {
          buttons.forEach(function (b) { b.disabled = false; });
          note.textContent = err && err.message ? err.message : t('The approval could not be decided.');
        });
      }
      if (it.approver) {
        buttons = [
          h('button', { type: 'button', class: 'wt-btn is-primary', text: t('Approve'), onclick: function () { decide('approve'); } }),
          h('button', { type: 'button', class: 'wt-btn is-danger', text: t('Deny'), onclick: function () { decide('deny'); } })
        ];
      }
      item = h('li', { class: 'wt-inbox-item', 'data-item': String(it.id) }, [
        h('h4', { text: it.title }),
        h('p', { class: 'wt-muted', text: t('From %name, %time').replace('%name', it.author).replace('%time', when(it.created)) }),
        it.lastMessage ? h('blockquote', { class: 'wt-quote' }, [
          h('p', { text: it.lastMessage.text }),
          h('p', { class: 'wt-muted', text: it.lastMessage.author + ', ' + when(it.lastMessage.created) })]) : null,
        it.preview ? h('div', { class: 'wt-row' }, [
          h('a', { class: 'wt-btn', href: it.preview, target: '_blank', rel: 'noopener', text: t('Open the draft') })]) : null,
        it.approver ? h('label', { class: 'wt-field', for: id }, [h('span', { text: t('Comment for the author') }), comment]) : null,
        buttons.length ? h('div', { class: 'wt-row' }, buttons) : null,
        note
      ]);
      list.appendChild(item);
    });
    drawNotices(ctx, root, r);
  }

  /* The other notifications (F29): comments not read yet (a mention of the user marked), the user's pages decided
     since the user last looked (publish results), the user's scheduled tasks that failed. Mark as read does what
     opening the item in the administration does; the count on the tabs follows. */
  function drawNotices(ctx, root, r) {
    var h = ctx.h;
    var t = ctx.t;
    function section(kind, title, rows, draw) {
      if (!rows || !rows.length) { return; }
      var list = h('ul', { class: 'wt-inbox' });
      var box = h('div', { class: 'wt-section', 'data-notices': kind }, [h('h3', { text: title }), list]);
      rows.forEach(function (it) { list.appendChild(draw(it, function () {
        var li = list.querySelector('[data-item="' + it.id + '"]');
        if (li) { li.remove(); }
        if (!list.children.length) { box.remove(); }
      })); });
      root.appendChild(box);
    }
    function quote(m) {
      return m ? h('blockquote', { class: 'wt-quote' }, [h('p', { text: m.text }), h('p', { class: 'wt-muted', text: m.author + ', ' + when(m.created) })]) : null;
    }
    function readButton(it, gone) {
      var b = h('button', { type: 'button', class: 'wt-btn', text: t('Mark as read'), onclick: function () {
        b.disabled = true;
        ctx.call('inbox.read', { itemId: it.id }).then(function (c) { ctx.waiting(c.count); gone(); },
          function (err) { b.disabled = false; failure(ctx, err); });
      } });
      return b;
    }
    function open(it) {
      return it.url ? h('a', { class: 'wt-btn', href: it.url, target: '_blank', rel: 'noopener', text: t('Open in the administration') }) : null;
    }
    section('comments', t('Comments and mentions'), r.comments, function (it, gone) {
      return h('li', { class: 'wt-inbox-item', 'data-item': String(it.id) }, [
        h('h4', { text: it.title }),
        h('p', {}, [
          it.mention ? h('span', { class: 'wt-chip is-warn', text: t('Mentions you') }) : null,
          it.mention ? document.createTextNode(' ') : null,
          h('span', { class: 'wt-chip', text: t('%n new').replace('%n', it.unread) })]),
        quote(it.lastMessage),
        h('div', { class: 'wt-row' }, [open(it), readButton(it, gone)])
      ]);
    });
    section('results', t('Your pages'), r.results, function (it, gone) {
      var ok = it.state === 'approved';
      return h('li', { class: 'wt-inbox-item', 'data-item': String(it.id) }, [
        h('h4', { text: it.title }),
        h('p', {}, [h('span', { class: 'wt-chip ' + (ok ? 'is-ok' : 'is-warn'), text: ok ? t('Approved') : t('Denied') })]),
        h('p', { text: ok ? t('It is published when the publishing workflow continues.') : t('It is a draft of yours again.') }),
        quote(it.lastMessage),
        h('div', { class: 'wt-row' }, [open(it), readButton(it, gone)])
      ]);
    });
    section('failed', t('Scheduled tasks that failed'), r.failed, function (it) {
      return h('li', { class: 'wt-inbox-item', 'data-item': 'p' + it.id }, [
        h('h4', { text: it.title || t('A page') }),
        h('p', { text: t('The workflow %workflow stopped with an error on %time.').replace('%workflow', it.workflow || '-').replace('%time', when(it.modified)) }),
        it.edit ? h('div', { class: 'wt-row' }, [h('a', { class: 'wt-btn', href: it.edit, text: t('Full editor') })]) : null
      ]);
    });
  }

  /* ---------------------------------------------------------------------------------------------- insights */

  /* The page's insights from the kernel's own statistics (F25), read-only: its views, the searches on this site for
     a word of its name, and that the kernel records no referrers. No third-party tracking. */
  function insights(ctx, root) {
    var h = ctx.h;
    var t = ctx.t;
    if (!ctx.node) {
      root.appendChild(h('p', { text: t('This page is not content of the site. Open a page of the site to edit it.') }));
      return;
    }
    root.appendChild(h('p', { class: 'wt-muted', text: t('Loading ...') }));
    ctx.call('insights.load', { nodeId: ctx.node }).then(function (r) {
      root.textContent = '';
      root.appendChild(h('div', { class: 'wt-section wt-page-head' }, [h('h3', { text: r.name })]));
      root.appendChild(h('div', { class: 'wt-section' }, [
        r.views.collected ? h('p', { class: 'wt-insight-figure', text: t('Views of this page: %n').replace('%n', r.views.count) })
          : h('p', { class: 'wt-muted', text: t('The views are not counted on this installation (cronjob updateviewcount).') })]));
      var searches = h('div', { class: 'wt-section' }, [h('h4', { text: t('Searches on this site for words in the page title') })]);
      if (r.searches.length) {
        searches.appendChild(h('ul', { class: 'wt-insight-list' }, r.searches.map(function (s) {
          return h('li', {}, [h('span', { class: 'wt-insight-term', text: s.phrase }),
            h('span', { class: 'wt-muted', text: t('%n searches, %results results').replace('%n', s.searches).replace('%results', s.results) })]);
        })));
      } else {
        searches.appendChild(h('p', { class: 'wt-muted', text: t('No search on this site used a word of the page title.') }));
      }
      root.appendChild(searches);
      root.appendChild(h('p', { class: 'wt-muted', text: t('Where visitors came from is not recorded on this installation.') }));
    }, function (err) { root.textContent = ''; failure(ctx, err); });
  }

  /* ---------------------------------------------------------------------------------------------- forms */

  /* The page's forms (E2): the page itself when it is a form, and the forms it relates to or embeds, each with its
     number of submissions. With the kernel's infocollector/read (the administration's Collected information) also
     the latest submissions (when, who, the first answers): one opens with all its answers; Export CSV is the
     kernel's export of the form. Without that policy: the numbers only. */
  function forms(ctx, root) {
    var h = ctx.h;
    var t = ctx.t;
    if (!ctx.node) {
      root.appendChild(h('p', { text: t('This page is not content of the site. Open a page of the site to edit it.') }));
      return;
    }
    root.appendChild(h('p', { class: 'wt-muted', text: t('Loading ...') }));
    ctx.call('forms.load', { nodeId: ctx.node }).then(function (r) {
      root.textContent = '';
      if (!r.forms.length) {
        root.appendChild(h('p', { class: 'wt-muted', text: t('This page has no form.') }));
        return;
      }
      if (!r.canRead) {
        root.appendChild(h('p', { class: 'wt-muted', text: t('The submissions are only shown to those who may read collected information in the administration.') }));
      }
      r.forms.forEach(function (form) {
        var section = h('div', { class: 'wt-section wt-form' }, [
          h('h3', { class: 'wt-form-name', text: form.name }),
          h('p', { class: 'wt-muted', text: (form.isPage ? t('This page') + ' · ' : '') + t('%n submissions').replace('%n', form.count) })
        ]);
        if (r.canRead) {
          var tools = h('div', { class: 'wt-row' });
          if (form.count && form.exportUrl) { tools.appendChild(h('a', { class: 'wt-btn', href: form.exportUrl, download: '', text: t('Export CSV') })); }
          if (form.adminUrl) { tools.appendChild(h('a', { class: 'wt-btn', href: form.adminUrl, text: t('In the administration') })); }
          if (tools.childNodes.length) { section.appendChild(tools); }
          if (form.sensitive) { section.appendChild(h('p', { class: 'wt-muted', text: t('This form collects personal data: keep what you read to yourself.') })); }
          if (form.recent.length) {
            var detail = h('div', { class: 'wt-form-detail', hidden: true, role: 'region', 'aria-live': 'polite', 'aria-label': t('Submission') });
            section.appendChild(h('h4', { text: t('Latest submissions') }));
            section.appendChild(h('ul', { class: 'wt-form-list' }, form.recent.map(function (s) {
              return h('li', {}, [h('button', { type: 'button', class: 'wt-form-item', 'aria-expanded': 'false', onclick: function (e) {
                open(s.id, e.currentTarget, detail);
              } }, [
                h('span', { class: 'wt-form-when', text: when(s.created) + (s.by ? ' · ' + s.by : ' · ' + t('Visitor')) }),
                s.preview ? h('span', { class: 'wt-form-preview', text: s.preview }) : null
              ])]);
            })));
            section.appendChild(detail);
          }
        }
        root.appendChild(section);
      });
    }, function (err) { root.textContent = ''; failure(ctx, err); });

    function open(id, button, detail) {
      if (button.getAttribute('aria-expanded') === 'true') {
        button.setAttribute('aria-expanded', 'false');
        detail.hidden = true;
        return;
      }
      Array.prototype.forEach.call(root.querySelectorAll('.wt-form-item[aria-expanded="true"]'), function (b) { b.setAttribute('aria-expanded', 'false'); });
      detail.textContent = '';
      detail.appendChild(h('p', { class: 'wt-muted', text: t('Loading ...') }));
      detail.hidden = false;
      button.setAttribute('aria-expanded', 'true');
      ctx.call('forms.view', { collectionId: id }).then(function (s) {
        detail.textContent = '';
        detail.appendChild(h('h4', { text: when(s.created) + (s.by ? ' · ' + s.by : ' · ' + t('Visitor')) }));
        detail.appendChild(h('dl', { class: 'wt-form-fields' }, [].concat.apply([], s.fields.map(function (f) {
          return [h('dt', { text: f.name }), h('dd', { text: f.value || '–' })];
        }))));
        if (s.adminUrl) { detail.appendChild(h('div', { class: 'wt-row' }, [h('a', { class: 'wt-btn', href: s.adminUrl, text: t('In the administration') })])); }
      }, function (err) {
        detail.textContent = '';
        detail.appendChild(h('p', { class: 'wt-muted', text: err && err.message ? err.message : t('Something went wrong on the server.') }));
      });
    }
  }

  /* ---------------------------------------------------------------------------------------------- review hold */

  /* The review hold (E14): the kernel's object state review_hold/held stops editing and publishing of the page until
     a reviewer releases it. Pure (tests/js/hold.test.js): what the box says and offers, from page.load's hold:
       kind     'missing' (no state group: told to those who administrate states), 'held' or 'free'
       lines    the texts under the title, in order (who and since, the reason, what it means for this user)
       action   'place', 'release' or '' (the kernel's state/assign decides)
       reason   whether the form asks for a reason, and whether it is required */
  var Hold = {
    view: function (p, t, when) {
      if (!p.hold) {
        return { kind: 'missing', lines: [t('Review holds need the object state group review_hold: run the upgrade step 6.0.15-review-hold (Exponential 6.0.15 or later).')], action: '', reason: null };
      }
      var hd = p.hold;
      if (!hd.held) {
        return { kind: 'free', lines: [], action: hd.canPlace ? 'place' : '', reason: hd.canPlace ? { required: true } : null };
      }
      var lines = [];
      if (hd.by && hd.since) {
        lines.push(t('Held by %name since %time.').replace('%name', hd.by).replace('%time', when(hd.since)));
      }
      if (hd.reason) { lines.push(t('Reason: %reason').replace('%reason', hd.reason)); }
      lines.push(hd.editable ? t('You may still edit it, but publishing waits until it is released, unless your role allows it.')
        : t('Nobody edits or publishes this page until a reviewer releases it.'));
      return { kind: 'held', lines: lines, action: hd.canRelease ? 'release' : '', reason: hd.canRelease ? { required: false } : null };
    }
  };

  function hold(ctx, p) {
    var h = ctx.h;
    var t = ctx.t;
    var box = h('section', { class: 'wt-section wt-hold', 'aria-labelledby': 'wt-hold-h' });
    function draw() {
      box.textContent = '';
      var v = Hold.view(p, t, when);
      if (v.kind === 'free' && !v.action) { box.hidden = true; return; }
      box.hidden = false;
      var notice = h('div', { class: 'wt-notice' + (v.kind === 'held' ? ' is-warn' : ''), role: 'status' }, [
        h('h3', { id: 'wt-hold-h', text: v.kind === 'held' ? t('Held for review') : t('Review hold') })
      ]);
      v.lines.forEach(function (line) { notice.appendChild(h('p', { text: line })); });
      /* moderators: Setup > Recent content, where every held item waits (Exponential 6.0.15, moderation/read) */
      var urls = ctx.boot && ctx.boot.urls;
      if (urls && urls.moderation && v.kind !== 'missing') {
        notice.appendChild(h('p', {}, [h('a', { href: urls.moderation + (v.kind === 'held' ? '/(held)/yes' : ''), text: urls.moderationLabel || 'Recent content' })]));
      }
      box.appendChild(notice);
      if (!v.action) { return; }
      var id = 'wt-hold-reason';
      var reason = h('textarea', { id: id, rows: '2', maxlength: '500', required: v.reason.required ? '' : null });
      var label = v.action === 'place' ? t('Why is it held? (kept in the audit)') : t('Note for the audit (optional)');
      var button = h('button', { type: 'submit', class: 'wt-btn' + (v.action === 'place' ? '' : ' is-primary'),
        text: v.action === 'place' ? t('Hold for review') : t('Release') });
      var form = h('form', { class: 'wt-hold-form', novalidate: true, onsubmit: function (e) {
        e.preventDefault();
        if (v.reason.required && !reason.value.trim()) {
          ctx.say(t('Say why the page is held.'), 'bad');
          reason.focus();
          return;
        }
        button.disabled = true;
        ctx.call(v.action === 'place' ? 'hold.place' : 'hold.release', { objectId: p.objectId, reason: reason.value }).then(function () {
          ctx.say(v.action === 'place' ? t('The page is held for review.') : t('The hold is released.'), 'ok');
          /* what the user may do changed with the state: the panel again, from the server */
          ctx.show('page');
        }, function (err) { button.disabled = false; failure(ctx, err); });
      } }, [
        h('label', { class: 'wt-field', for: id }, [h('span', { text: label }), reason]),
        h('div', { class: 'wt-row' }, [button])
      ]);
      box.appendChild(form);
    }
    draw();
    return box;
  }

  /* ---------------------------------------------------------------------------------------------- comments */

  /* The page's comments, moderated in place (E4), at the end of the Page panel: newest first, the hidden ones (waiting
     for moderation) only for those who may reveal them. Approve and Hide (content/hide of the comment), Spam (to the
     trash, content/remove) behind one more question, and a reply on the page (content/create of a comment below it).
     The number waiting is the badge on the Page panel's tab. */
  /* Pure (tests/js/comments.test.js): the buttons of a comment, in their order: approve (a hidden comment) or hide (a
     shown one) for those who may hide it, reply where the user may comment on the page, spam for those who may remove it */
  function commentActions(c, canReply) {
    var out = [];
    if (c.can && c.can.moderate) { out.push(c.hidden ? 'approve' : 'hide'); }
    if (canReply) { out.push('reply'); }
    if (c.can && c.can.spam) { out.push('spam'); }
    return out;
  }

  function comments(ctx, root) {
    var h = ctx.h;
    var t = ctx.t;
    var box = h('section', { class: 'wt-section wt-comments', 'aria-labelledby': 'wt-comments-h' }, [
      h('h3', { id: 'wt-comments-h', text: t('Comments') }),
      h('p', { class: 'wt-muted', text: t('Loading ...') })
    ]);
    root.appendChild(box);
    ctx.call('comments.load', { nodeId: ctx.node }).then(draw, function (err) {
      box.lastChild.remove();
      box.appendChild(h('p', { class: 'wt-muted', text: err && err.message ? err.message : t('Something went wrong on the server.') }));
    });

    function draw(r) {
      box.textContent = '';
      var waiting = r.waiting ? ' · ' + t('%n wait for moderation').replace('%n', r.waiting) : '';
      box.appendChild(h('h3', { id: 'wt-comments-h', text: t('Comments') + ' (' + r.total + ')' }));
      if (waiting) { box.appendChild(h('p', { class: 'wt-muted', text: waiting.slice(3) })); }
      if (ctx.host && ctx.host.setComments) { ctx.host.setComments(r.waiting); }
      if (!r.comments.length) {
        box.appendChild(h('p', { class: 'wt-muted', text: t('No comments on this page yet.') }));
      }
      var list = h('ul', { class: 'wt-list wt-comment-list' });
      r.comments.forEach(function (c) { list.appendChild(item(c, r)); });
      if (r.comments.length) { box.appendChild(list); }
      if (r.canReply) { box.appendChild(replyForm(null, r)); }
    }

    function reload() {
      ctx.call('comments.load', { nodeId: ctx.node }).then(draw, function (err) { ctx.say(err && err.message ? err.message : t('Something went wrong on the server.'), 'bad'); });
    }

    function item(c, r) {
      var li = h('li', { class: 'wt-comment' + (c.hidden ? ' is-hidden' : ''), 'data-comment': String(c.nodeId) });
      var head = h('p', { class: 'wt-comment-head' }, [
        h('strong', { text: c.subject }),
        document.createTextNode(' '),
        c.hidden ? h('span', { class: 'wt-chip is-warn', text: t('Waiting for moderation') }) : null
      ]);
      li.appendChild(head);
      li.appendChild(h('p', { class: 'wt-muted', text: [c.author, c.published ? when(c.published) : ''].filter(Boolean).join(' · ') }));
      li.appendChild(h('p', { class: 'wt-comment-text', text: c.message }));
      var acts = h('div', { class: 'wt-row' });
      var can = commentActions(c, r.canReply);
      if (can.indexOf('approve') >= 0) { acts.appendChild(button(t('Approve'), 'is-primary', function (b) { moderate('approve', c, b); })); }
      if (can.indexOf('hide') >= 0) { acts.appendChild(button(t('Hide'), '', function (b) { moderate('hide', c, b); })); }
      if (can.indexOf('reply') >= 0) {
        acts.appendChild(button(t('Reply'), '', function () {
          var open = li.querySelector('.wt-comment-reply');
          if (open) { open.remove(); return; }
          var f = replyForm(c, r);
          li.appendChild(f);
          f.querySelector('textarea').focus();
        }));
      }
      if (can.indexOf('spam') >= 0) {
        var confirmBox = h('div', { class: 'wt-nl-confirm', role: 'group', 'aria-label': t('Spam'), hidden: true }, [
          h('p', { text: t('Move "%name" to the trash as spam? It can be restored from the trash in the administration.').replace('%name', c.subject) })
        ]);
        var spam = button(t('Spam ...'), '', function () {
          confirmBox.hidden = false;
          spam.setAttribute('aria-expanded', 'true');
          confirmBox.querySelector('.is-danger').focus();
        });
        spam.setAttribute('aria-expanded', 'false');
        confirmBox.appendChild(h('div', { class: 'wt-row' }, [
          button(t('Move to trash'), 'is-danger', function (b) { moderate('spam', c, b); }),
          button(t('Cancel'), '', function () { confirmBox.hidden = true; spam.setAttribute('aria-expanded', 'false'); spam.focus(); })
        ]));
        acts.appendChild(spam);
        li.appendChild(acts);
        li.appendChild(confirmBox);
      } else if (acts.childNodes.length) {
        li.appendChild(acts);
      }
      return li;
    }

    function button(text, kind, fn) {
      var b = h('button', { type: 'button', class: 'wt-btn' + (kind ? ' ' + kind : ''), text: text });
      b.addEventListener('click', function () { fn(b); });
      return b;
    }

    function moderate(decision, c, b) {
      b.disabled = true;
      ctx.call('comments.' + decision, { commentId: c.nodeId }).then(function (res) {
        var said = { approve: t('The comment is approved and shown on the page.'), hide: t('The comment is hidden.'),
          spam: t('The comment was moved to the trash as spam.') }[decision];
        ctx.say(res.status === 'DONE' ? said : t('A workflow holds this change. Find it in the administration.'), res.status === 'DONE' ? 'ok' : '');
        reload();
      }, function (err) { b.disabled = false; ctx.say(err && err.message ? err.message : t('Something went wrong on the server.'), 'bad'); });
    }

    function replyForm(c, r) {
      var id = 'wt-comment-reply-' + (c ? c.nodeId : 'page');
      var text = h('textarea', { id: id, rows: '3', maxlength: '10000' });
      var send = h('button', { type: 'submit', class: 'wt-btn is-primary', text: c ? t('Send the reply') : t('Write a comment') });
      var form = h('form', { class: 'wt-comment-reply' }, [
        h('label', { class: 'wt-field', for: id }, [h('span', { text: c ? t('Reply to "%name"').replace('%name', c.subject) : t('Comment on this page') }), text]),
        h('div', { class: 'wt-row' }, [send])
      ]);
      form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        if (!text.value.trim()) { text.focus(); ctx.say(t('Write the reply first.'), 'bad'); return; }
        send.disabled = true;
        ctx.call('comments.reply', c ? { nodeId: r.nodeId, commentId: c.nodeId, message: text.value } : { nodeId: r.nodeId, message: text.value }).then(function (res) {
          ctx.say(res.status === 'DONE' ? t('The reply is published on the page.') : t('The reply waits for its approval.'), res.status === 'DONE' ? 'ok' : '');
          reload();
        }, function (err) { send.disabled = false; ctx.say(err && err.message ? err.message : t('Something went wrong on the server.'), 'bad'); });
      });
      return form;
    }
  }

  /* ---------------------------------------------------------------------------------------------- urls */

  /* Pure (tests/js/urls.test.js): an address as the panel shows it and sends it: one leading slash, no host, no
     query, no doubled slashes; '' for nothing */
  function addressOf(text) {
    var s = String(text || '').trim().replace(/^[a-z][a-z0-9+.-]*:\/\/[^/]*/i, '').replace(/[?#].*$/, '');
    s = s.replace(/\/+/g, '/').replace(/^\/|\/$/g, '');
    return s ? '/' + s : '';
  }

  /* The page's addresses (E5): its address in each language, its old addresses (they redirect), the aliases added to
     it, the URL wildcards that lead to it, and the addresses visitors asked for and did not find that look like the
     page's, each with "Redirect to this page". With content/edit of the page a new alias (redirecting or not, in a
     language); with content/urltranslator a new wildcard. Nothing is removed here. */
  function urls(ctx, root) {
    var h = ctx.h;
    var t = ctx.t;
    if (!ctx.node) {
      root.appendChild(h('p', { text: t('This page is not content of the site. Open a page of the site to edit it.') }));
      return;
    }
    root.appendChild(h('p', { class: 'wt-muted', text: t('Loading ...') }));
    load();

    function load() {
      ctx.call('urls.load', { nodeId: ctx.node }).then(draw, function (err) { root.textContent = ''; failure(ctx, err); });
    }

    function message(err) {
      var d = err && err.body && err.body.details;
      var text = err && err.message ? err.message : t('Something went wrong on the server.');
      return d && d.path ? text + ' ' + d.path : text;
    }

    function draw(r) {
      root.textContent = '';
      var kinds = { main: t('Address'), alias: t('Alias'), history: t('Old address') };
      var box = h('section', { class: 'wt-section', 'aria-labelledby': 'wt-urls-h' }, [h('h3', { id: 'wt-urls-h', text: t('Addresses of this page') })]);
      var list = h('ul', { class: 'wt-list wt-url-list' });
      r.addresses.forEach(function (a) {
        list.appendChild(h('li', { class: 'wt-url' }, [
          h('a', { class: 'wt-url-path', href: a.url, target: '_blank', rel: 'noopener', text: a.path }),
          h('span', { class: 'wt-url-meta' }, [
            h('span', { class: 'wt-chip' + (a.kind === 'main' ? ' is-ok' : ''), text: kinds[a.kind] || a.kind }),
            a.language ? h('span', { class: 'wt-chip', text: a.language }) : null,
            a.redirects ? h('span', { class: 'wt-chip', text: t('redirects') }) : null
          ])
        ]));
      });
      box.appendChild(list);
      root.appendChild(box);

      var wild = h('section', { class: 'wt-section', 'aria-labelledby': 'wt-urls-w' }, [h('h3', { id: 'wt-urls-w', text: t('Wildcards that lead here') })]);
      if (!r.wildcards.length) {
        wild.appendChild(h('p', { class: 'wt-muted', text: t('No wildcard leads to this page.') }));
      } else {
        var wl = h('ul', { class: 'wt-list wt-url-list' });
        r.wildcards.forEach(function (w) {
          wl.appendChild(h('li', { class: 'wt-url' }, [
            h('span', { class: 'wt-url-path', text: w.source }),
            h('span', { class: 'wt-url-meta' }, [h('span', { class: 'wt-chip', text: w.type === 'redirect' ? t('redirects') : t('shows this page') })])
          ]));
        });
        wild.appendChild(wl);
      }
      root.appendChild(wild);

      if (r.canAlias) {
        var broken = h('section', { class: 'wt-section', 'aria-labelledby': 'wt-urls-b' }, [h('h3', { id: 'wt-urls-b', text: t('Addresses visitors did not find') }),
          h('p', { class: 'wt-muted', text: t('Addresses of this site that answered "not found" and end like this page\'s address. A redirect sends their visitors here.') })]);
        if (!r.broken.length) {
          broken.appendChild(h('p', { class: 'wt-muted', text: t('None: no visitor missed this page.') }));
        } else {
          var bl = h('ul', { class: 'wt-list wt-url-list' });
          r.broken.forEach(function (b) {
            var fix = h('button', { type: 'button', class: 'wt-btn', text: t('Redirect to this page') });
            fix.addEventListener('click', function () {
              fix.disabled = true;
              ctx.call('urls.alias', { nodeId: r.nodeId, path: b.path, redirects: true, language: r.languages.length ? r.languages[0].code : '' }).then(function (res) {
                ctx.say(t('%path now redirects to this page.').replace('%path', res.path), 'ok');
                load();
              }, function (err) { fix.disabled = false; ctx.say(message(err), 'bad'); });
            });
            bl.appendChild(h('li', { class: 'wt-url', 'data-broken': b.path }, [
              h('span', { class: 'wt-url-path', text: b.path }),
              h('span', { class: 'wt-url-meta' }, [h('span', { class: 'wt-chip is-warn', text: t('%n times, last %time').replace('%n', b.count).replace('%time', b.last) })]),
              fix
            ]));
          });
          broken.appendChild(bl);
        }
        root.appendChild(broken);
        root.appendChild(aliasForm(r));
      }
      if (r.canWildcard) { root.appendChild(wildcardForm(r)); }
      if (r.adminUrl) {
        root.appendChild(h('div', { class: 'wt-row' }, [h('a', { class: 'wt-btn', href: r.adminUrl, text: t('In the administration') })]));
      }
    }

    function aliasForm(r) {
      var path = h('input', { type: 'text', id: 'wt-url-alias', inputmode: 'url', autocomplete: 'off', placeholder: '/campaign/running' });
      var lang = h('select', { id: 'wt-url-lang' }, r.languages.map(function (l) { return h('option', { value: l.code, text: l.name }); }));
      var redirects = h('input', { type: 'checkbox', id: 'wt-url-redirects', checked: true });
      var add = h('button', { type: 'submit', class: 'wt-btn is-primary', text: t('Add the address') });
      var form = h('form', { class: 'wt-section wt-url-form' }, [
        h('h3', { text: t('Add an address') }),
        h('label', { class: 'wt-field', for: 'wt-url-alias' }, [h('span', { text: t('Address') }), path]),
        h('label', { class: 'wt-field', for: 'wt-url-lang' }, [h('span', { text: t('Language') }), lang]),
        h('label', { class: 'wt-check', for: 'wt-url-redirects' }, [redirects, h('span', { text: t('Redirect to the page\'s address (otherwise the page is shown under this address)') })]),
        h('div', { class: 'wt-row' }, [add])
      ]);
      form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var p = addressOf(path.value);
        if (!p) { path.focus(); ctx.say(t('Give an address on this site, such as /campaign/running.'), 'bad'); return; }
        add.disabled = true;
        ctx.call('urls.alias', { nodeId: r.nodeId, path: p, language: lang.value, redirects: redirects.checked }).then(function (res) {
          ctx.say((res.status === 'cleaned' ? t('Added as %path (the address was cleaned up).') : t('Added: %path')).replace('%path', res.path), 'ok');
          load();
        }, function (err) { add.disabled = false; ctx.say(message(err), 'bad'); });
      });
      return form;
    }

    function wildcardForm(r) {
      var source = h('input', { type: 'text', id: 'wt-url-wild', inputmode: 'url', autocomplete: 'off', placeholder: '/old-campaign/*' });
      var redirects = h('input', { type: 'checkbox', id: 'wt-url-wild-redirects', checked: true });
      var add = h('button', { type: 'submit', class: 'wt-btn', text: t('Add the wildcard') });
      var form = h('form', { class: 'wt-section wt-url-form' }, [
        h('h3', { text: t('Add a wildcard') }),
        h('p', { class: 'wt-muted', text: t('Every address that matches leads to this page; a * matches the rest of the address.') }),
        h('label', { class: 'wt-field', for: 'wt-url-wild' }, [h('span', { text: t('Address pattern') }), source]),
        h('label', { class: 'wt-check', for: 'wt-url-wild-redirects' }, [redirects, h('span', { text: t('Redirect to the page\'s address (otherwise the page is shown under this address)') })]),
        h('div', { class: 'wt-row' }, [add])
      ]);
      form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var p = addressOf(source.value);
        if (!p) { source.focus(); ctx.say(t('Give an address on this site, such as /campaign/running.'), 'bad'); return; }
        add.disabled = true;
        ctx.call('urls.wildcard', { nodeId: r.nodeId, source: p, type: redirects.checked ? 'redirect' : 'direct' }).then(function (res) {
          ctx.say(t('Added: %path').replace('%path', res.source), 'ok');
          load();
        }, function (err) { add.disabled = false; ctx.say(message(err), 'bad'); });
      });
      return form;
    }
  }

  /* ------------------------------------------------------------------------------------- focal point (E7) */

  /* Pure (tests/js/focal.test.js): what the kernel's expImageFocalPoint::plan() computes, for live previews while the
     focal point or the crop box moves: the crop box cut out first, every geometry/crop filter centered on the focal
     point (inside the image), the size followed through the scale filters; a filter whose size is not known stops it.
     source: the part of the original the alias shows (original pixels), or null when it is not known. Points and
     boxes are fractions of the original (0 to 1). */
  var Focal = {
    KEEP: ['colorspace', 'colorspace/gray', 'colorspace/transparent', 'luminance/gray', 'luminance/sepia', 'color/monochrome',
      'filter/noise', 'filter/swirl', 'border/color', 'border/width', 'flatten'],
    MIN: 0.01,
    round: function (v) { return Math.round(v * 1e6) / 1e6; },
    clamp: function (v, lo, hi) { return Math.max(lo, Math.min(hi, v)); },
    num: function (v) { var n = parseFloat(v); return isNaN(n) ? null : n; },
    scale: function (name, data, w, h) {
      var a = Focal.num(data[0]);
      var b = Focal.num(data[1]);
      var r;
      switch (name) {
        case 'geometry/scale': case 'geometry/scaledownonly':
          if (!a || !b) { return null; }
          r = Math.min(a / w, b / h);
          if (name === 'geometry/scaledownonly') { r = Math.min(1, r); }
          break;
        case 'geometry/scalewidth': case 'geometry/scalewidthdownonly':
          if (!a) { return null; }
          r = a / w;
          if (name === 'geometry/scalewidthdownonly') { r = Math.min(1, r); }
          break;
        case 'geometry/scaleheight': case 'geometry/scaleheightdownonly':
          if (!a) { return null; }
          r = a / h;
          if (name === 'geometry/scaleheightdownonly') { r = Math.min(1, r); }
          break;
        case 'geometry/scaleexact':
          return a && b ? [a, b] : null;
        case 'geometry/scalepercent':
          return a ? [Math.max(1, Math.round(w * a / 100)), Math.max(1, Math.round(h * (b || a) / 100))] : null;
        default:
          return null;
      }
      return [Math.max(1, Math.round(w * r)), Math.max(1, Math.round(h * r))];
    },
    cropFilter: function (crop, width, height) {
      var x = Math.round(crop.x * width);
      var y = Math.round(crop.y * height);
      return [Math.max(1, Math.min(Math.round(crop.width * width), width - x)), Math.max(1, Math.min(Math.round(crop.height * height), height - y)), x, y];
    },
    plan: function (filters, width, height, focal, crop) {
      var out = { width: null, height: null, focal: null, source: null };
      if (!(width > 0 && height > 0)) { return out; }
      var w = width, h = height, sx = 0, sy = 0, sw = width, sh = height;
      if (crop) {
        var c = Focal.cropFilter(crop, width, height);
        sx = c[2]; sy = c[3]; sw = c[0]; sh = c[1]; w = c[0]; h = c[1];
      }
      var has = !!focal;
      var fx = has ? Focal.clamp(focal.x * width - sx, 0, w) : w / 2;
      var fy = has ? Focal.clamp(focal.y * height - sy, 0, h) : h / 2;
      for (var i = 0; i < filters.length; i++) {
        var name = filters[i].name || '';
        var data = filters[i].data || [];
        var s = Focal.scale(name, data, w, h);
        if (s) { fx *= s[0] / w; fy *= s[1] / h; w = s[0]; h = s[1]; continue; }
        if (name === 'geometry/crop' && Focal.num(data[0]) !== null && Focal.num(data[1]) !== null) {
          var cw = parseInt(data[0], 10), ch = parseInt(data[1], 10), iw = Math.round(w), ih = Math.round(h), x, y;
          if (has) {
            x = Focal.clamp(Math.round(fx - cw / 2), 0, Math.max(0, iw - cw));
            y = Focal.clamp(Math.round(fy - ch / 2), 0, Math.max(0, ih - ch));
          } else {
            x = Focal.clamp(parseInt(data[2], 10) || 0, 0, iw);
            y = Focal.clamp(parseInt(data[3], 10) || 0, 0, ih);
          }
          var nw = Math.max(0, Math.min(cw, iw - x)), nh = Math.max(0, Math.min(ch, ih - y));
          sx += x * sw / w; sy += y * sh / h; sw = nw * sw / w; sh = nh * sh / h;
          fx -= x; fy -= y; w = nw; h = nh;
          if (w <= 0 || h <= 0) { return out; }
          continue;
        }
        if (name === 'border' && Focal.num(data[0]) !== null && Focal.num(data[1]) !== null) {
          fx += parseInt(data[0], 10); fy += parseInt(data[1], 10);
          w += 2 * parseInt(data[0], 10); h += 2 * parseInt(data[1], 10);
          continue;
        }
        if (Focal.KEEP.indexOf(name) < 0) { return out; }
      }
      out.width = Math.round(w);
      out.height = Math.round(h);
      out.focal = { x: Focal.round(Focal.clamp(fx / w, 0, 1)), y: Focal.round(Focal.clamp(fy / h, 0, 1)) };
      out.source = { x: Math.round(sx * 100) / 100, y: Math.round(sy * 100) / 100, width: Math.round(sw * 100) / 100, height: Math.round(sh * 100) / 100 };
      return out;
    },
    /* the preview of a plan in a frame: the image's width and offsets in percent of the frame, the frame's ratio */
    view: function (plan, width, height) {
      if (!plan.source || !plan.source.width || !plan.source.height) { return null; }
      var s = plan.source;
      return { ratio: Focal.round(plan.width / plan.height), imageWidth: Focal.round(width / s.width * 100),
        left: Focal.round(-s.x / s.width * 100), top: Focal.round(-s.y / s.height * 100) };
    },
    /* a point of the image from a pointer on it (its client box) */
    point: function (box, clientX, clientY) {
      return { x: Focal.round(Focal.clamp((clientX - box.left) / box.width, 0, 1)), y: Focal.round(Focal.clamp((clientY - box.top) / box.height, 0, 1)) };
    },
    /* the focal point moved by dx, dy (fractions), inside the crop box when there is one */
    nudge: function (p, dx, dy, crop) {
      var lo = crop ? { x: crop.x, y: crop.y } : { x: 0, y: 0 };
      var hi = crop ? { x: crop.x + crop.width, y: crop.y + crop.height } : { x: 1, y: 1 };
      return { x: Focal.round(Focal.clamp(p.x + dx, lo.x, hi.x)), y: Focal.round(Focal.clamp(p.y + dy, lo.y, hi.y)) };
    },
    /* the crop box moved by dx, dy, inside the image */
    move: function (c, dx, dy) {
      return { x: Focal.round(Focal.clamp(c.x + dx, 0, 1 - c.width)), y: Focal.round(Focal.clamp(c.y + dy, 0, 1 - c.height)), width: c.width, height: c.height };
    },
    /* the crop box with one corner (nw, ne, sw, se) dragged to p, at least MIN wide and high, inside the image */
    resize: function (c, corner, p) {
      var x1 = c.x, y1 = c.y, x2 = c.x + c.width, y2 = c.y + c.height;
      if (corner.indexOf('w') >= 0) { x1 = Focal.clamp(p.x, 0, x2 - Focal.MIN); } else { x2 = Focal.clamp(p.x, x1 + Focal.MIN, 1); }
      if (corner.indexOf('n') === 0) { y1 = Focal.clamp(p.y, 0, y2 - Focal.MIN); } else { y2 = Focal.clamp(p.y, y1 + Focal.MIN, 1); }
      return { x: Focal.round(x1), y: Focal.round(y1), width: Focal.round(x2 - x1), height: Focal.round(y2 - y1) };
    },
    /* the crop box made larger or smaller by dw, dh from its top left corner, inside the image */
    grow: function (c, dw, dh) {
      return { x: c.x, y: c.y, width: Focal.round(Focal.clamp(c.width + dw, Focal.MIN, 1 - c.x)), height: Focal.round(Focal.clamp(c.height + dh, Focal.MIN, 1 - c.y)) };
    },
    /* the crop box a click on Crop starts with: the middle 80% */
    start: function () { return { x: 0.1, y: 0.1, width: 0.8, height: 0.8 }; },
    /* what Save sends: null for no focal point or no crop box (the whole image is none) */
    body: function (focal, crop) {
      var whole = crop && crop.x <= 0 && crop.y <= 0 && crop.width >= 1 && crop.height >= 1;
      return { focal: focal ? { x: focal.x, y: focal.y } : null, crop: crop && !whole ? { x: crop.x, y: crop.y, width: crop.width, height: crop.height } : null };
    },
    /* the arrow keys: a step of 1% (10% with Shift) */
    step: function (key, shift) {
      var d = shift ? 0.1 : 0.01;
      return { ArrowLeft: [-d, 0], ArrowRight: [d, 0], ArrowUp: [0, -d], ArrowDown: [0, d] }[key] || null;
    }
  };

  /* ---------------------------------------------------------------------------------------------- calendar */

  /* Pure (tests/js/calendar.test.js): the month grid (six weeks from the Monday on or before the 1st), the days of the
     items, a time moved to another day (the same time of day, in the browser's time zone, sent with its offset) and
     what a move sends. */
  var Calendar = {
    key: function (d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); },
    dayOf: function (ts) { return Calendar.key(new Date(ts * 1000)); },
    grid: function (year, month) {
      var first = new Date(year, month, 1);
      var shift = (first.getDay() + 6) % 7;
      var days = [];
      for (var i = 0; i < 42; i++) {
        var d = new Date(year, month, 1 - shift + i);
        days.push({ key: Calendar.key(d), day: d.getDate(), inMonth: d.getMonth() === month, weekday: (d.getDay() + 6) % 7 });
      }
      var start = new Date(year, month, 1 - shift);
      var end = new Date(year, month, 1 - shift + 42);
      return { from: Math.floor(start.getTime() / 1000), to: Math.floor(end.getTime() / 1000) - 1, days: days };
    },
    iso: function (d) {
      var off = -d.getTimezoneOffset();
      var sign = off >= 0 ? '+' : '-';
      off = Math.abs(off);
      return Calendar.key(d) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds()) + sign + pad(Math.floor(off / 60)) + ':' + pad(off % 60);
    },
    movedTo: function (ts, dayKey) {
      var d = new Date(ts * 1000);
      var p = String(dayKey).split('-');
      return Calendar.iso(new Date(+p[0], +p[1] - 1, +p[2], d.getHours(), d.getMinutes(), d.getSeconds()));
    },
    fromLocal: function (text) {
      if (!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/.test(String(text || ''))) { return ''; }
      var d = new Date(text);
      return isNaN(d.getTime()) ? '' : Calendar.iso(d);
    },
    byDay: function (items) {
      var out = {};
      items.forEach(function (it) { var k = Calendar.dayOf(it.at); (out[k] = out[k] || []).push(it); });
      return out;
    },
    action: function (item) {
      return item.kind === 'publish' ? 'calendar.publish' : item.kind === 'takedown' ? 'calendar.takedown' : 'calendar.variant';
    },
    input: function (item, at) {
      if (item.kind === 'publish') { return { objectId: item.objectId, version: item.version, at: at }; }
      if (item.kind === 'takedown') { return { nodeId: item.nodeId, at: at }; }
      return { conditionId: item.conditionId, which: item.which === 'end' ? 'end' : 'start', at: at };
    }
  };

  /* Everything scheduled (E6) for the page, the page and below, or the whole site, a month at a time: the drafts the
     wait-until-date event holds, the pages the hide cronjob takes down and the layout variants. Drag an item (its
     handle in the list, or its mark in the grid) to another day, or "Move" it to a date and time; every move is
     confirmed first. */
  function calendar(ctx, root) {
    var h = ctx.h;
    var t = ctx.t;
    if (!ctx.node) {
      root.appendChild(h('p', { text: t('This page is not content of the site. Open a page of the site to edit it.') }));
      return;
    }
    var today = new Date();
    var view = { year: today.getFullYear(), month: today.getMonth(), scope: 'page' };
    var lang = (document.documentElement && document.documentElement.lang) || undefined;
    var kinds = { publish: t('Publish'), takedown: t('Take down'), variant: t('Layout variant starts'), 'variant-end': t('Layout variant ends') };
    var current = null;
    var confirmBox = null;
    var asked = 0;
    root.appendChild(h('p', { class: 'wt-muted', text: t('Loading ...') }));
    load();

    function fmt(options, d) {
      try { return new Intl.DateTimeFormat(lang, options).format(d); } catch (e) { return d.toLocaleString(); }
    }
    function whenText(item, ts) {
      var d = new Date(ts * 1000);
      return item.precision === 'date' ? fmt({ dateStyle: 'medium' }, d) : fmt({ dateStyle: 'medium', timeStyle: 'short' }, d);
    }

    function load() {
      var g = Calendar.grid(view.year, view.month);
      /* only the answer to the latest question is drawn: a slower earlier one never overwrites it */
      var mine = ++asked;
      ctx.call('calendar.load', { nodeId: ctx.node, scope: view.scope, from: g.from, to: g.to }).then(function (r) {
        if (mine !== asked) { return; }
        current = r;
        draw(r, g);
      }, function (err) { if (mine !== asked) { return; } root.textContent = ''; failure(ctx, err); });
    }

    function shift(n) {
      var d = new Date(view.year, view.month + n, 1);
      view.year = d.getFullYear();
      view.month = d.getMonth();
      load();
    }

    function draw(r, g) {
      root.textContent = '';
      var scopes = [['page', t('This page')], ['subtree', t('This page and below')], ['site', t('The whole site')]];
      root.appendChild(h('div', { class: 'wt-row wt-cal-scope', role: 'group', 'aria-label': t('Calendar') }, scopes.map(function (s) {
        return h('button', { type: 'button', class: 'wt-btn wt-chip-btn', 'aria-pressed': view.scope === s[0] ? 'true' : 'false', text: s[1],
          onclick: function () { view.scope = s[0]; load(); } });
      })));
      var title = fmt({ month: 'long', year: 'numeric' }, new Date(view.year, view.month, 1));
      root.appendChild(h('div', { class: 'wt-cal-nav' }, [
        h('button', { type: 'button', class: 'wt-btn wt-chip-btn', 'aria-label': t('Previous month'), text: '‹', onclick: function () { shift(-1); } }),
        h('h3', { id: 'wt-cal-h', class: 'wt-cal-title', 'aria-live': 'polite', text: title }),
        h('button', { type: 'button', class: 'wt-btn wt-chip-btn', 'aria-label': t('Next month'), text: '›', onclick: function () { shift(1); } }),
        h('button', { type: 'button', class: 'wt-btn wt-chip-btn', text: t('Today'), onclick: function () {
          view.year = today.getFullYear(); view.month = today.getMonth(); load();
        } })
      ]));

      var byDay = Calendar.byDay(r.items);
      var todayKey = Calendar.key(new Date());
      var grid = h('div', { class: 'wt-cal-grid', 'aria-labelledby': 'wt-cal-h', role: 'group' });
      for (var w = 0; w < 7; w++) {
        grid.appendChild(h('span', { class: 'wt-cal-wd', 'aria-hidden': 'true', text: fmt({ weekday: 'narrow' }, g.days[w] ? new Date(g.days[w].key + 'T12:00') : new Date()) }));
      }
      g.days.forEach(function (day) {
        var items = byDay[day.key] || [];
        var label = fmt({ dateStyle: 'full' }, new Date(day.key + 'T12:00')) + (items.length ? ', ' + t('%n scheduled').replace('%n', items.length) : '');
        var marks = h('span', { class: 'wt-cal-marks', 'aria-hidden': 'true' }, items.slice(0, 4).map(function (it) {
          var m = h('span', { class: 'wt-cal-mark is-' + it.kind, 'data-key': it.key });
          drag(m, it);
          return m;
        }));
        var cell = h('button', { type: 'button', class: 'wt-cal-day' + (day.inMonth ? '' : ' is-other') + (day.key === todayKey ? ' is-today' : ''),
          'data-day': day.key, 'aria-label': label, 'aria-current': day.key === todayKey ? 'date' : null,
          onclick: function () {
            var group = root.querySelector('[data-day-list="' + day.key + '"]');
            if (group) { group.scrollIntoView({ block: 'nearest' }); var f = group.querySelector('a,button'); if (f) { f.focus(); } }
          } }, [h('span', { class: 'wt-cal-num', text: String(day.day) }), marks]);
        grid.appendChild(cell);
      });
      root.appendChild(grid);
      root.appendChild(h('p', { class: 'wt-cal-legend' }, ['publish', 'takedown', 'variant', 'variant-end'].map(function (k) {
        return h('span', { class: 'wt-cal-key' }, [h('span', { class: 'wt-cal-mark is-' + k, 'aria-hidden': 'true' }), h('span', { text: kinds[k] })]);
      })));

      confirmBox = h('div', { class: 'wt-cal-confirm', hidden: true, role: 'region', 'aria-live': 'polite', 'aria-label': t('Move') });
      root.appendChild(confirmBox);

      var list = h('section', { class: 'wt-section', 'aria-labelledby': 'wt-cal-l' }, [h('h3', { id: 'wt-cal-l', text: t('Scheduled') })]);
      var inMonth = r.items.filter(function (it) { return Calendar.dayOf(it.at) >= g.days[0].key && Calendar.dayOf(it.at) <= g.days[41].key; });
      if (!inMonth.length) {
        list.appendChild(h('p', { class: 'wt-muted', text: t('Nothing is scheduled in these weeks.') }));
      }
      Object.keys(byDay).sort().forEach(function (key) {
        var group = h('div', { class: 'wt-cal-group', 'data-day-list': key }, [h('h4', { class: 'wt-cal-date', text: fmt({ dateStyle: 'full' }, new Date(key + 'T12:00')) })]);
        var ul = h('ul', { class: 'wt-list wt-cal-list' });
        byDay[key].forEach(function (it) { ul.appendChild(itemRow(it)); });
        group.appendChild(ul);
        list.appendChild(group);
      });
      root.appendChild(list);

      var notes = [];
      if (!r.setup.publish) { notes.push(t('Publishing at a date is not set up here: the content/publish workflow has no "Wait until date" event.')); }
      if (!r.setup.takeDown) { notes.push(t('Taking pages down at a date is not set up here: content.ini [HideSettings] names no class and no top node.')); }
      if (notes.length) {
        root.appendChild(h('details', { class: 'wt-section wt-cal-notes' }, [h('summary', { text: t('Not set up on this site') })].concat(notes.map(function (n) { return h('p', { class: 'wt-muted', text: n }); }))));
      }
    }

    function itemRow(it) {
      var meta = [h('span', { class: 'wt-chip wt-cal-kind is-' + it.kind, text: kinds[it.kind] || it.kind }),
        h('span', { class: 'wt-cal-time', text: whenText(it, it.at) })];
      if (it.kind === 'variant' && it.to) { meta.push(h('span', { class: 'wt-muted', text: t('until %time').replace('%time', whenText(it, it.to)) })); }
      if ((it.kind === 'variant' || it.kind === 'variant-end') && it.enabled === false) { meta.push(h('span', { class: 'wt-chip is-warn', text: t('rule switched off') })); }
      var body = h('div', { class: 'wt-cal-body' }, [
        it.url ? h('a', { class: 'wt-cal-name', href: it.url, target: '_blank', rel: 'noopener', text: it.title }) : h('span', { class: 'wt-cal-name', text: it.title }),
        h('span', { class: 'wt-cal-meta' }, meta),
        it.targets && it.targets.length ? h('span', { class: 'wt-muted wt-cal-targets', text: t('For: %list').replace('%list', it.targets.join(', ')) }) : null
      ]);
      var li = h('li', { class: 'wt-cal-item', 'data-key': it.key }, [body]);
      if (it.canMove) {
        var handle = h('span', { class: 'wt-cal-handle', 'aria-hidden': 'true', title: t('Drag to another day'), text: '⠇' });
        drag(handle, it);
        li.insertBefore(handle, body);
        li.appendChild(h('button', { type: 'button', class: 'wt-btn wt-chip-btn', text: t('Move'), 'aria-label': t('Move') + ': ' + it.title,
          onclick: function () { ask(it, null); } }));
      }
      return li;
    }

    /* the confirmation of a move: the new date and time (prefilled), Move and Cancel */
    function ask(it, dayKey) {
      var at = dayKey ? Calendar.movedTo(it.at, dayKey) : Calendar.iso(new Date(it.at * 1000));
      var input = h('input', { type: 'datetime-local', id: 'wt-cal-at', value: at.slice(0, 16) });
      var ok = h('button', { type: 'submit', class: 'wt-btn is-primary', text: t('Move') });
      var form = h('form', { class: 'wt-cal-form' }, [
        h('p', { text: t('Move "%name" (%kind) to:').replace('%name', it.title).replace('%kind', kinds[it.kind] || it.kind) }),
        it.kind === 'takedown' ? h('p', { class: 'wt-muted', text: t('A new version of the page with this date is published; an approval may apply.') }) : null,
        it.kind === 'variant' && it.to ? h('p', { class: 'wt-muted', text: t('Its end moves with it.') }) : null,
        h('label', { class: 'wt-field', for: 'wt-cal-at' }, [h('span', { text: t('Date and time') }), input]),
        h('div', { class: 'wt-row' }, [ok, h('button', { type: 'button', class: 'wt-btn', text: t('Cancel'), onclick: close })])
      ]);
      form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var value = Calendar.fromLocal(input.value);
        if (!value) { input.focus(); ctx.say(t('This is not a date and time.'), 'bad'); return; }
        ok.disabled = true;
        ctx.call(Calendar.action(it), Calendar.input(it, value)).then(function (res) {
          ctx.say(res.status === 'PENDING' ? t('Moved. The new date waits for its approval.') : t('Moved.'), 'ok');
          load();
        }, function (err) { ok.disabled = false; failure(ctx, err); });
      });
      confirmBox.textContent = '';
      confirmBox.appendChild(form);
      confirmBox.hidden = false;
      confirmBox.scrollIntoView({ block: 'nearest' });
      input.focus();
      function close() { confirmBox.hidden = true; confirmBox.textContent = ''; }
    }

    /* drag by pointer (mouse, pen and touch alike) onto a day of the grid; Escape cancels */
    function drag(el, it) {
      if (!it.canMove) { return; }
      el.setAttribute('data-drag', 'true');
      el.addEventListener('pointerdown', function (ev) {
        if (ev.button !== 0) { return; }
        ev.preventDefault();
        ev.stopPropagation();
        var scope = root.getRootNode && root.getRootNode().elementFromPoint ? root.getRootNode() : document;
        var over = null;
        var row = root.querySelector('.wt-cal-item[data-key="' + it.key + '"]');
        if (row) { row.classList.add('is-dragging'); }
        try { el.setPointerCapture(ev.pointerId); } catch (e) { /* not capturable */ }
        function at(x, y) {
          var target = scope.elementFromPoint(x, y);
          return target && target.closest ? target.closest('.wt-cal-day') : null;
        }
        function mark(cell) {
          if (over === cell) { return; }
          if (over) { over.removeAttribute('data-drop'); }
          over = cell;
          if (over) { over.setAttribute('data-drop', 'true'); }
        }
        function moveTo(e) { mark(at(e.clientX, e.clientY)); }
        function end(e, cancelled) {
          el.removeEventListener('pointermove', moveTo);
          el.removeEventListener('pointerup', up);
          el.removeEventListener('pointercancel', cancel);
          document.removeEventListener('keydown', key, true);
          if (row) { row.classList.remove('is-dragging'); }
          var cell = cancelled ? null : (e ? at(e.clientX, e.clientY) : over);
          mark(null);
          if (cell && cell.getAttribute('data-day') !== Calendar.dayOf(it.at)) { ask(it, cell.getAttribute('data-day')); }
        }
        function up(e) { end(e, false); }
        function cancel() { end(null, true); }
        function key(e) { if (e.key === 'Escape') { e.preventDefault(); end(null, true); } }
        el.addEventListener('pointermove', moveTo);
        el.addEventListener('pointerup', up);
        el.addEventListener('pointercancel', cancel);
        document.addEventListener('keydown', key, true);
      });
    }
  }

  /* ---------------------------------------------------------------------------------------------- newsletter */

  /* The page's newsletter (E3, cjw_newsletter). On an edition: the edition. On any other page: the editions that carry
     the page as an article, and (with newsletter/editorial) a form that makes a new edition below a list with this page
     as its article. Each edition: its list, state and subscribers; with newsletter/send a test send (the list's test
     addresses by default) and the real send to the subscribers, confirmed in the panel and then with "Verify it's you".
     The newsletter's cronjob mails a send. */
  function newsletter(ctx, root) {
    var h = ctx.h;
    var t = ctx.t;
    if (!ctx.node) {
      root.appendChild(h('p', { text: t('This page is not content of the site. Open a page of the site to edit it.') }));
      return;
    }
    root.appendChild(h('p', { class: 'wt-muted', text: t('Loading ...') }));
    ctx.call('newsletter.load', { nodeId: ctx.node }).then(function (r) {
      root.textContent = '';
      if (r.edition) {
        root.appendChild(edition(r.edition, r.canSend));
        return;
      }
      var box = h('div', { class: 'wt-section' }, [h('h3', { text: t('Editions with this page') })]);
      if (!r.editions.length) {
        box.appendChild(h('p', { class: 'wt-muted', text: t('This page is in no edition yet.') }));
      }
      r.editions.forEach(function (e) { box.appendChild(edition(e, r.canSend)); });
      root.appendChild(box);
      if (r.canCreate) { root.appendChild(createForm(r)); }
    }, function (err) { root.textContent = ''; failure(ctx, err); });

    function message(err) {
      var d = err && err.body && err.body.details;
      var text = err && err.message ? err.message : t('Something went wrong on the server.');
      return d && d.invalid && d.invalid.length ? text + ' ' + d.invalid.join(', ') : text;
    }

    function stateText(e) {
      var s = { draft: t('Draft'), process: t('Being sent'), archive: t('Sent, in the archive'), abort: t('Stopped') }[e.status] || e.status;
      return [e.list.name, s, t('%n subscribers').replace('%n', e.subscribers)].filter(Boolean).join(' · ');
    }

    function createForm(r) {
      var form = h('form', { class: 'wt-section wt-nl-create' }, [h('h3', { text: t('Make an edition from this page') })]);
      if (!r.lists.length) {
        form.appendChild(h('p', { class: 'wt-muted', text: t('There is no newsletter list where you may make an edition.') }));
        return form;
      }
      var list = h('select', { id: 'wt-nl-list' }, r.lists.map(function (l) {
        return h('option', { value: String(l.objectId), text: l.name + ' · ' + t('%n subscribers').replace('%n', l.subscribers) });
      }));
      var title = h('input', { type: 'text', id: 'wt-nl-title', value: r.name, maxlength: '255' });
      var intro = h('textarea', { id: 'wt-nl-intro', rows: '3' });
      var button = h('button', { type: 'submit', class: 'wt-btn is-primary', text: t('Make the edition') });
      form.appendChild(h('label', { class: 'wt-field', for: 'wt-nl-list' }, [h('span', { text: t('Newsletter list') }), list]));
      form.appendChild(h('label', { class: 'wt-field', for: 'wt-nl-title' }, [h('span', { text: t('Title') }), title]));
      form.appendChild(h('label', { class: 'wt-field', for: 'wt-nl-intro' }, [h('span', { text: t('Introduction (optional)') }), intro]));
      form.appendChild(h('p', { class: 'wt-muted', text: t('This page becomes the edition\'s article: its title, its intro and a link to it.') }));
      form.appendChild(h('div', { class: 'wt-row' }, [button]));
      form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        if (!title.value.trim()) { title.focus(); ctx.say(t('Give a title.'), 'bad'); return; }
        button.disabled = true;
        ctx.call('newsletter.create', { nodeId: ctx.node, listId: list.value, title: title.value.trim(), intro: intro.value })
          .then(function () {
            ctx.say(t('The edition was made, with this page as its article.'), 'ok');
            ctx.show('newsletter');
          }, function (err) { button.disabled = false; ctx.say(message(err), 'bad'); });
      });
      return form;
    }

    function edition(e, canSend) {
      var card = h('div', { class: 'wt-nl', 'data-edition': String(e.objectId) });
      draw();
      return card;

      function draw() {
        card.textContent = '';
        card.appendChild(h('h4', { class: 'wt-nl-name', text: e.name }));
        card.appendChild(h('p', { class: 'wt-muted', text: stateText(e) }));
        if (e.approval.required) {
          var a = { none: t('not asked for yet'), pending: t('waiting'), approved: t('approved'), rejected: t('rejected') }[e.approval.state] || e.approval.state;
          card.appendChild(h('p', { class: 'wt-muted', text: t('Approval: %state').replace('%state', a) }));
        }
        if (e.send) {
          var s = { waiting: t('waiting for the cronjob'), queued: t('queued'), sending: t('being sent'), sent: t('sent'), aborted: t('stopped') }[e.send.status] || e.send.status;
          card.appendChild(h('p', { class: 'wt-muted', text: t('Last send: %state, %time').replace('%state', s).replace('%time', when(e.send.created)) }));
        }
        if (canSend) { sendParts(); }
        if (e.adminUrl) {
          card.appendChild(h('div', { class: 'wt-row' }, [h('a', { class: 'wt-btn', href: e.adminUrl, text: t('In the administration') })]));
        }
      }

      function sendParts() {
        var id = 'wt-nl-test-' + e.objectId;
        var to = h('input', { type: 'text', id: id, value: e.testRecipients, inputmode: 'email', autocomplete: 'off' });
        var test = h('button', { type: 'submit', class: 'wt-btn', text: t('Send a test') });
        card.appendChild(h('form', { class: 'wt-nl-test', onsubmit: function (ev) {
          ev.preventDefault();
          test.disabled = true;
          ctx.call('newsletter.test', { editionId: e.objectId, recipients: to.value }).then(function (r) {
            test.disabled = false;
            if (r.failed.length) {
              ctx.say(t('The test could not be sent.'), 'bad');
            } else {
              ctx.say(t('The test was sent to %list.').replace('%list', r.sent.join(', ')), 'ok');
            }
          }, function (err) { test.disabled = false; ctx.say(message(err), 'bad'); });
        } }, [
          h('label', { class: 'wt-field', for: id }, [h('span', { text: t('Test addresses') }), to]),
          h('div', { class: 'wt-row' }, [test])
        ]));
        if (e.reason) {
          card.appendChild(h('p', { class: 'wt-muted', text: t(e.reason) }));
          return;
        }
        var confirmBox = h('div', { class: 'wt-nl-confirm', role: 'group', 'aria-label': t('Send to the subscribers'), hidden: true });
        var open = h('button', { type: 'button', class: 'wt-btn is-primary', 'aria-expanded': 'false', text: t('Send to the subscribers ...'), onclick: function () {
          confirmBox.hidden = false;
          open.setAttribute('aria-expanded', 'true');
          var go = confirmBox.querySelector('.is-danger');
          if (go) { go.focus(); }
        } });
        var sendNow = h('button', { type: 'button', class: 'wt-btn is-danger', text: t('Send now'), onclick: function () {
          sendNow.disabled = true;
          ctx.call('newsletter.send', { editionId: e.objectId, version: e.version }).then(function (next) {
            e = next;
            ctx.say(t('The edition is queued: the newsletter\'s cronjob sends it to the subscribers.'), 'ok');
            draw();
          }, function (err) { sendNow.disabled = false; ctx.say(message(err), 'bad'); });
        } });
        confirmBox.appendChild(h('p', { text: t('Send "%name" to the %n subscribers of "%list" now? This cannot be taken back here.')
          .replace('%name', e.name).replace('%n', e.subscribers).replace('%list', e.list.name) }));
        confirmBox.appendChild(h('div', { class: 'wt-row' }, [sendNow, h('button', { type: 'button', class: 'wt-btn', text: t('Cancel'), onclick: function () {
          confirmBox.hidden = true;
          open.setAttribute('aria-expanded', 'false');
          open.focus();
        } })]));
        card.appendChild(h('div', { class: 'wt-row' }, [open]));
        card.appendChild(confirmBox);
      }
    }
  }

  /* ---------------------------------------------------------------------------------------------- SEO check */

  /* The rules of the SEO check (F26), pure: what the rendered page shows (facts) to findings. A finding is
     { id, level: bad|warn|ok, text: the English source, vars: its placeholders, ref: where it jumps }, ref one of
     { field: 'title'|'meta' } (the page's title or metadata field), { heading: i }, { image: i }, { link: i }. */
  var SEO = {
    TITLE: [30, 60],
    DESCRIPTION: [70, 160],
    rules: function (f, translate) {
      var t = translate || function (s) { return s; };
      var out = [];
      var add = function (id, level, text, vars, ref) {
        Object.keys(vars || {}).forEach(function (k) { text = text.split(k).join(String(vars[k])); });
        out.push({ id: id, level: level, text: text, vars: vars || {}, ref: ref || null });
      };
      var title = String(f.title || '').trim();
      if (!title) {
        add('title', 'bad', t('The page has no title.'), {}, { field: 'title' });
      } else if (title.length < SEO.TITLE[0] || title.length > SEO.TITLE[1]) {
        add('title', 'warn', t('The title has %n characters; 30 to 60 fit search results.'), { '%n': title.length }, { field: 'title' });
      } else {
        add('title', 'ok', t('The title has %n characters.'), { '%n': title.length }, { field: 'title' });
      }
      var desc = String(f.description || '').trim();
      if (!desc) {
        add('description', 'bad', t('The page has no description.'), {}, { field: 'meta' });
      } else if (desc.length < SEO.DESCRIPTION[0] || desc.length > SEO.DESCRIPTION[1]) {
        add('description', 'warn', t('The description has %n characters; 70 to 160 fit search results.'), { '%n': desc.length }, { field: 'meta' });
      } else {
        add('description', 'ok', t('The description has %n characters.'), { '%n': desc.length }, { field: 'meta' });
      }
      var levels = f.headings || [];
      var h1 = levels.filter(function (l) { return l === 1; }).length;
      if (!h1) {
        add('h1', 'bad', t('The page has no main heading (h1).'), {}, null);
      } else if (h1 > 1) {
        add('h1', 'warn', t('The page has %n main headings (h1); one is best.'), { '%n': h1 }, { heading: levels.indexOf(1, levels.indexOf(1) + 1) });
      }
      var skips = 0;
      levels.forEach(function (l, i) {
        if (i > 0 && l > levels[i - 1] + 1) {
          skips++;
          add('order-' + i, 'warn', t('A heading jumps from level %from to level %to.'), { '%from': levels[i - 1], '%to': l }, { heading: i });
        }
      });
      if (h1 === 1 && !skips) { add('headings', 'ok', t('The headings are in order.'), {}, null); }
      var alt = 0;
      (f.images || []).forEach(function (img, i) {
        if (img.alt === null) { alt++; add('alt-' + i, 'bad', t('An image has no alternative text.'), {}, { image: i }); }
      });
      if (!alt && (f.images || []).length) { add('alt', 'ok', t('Every image has an alternative text.'), {}, null); }
      var canon = f.canonical || [];
      if (!canon.length) {
        add('canonical', 'warn', t('The page names no canonical address.'), {}, { field: 'meta' });
      } else if (canon.length > 1) {
        add('canonical', 'bad', t('The page names %n canonical addresses; one is right.'), { '%n': canon.length }, { field: 'meta' });
      } else if (String(canon[0]).replace(/[?#].*$/, '').replace(/\/$/, '') !== String(f.here || '').replace(/[?#].*$/, '').replace(/\/$/, '')) {
        add('canonical', 'warn', t('The canonical address is another page: %url'), { '%url': canon[0] }, { field: 'meta' });
      } else {
        add('canonical', 'ok', t('The canonical address is this page.'), {}, null);
      }
      var robots = String(f.robots || '').toLowerCase();
      if (/noindex|none/.test(robots)) {
        add('robots', 'bad', t('Search engines are asked not to list this page (robots: %value).'), { '%value': robots }, { field: 'meta' });
      }
      var broken = 0;
      (f.links || []).forEach(function (l, i) {
        if (l.status === 'broken') { broken++; add('link-' + i, 'bad', t('A link leads nowhere: %url'), { '%url': l.path }, { link: i }); }
      });
      var checked = (f.links || []).filter(function (l) { return l.status === 'ok'; }).length;
      if (!broken && checked) { add('links', 'ok', t('All %n links checked on this page lead somewhere.'), { '%n': checked }, null); }
      var rank = { bad: 0, warn: 1, ok: 2 };
      return out.map(function (x, i) { return [x, i]; })
        .sort(function (a, b) { return rank[a[0].level] - rank[b[0].level] || a[1] - b[1]; })
        .map(function (x) { return x[0]; });
    }
  };

  /* What the rendered page shows, outside the sidebar: the facts for SEO.rules() and the elements behind them */
  function seoFacts() {
    var outside = function (el) { return !el.closest('exp-wt'); };
    var all = function (sel) { return Array.prototype.filter.call(document.querySelectorAll(sel), outside); };
    var meta = function (name) {
      var el = document.querySelector('meta[name="' + name + '" i]');
      return el ? el.getAttribute('content') || '' : '';
    };
    var headings = all('h1,h2,h3,h4,h5,h6').filter(function (el) { return el.getClientRects().length && !el.closest('[aria-hidden="true"]'); });
    var images = all('img').filter(function (el) { return el.getAttribute('role') !== 'presentation' && !el.closest('[aria-hidden="true"]'); });
    var seen = {};
    var links = all('a[href]').filter(function (a) {
      if (a.origin !== window.location.origin || !/^https?:$/.test(a.protocol)) { return false; }
      if (a.pathname === window.location.pathname && a.hash) { return false; }
      if (seen[a.pathname]) { return false; }
      seen[a.pathname] = true;
      return true;
    }).slice(0, 200);
    return {
      els: { headings: headings, images: images, links: links },
      facts: {
        title: document.title,
        description: meta('description'),
        robots: meta('robots'),
        canonical: all('link[rel="canonical"]').map(function (l) { return l.href; }),
        here: window.location.origin + window.location.pathname,
        headings: headings.map(function (el) { return parseInt(el.tagName.charAt(1), 10); }),
        images: images.map(function (el) { return { alt: el.hasAttribute('alt') ? el.getAttribute('alt') : null }; }),
        paths: links.map(function (a) { return a.pathname; })
      }
    };
  }

  /* The field of the page an element of the page shows (the kernel's markers, F20), or null */
  function fieldAt(el) {
    if (!el || !document.createTreeWalker) { return null; }
    var walker = document.createTreeWalker(document.body, NodeFilter.SHOW_COMMENT);
    var stack = [];
    var node;
    var found = null;
    while ((node = walker.nextNode())) {
      var text = node.data || '';
      if (text.indexOf('exp-field ') === 0) {
        var m = /data-exp-field="([^"]*)"/.exec(text);
        var o = /data-exp-object="([^"]*)"/.exec(text);
        stack.push({ open: node, field: m ? m[1] : '', object: o ? o[1] : '' });
      } else if (text === '/exp-field' && stack.length) {
        var range = stack.pop();
        var r = document.createRange();
        r.setStartAfter(range.open);
        r.setEndBefore(node);
        if (r.intersectsNode(el)) { found = range; }
      }
    }
    return found;
  }

  function seo(ctx, root) {
    var h = ctx.h;
    var t = ctx.t;
    if (!ctx.node) {
      root.appendChild(h('p', { text: t('This page is not content of the site. Open a page of the site to edit it.') }));
      return;
    }
    /* the SEO findings, then the accessibility check (E11) below them, which keeps its result when SEO runs again */
    var box = h('div', { class: 'wt-seo' });
    root.appendChild(box);
    var access = h('div', { class: 'wt-a11y' });
    root.appendChild(access);
    a11y(ctx, access);
    var run = function () {
      box.textContent = '';
      box.appendChild(h('p', { class: 'wt-muted', text: t('Checking ...') }));
      var seen = seoFacts();
      /* the links in parts: each part one read (a GET), short enough for every server */
      var parts = [];
      for (var i = 0; i < seen.facts.paths.length; i += 30) { parts.push(seen.facts.paths.slice(i, i + 30)); }
      if (!parts.length) { parts.push([]); }
      Promise.all(parts.map(function (paths) { return ctx.call('seo.check', { nodeId: ctx.node, paths: paths }); })).then(function (answers) {
        var fields = answers[0].fields;
        var links = [];
        answers.forEach(function (a) { links = links.concat(a.links); });
        seen.facts.links = links;
        box.textContent = '';
        drawSeo(ctx, box, SEO.rules(seen.facts, t), seen.els, fields, run);
      }, function (err) { box.textContent = ''; failure(ctx, err); });
    };
    run();
  }

  function drawSeo(ctx, root, findings, els, fields, again) {
    var h = ctx.h;
    var t = ctx.t;
    var bad = findings.filter(function (f) { return f.level !== 'ok'; }).length;
    var again_ = h('button', { type: 'button', class: 'wt-btn', text: t('Check again'), onclick: again });
    root.appendChild(h('div', { class: 'wt-section wt-page-head' }, [
      h('h3', { text: bad ? t('%n things to look at').replace('%n', bad) : t('Nothing to look at.') }), again_]));
    var label = { bad: t('Problem'), warn: t('To look at'), ok: t('Fine') };
    var list = h('ul', { class: 'wt-seo-list' });
    findings.forEach(function (f) {
      var text = f.text;
      var target = seoTarget(f.ref, els, fields);
      var body = [h('span', { class: 'wt-chip is-' + f.level, text: label[f.level] }), h('span', { class: 'wt-seo-text', text: text })];
      var item = target ? h('button', { type: 'button', class: 'wt-seo-item', onclick: function () { seoJump(ctx, target); } }, body)
        : h('div', { class: 'wt-seo-item' }, body);
      if (target) {
        item.setAttribute('title', target.field ? t('Open the field') : t('Show it on the page'));
        item.setAttribute('data-target', target.field ? 'field' : 'page');
      }
      list.appendChild(h('li', { 'data-level': f.level }, [item]));
    });
    root.appendChild(list);
  }

  /* Where a finding leads: { field } (the Page panel opens it) or { element } (shown on the page); null: nowhere */
  function seoTarget(ref, els, fields) {
    if (!ref) { return null; }
    if (ref.field) {
      /* the page's <title> comes from its metadata where the class has a metadata field, else from its name */
      var id = ref.field === 'title' ? fields.meta || fields.title : fields.meta;
      return id ? { field: id } : null;
    }
    var el = ref.heading !== undefined ? els.headings[ref.heading] : ref.image !== undefined ? els.images[ref.image] : ref.link !== undefined ? els.links[ref.link] : null;
    if (!el) { return null; }
    var marked = fieldAt(el);
    return marked && String(marked.object) && marked.field ? { field: marked.field, element: el } : { element: el };
  }

  function seoJump(ctx, target) {
    if (target.element) { ctx.host.flash(target.element); }
    if (target.field) { ctx.host.openField(target.field); }
  }

  /* ---------------------------------------------------------------------------------------------- accessibility */

  /* The accessibility check (E11), on demand in the SEO panel: axe-core (shipped unchanged as expwt-axe.min.js,
     loaded only when the check runs, for users with expwt/edit) checks the rendered page against WCAG 2.2 AA, the
     sidebar left out. Each finding names where it is: the field of the page (the kernel's markers, F20), the layout
     block (its ngl-vt-<view type> class) and the template (the kernel's template debug comments, where they are on).
     A11y.findings() is pure, for the tests (tests/js/a11y.test.js): axe's violations to one finding per element,
     the worst first; place(selector) says where an element is. */
  var A11y = {
    TAGS: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'],
    IMPACT: ['critical', 'serious', 'moderate', 'minor'],
    LIMIT: 200,
    findings: function (violations, place) {
      var out = [];
      (Array.isArray(violations) ? violations : []).forEach(function (v) {
        (Array.isArray(v.nodes) ? v.nodes : []).forEach(function (n, i) {
          var target = n && Array.isArray(n.target) && n.target.length === 1 && typeof n.target[0] === 'string' ? n.target[0] : null;
          out.push({
            id: String(v.id) + '-' + i, rule: String(v.id || ''),
            impact: A11y.IMPACT.indexOf(n.impact || v.impact) >= 0 ? (n.impact || v.impact) : 'minor',
            help: String(v.help || ''),
            url: /^https:\/\/dequeuniversity\.com\//.test(String(v.helpUrl || '')) ? String(v.helpUrl) : '',
            target: target,
            where: target && place ? place(target) : null
          });
        });
      });
      return out.map(function (x, i) { return [x, i]; })
        .sort(function (a, b) { return A11y.IMPACT.indexOf(a[0].impact) - A11y.IMPACT.indexOf(b[0].impact) || a[1] - b[1]; })
        .map(function (x) { return x[0]; }).slice(0, A11y.LIMIT);
    },
    /* the layout block of an element: the view type of its innermost ngl-block (ngl-vt-<view type>), or '' */
    block: function (classes) {
      var m = /(?:^|\s)ngl-vt-([A-Za-z0-9_-]+)/.exec(String(classes || ''));
      return m ? m[1] : '';
    },
    /* the template of a template debug comment (START: including template: <path> (...)), or '' */
    template: function (comment) {
      var m = /^\s*START: including template: ([^\s()]+)/.exec(String(comment || ''));
      return m ? m[1] : '';
    }
  };

  /* The template the kernel's template debug comments say an element of the page comes from, or '' */
  function templateAt(el) {
    if (!el || !document.createTreeWalker) { return ''; }
    var walker = document.createTreeWalker(document.body, NodeFilter.SHOW_COMMENT);
    var stack = [];
    var node;
    var found = '';
    while ((node = walker.nextNode())) {
      var path = A11y.template(node.data);
      if (path) {
        stack.push({ open: node, path: path });
      } else if (/^\s*STOP: including template:/.test(node.data || '') && stack.length) {
        var open = stack.pop();
        var r = document.createRange();
        r.setStartAfter(open.open);
        r.setEndBefore(node);
        if (r.intersectsNode(el)) { found = open.path; }
      }
    }
    return found;
  }

  /* Where an element of the page (a selector of axe) is: element, field, block, template */
  function a11yPlace(selector) {
    var el = null;
    try { el = document.querySelector(selector); } catch (e) { return null; }
    if (!el || el.closest('exp-wt')) { return null; }
    var marked = fieldAt(el);
    var block = el.closest('.ngl-block');
    return {
      element: el,
      field: marked && String(marked.object) && marked.field ? marked.field : '',
      block: block ? A11y.block(block.className) : '',
      template: templateAt(el)
    };
  }

  /* axe-core, loaded once into the page (it checks the page, so it runs there, never in the sidebar) */
  var axeLoading = null;
  function loadAxe(url) {
    if (window.axe && typeof window.axe.run === 'function') { return Promise.resolve(window.axe); }
    if (!axeLoading) {
      axeLoading = new Promise(function (resolve, reject) {
        var s = document.createElement('script');
        s.src = url;
        s.async = true;
        s.onload = function () { if (window.axe && typeof window.axe.run === 'function') { resolve(window.axe); } else { reject(new Error('axe')); } };
        s.onerror = function () { axeLoading = null; reject(new Error('axe')); };
        document.head.appendChild(s);
      });
    }
    return axeLoading;
  }

  function a11y(ctx, root) {
    var h = ctx.h;
    var t = ctx.t;
    var url = ctx.boot && ctx.boot.urls && ctx.boot.urls.axe;
    if (!url) { return; }
    var result = h('div', { class: 'wt-a11y-result', 'aria-live': 'polite' });
    var button = h('button', { type: 'button', class: 'wt-btn', text: t('Check the accessibility of the page (WCAG 2.2 AA)'), onclick: function () { run(); } });
    root.appendChild(h('div', { class: 'wt-section wt-page-head' }, [h('h3', { text: t('Accessibility') }), button]));
    root.appendChild(result);
    root.appendChild(h('p', { class: 'wt-muted', text: t('An automatic check finds only some problems: also try the page with the keyboard alone and with a screen reader.') + ' '
      + t('The rule texts come from axe-core, in English.') }));
    function run() {
      button.disabled = true;
      result.textContent = '';
      result.appendChild(h('p', { class: 'wt-muted', text: t('Checking the accessibility ...') }));
      loadAxe(url).then(function (axe) {
        return axe.run({ include: [['html']], exclude: [['exp-wt']] }, { runOnly: { type: 'tag', values: A11y.TAGS }, resultTypes: ['violations'] });
      }).then(function (r) {
        button.disabled = false;
        drawA11y(ctx, result, A11y.findings(r.violations, a11yPlace));
      }, function () {
        button.disabled = false;
        result.textContent = '';
        result.appendChild(h('div', { class: 'wt-notice is-warn', role: 'alert', text: t('The accessibility check could not run.') }));
      });
    }
  }

  function drawA11y(ctx, root, findings) {
    var h = ctx.h;
    var t = ctx.t;
    root.textContent = '';
    if (!findings.length) {
      root.appendChild(h('p', { text: t('The automatic check found no accessibility problems.') }));
      return;
    }
    root.appendChild(h('p', { class: 'wt-a11y-count', text: t('%n accessibility problems').replace('%n', findings.length) }));
    var label = { critical: t('Critical'), serious: t('Serious'), moderate: t('Moderate'), minor: t('Minor') };
    var list = h('ul', { class: 'wt-seo-list' });
    findings.forEach(function (f) {
      var w = f.where;
      var where = [];
      if (w && w.field) { where.push(t('Field: %name').replace('%name', w.field)); }
      if (w && w.block) { where.push(t('Block: %name').replace('%name', w.block)); }
      if (w && w.template) { where.push(t('Template: %name').replace('%name', w.template)); }
      var body = [h('span', { class: 'wt-chip is-' + (f.impact === 'critical' || f.impact === 'serious' ? 'bad' : 'warn'), text: label[f.impact] }),
        h('span', { class: 'wt-seo-text' }, [f.help, where.length ? h('span', { class: 'wt-muted wt-a11y-where', text: where.join(' · ') }) : null])];
      var target = w ? { element: w.element, field: w.field } : null;
      var item = target ? h('button', { type: 'button', class: 'wt-seo-item', 'data-rule': f.rule, onclick: function () { seoJump(ctx, target); } }, body)
        : h('div', { class: 'wt-seo-item', 'data-rule': f.rule }, body);
      if (target) {
        item.setAttribute('title', target.field ? t('Open the field') : t('Show it on the page'));
        item.setAttribute('data-target', target.field ? 'field' : 'page');
      }
      list.appendChild(h('li', { 'data-level': f.impact }, [item,
        f.url ? h('a', { class: 'wt-a11y-more', href: f.url, target: '_blank', rel: 'noopener noreferrer', text: t('More about this rule') }) : null]));
    });
    root.appendChild(list);
  }

  /* ---------------------------------------------------------------------------------------------- sites */

  /* The page on the other sites and in its other languages (F27): open it there (the sidebar's state is saved first,
     so it opens there as it is here), or see it side by side with this page; a translation in the Page panel, or
     a new one in the full editor. */
  function sites(ctx, root) {
    var h = ctx.h;
    var t = ctx.t;
    if (!ctx.node) {
      root.appendChild(h('p', { text: t('This page is not content of the site. Open a page of the site to edit it.') }));
      return;
    }
    root.appendChild(h('p', { class: 'wt-muted', text: t('Loading ...') }));
    ctx.call('page.elsewhere', { nodeId: ctx.node }).then(function (r) {
      root.textContent = '';
      var open = function (url) {
        return function (e) {
          e.preventDefault();
          var go = function () { window.location.href = url; };
          ctx.call('state.save', { state: ctx.state }).then(go, go);
        };
      };
      var list = h('ul', { class: 'wt-place-list' });
      r.sites.forEach(function (s) {
        var info = [h('span', { class: 'wt-place-name', text: s.name }), h('span', { class: 'wt-muted', text: s.language + ' · ' + s.identifier })];
        var acts = h('div', { class: 'wt-row' });
        if (s.current) {
          info.push(h('span', { class: 'wt-chip is-ok', text: t('This site') }));
        } else {
          if (!s.shown) { info.push(h('span', { class: 'wt-muted', text: t('Not in the language of this site') })); }
          acts.appendChild(h('a', { class: 'wt-btn', href: s.url, text: t('Open'), onclick: open(s.url) }));
          acts.appendChild(h('button', { type: 'button', class: 'wt-btn', text: t('Side by side'), onclick: function () { ctx.host.split(s.url, s.name + ' · ' + s.language); } }));
        }
        list.appendChild(h('li', {}, [h('div', { class: 'wt-place-info' }, info), acts]));
      });
      root.appendChild(h('div', { class: 'wt-section' }, [h('h3', { text: t('This page on other sites') }),
        r.sites.length > 1 ? list : h('p', { class: 'wt-muted', text: t('There is no other site to show it on.') })]));

      var langs = h('ul', { class: 'wt-place-list' });
      r.translations.forEach(function (l) {
        var info = [h('span', { class: 'wt-place-name', text: l.name }), h('span', { class: 'wt-muted', text: l.locale })];
        if (l.main) { info.push(h('span', { class: 'wt-chip', text: t('Main language') })); }
        if (!l.exists) { info.push(h('span', { class: 'wt-muted', text: t('Not translated yet') })); }
        var acts = h('div', { class: 'wt-row' });
        if (l.exists) {
          if (l.editable) {
            acts.appendChild(h('button', { type: 'button', class: 'wt-btn', text: t('Edit here'), onclick: function () {
              ctx.host.pageLanguage = l.locale;
              ctx.show('page');
            } }));
          }
          acts.appendChild(h('button', { type: 'button', class: 'wt-btn', text: t('Side by side'), onclick: function () { ctx.host.split(l.preview, l.name); } }));
        } else if (l.translate) {
          acts.appendChild(h('a', { class: 'wt-btn', href: l.translate, text: t('Translate') }));
        }
        langs.appendChild(h('li', {}, [h('div', { class: 'wt-place-info' }, info), acts]));
      });
      root.appendChild(h('div', { class: 'wt-section' }, [h('h3', { text: t('Translations') }), langs]));
    }, function (err) { root.textContent = ''; failure(ctx, err); });
  }

  /* ---------------------------------------------------------------------------------------------- migrations */

  /* The Migrations panel's pure parts (R4.5), for the tests (tests/js/migrations.test.js): the chip of a state, how
     many need attention, a time of the report (ISO 8601) as the user's language writes it. */
  var Migrations = {
    chip: function (state) {
      if (state === 'failed') { return 'is-bad'; }
      if (state === 'done' || state === 'pushed') { return 'is-ok'; }
      if (state === 'running' || state === 'pending') { return 'is-warn'; }
      return '';
    },
    attention: function (r) {
      return (r.pending || []).length + (r.more || 0) + (r.failed || []).length + (r.orphans || []).length;
    },
    at: function (iso, lang) {
      if (!iso) { return ''; }
      var d = new Date(iso);
      if (isNaN(d.getTime())) { return String(iso); }
      try { return new Intl.DateTimeFormat(lang, { dateStyle: 'medium', timeStyle: 'short' }).format(d); } catch (e) { return d.toLocaleString(); }
    },
    /* The recording of the user's own changes (WP13): 'none' (not offered: no kernel support, no migration/edit,
       switched off), 'on' (the user's changes are recorded), 'off' (they can be). */
    recording: function (rec) {
      if (!rec || typeof rec !== 'object') { return 'none'; }
      return rec.active ? 'on' : 'off';
    },
    /* The chip of a deploy's or a remote's state, from the kind the kernel's words come with (WP18). */
    deployChip: function (kind) {
      return { ok: 'is-ok', warn: 'is-warn', bad: 'is-bad' }[kind] || '';
    }
  };

  /* The installation's migrations, read-only (R4.5), for a user with the kernel's migration/read: who runs now (the
     installation lock), the failed ones, the pending ones (id, source, description), those recorded whose file is
     gone, and the last run (state, when, who, caption, tag, push). Every item links to Setup > Migrations in the
     administration, where running and rolling back stay. The number that needs attention is the badge on the tab. */
  function migrations(ctx, root) {
    var h = ctx.h;
    var t = ctx.t;
    var lang = (document.documentElement && document.documentElement.lang) || undefined;
    var runText = { done: t('Done'), failed: t('Failed'), running: t('Running'), 'rolled-back': t('Rolled back') };
    var pushText = { pushed: t('Pushed'), pending: t('Waiting for its push'), disabled: t('Off'), 'no-remote': t('No remote'), none: t('Nothing to push') };
    function open(url) {
      return url ? h('a', { class: 'wt-btn', href: url, text: t('Open in the administration') }) : null;
    }
    function chip(text, state) {
      return h('span', { class: 'wt-chip' + (Migrations.chip(state) ? ' ' + Migrations.chip(state) : ''), text: text });
    }
    function item(m, extra) {
      return h('li', { class: 'wt-mig' }, [
        h('div', { class: 'wt-mig-info' }, [
          h('span', { class: 'wt-mig-id', text: m.id }),
          h('span', { class: 'wt-url-meta' }, [h('span', { class: 'wt-chip', text: m.source })].concat(extra || [])),
          m.description ? h('span', { class: 'wt-muted wt-mig-desc', text: m.description }) : null
        ]),
        open(m.url)
      ]);
    }
    function section(id, title, children) {
      return h('section', { class: 'wt-section', 'aria-labelledby': id }, [h('h3', { id: id, text: title })].concat(children));
    }
    function row(label, value) {
      return value ? [h('dt', { text: label }), h('dd', {}, [value])] : [];
    }
    /* The recording of the user's own changes (WP13): the state with Start or Stop; the site-wide window is only
       shown. Each answer redraws the block and the mark on the tab. */
    function recording(rec) {
      var state = Migrations.recording(rec);
      if (ctx.recording) { ctx.recording(rec); }
      if (state === 'none') { return null; }
      var box = h('section', { class: 'wt-section wt-rec' + (state === 'on' ? ' is-on' : ''), 'aria-labelledby': 'wt-mig-rec' });
      function draw(r) {
        box.textContent = '';
        var on = Migrations.recording(r) === 'on';
        box.className = 'wt-section wt-rec' + (on ? ' is-on' : '');
        var button = h('button', { type: 'button', class: 'wt-btn' + (on ? '' : ' is-primary'), text: on ? t('Stop recording') : t('Record my changes') });
        button.addEventListener('click', function () {
          button.disabled = true;
          ctx.call(on ? 'migrations.record.stop' : 'migrations.record.start', {}).then(function (answer) {
            if (ctx.recording) { ctx.recording(answer); }
            if (Migrations.recording(answer) === 'none') { box.remove(); return; }
            draw(answer);
            ctx.say(Migrations.recording(answer) === 'on' ? t('Your changes are recorded for a migration.') : t('Recording stopped.'), 'ok');
          }, function (err) { button.disabled = false; failure(ctx, err); });
        });
        box.appendChild(h('h3', { id: 'wt-mig-rec', class: 'wt-rec-title' }, [
          on ? h('span', { class: 'wt-rec-dot', 'aria-hidden': 'true' }) : null,
          h('span', { text: on ? t('Recording…') : t('Record my changes') })
        ]));
        if (on) {
          box.appendChild(h('p', { role: 'status', text: t('Your changes are recorded for a migration.') }));
          if (r.since) { box.appendChild(h('p', { class: 'wt-muted', text: Migrations.at(r.since, lang) })); }
        }
        if (r.site) { box.appendChild(h('p', { class: 'wt-notice is-warn', role: 'status', text: t('A site-wide recording is open: every change is recorded.') })); }
        box.appendChild(h('div', { class: 'wt-row' }, [button]));
      }
      draw(rec);
      return box;
    }
    root.appendChild(h('p', { class: 'wt-muted', text: t('Loading ...') }));
    ctx.call('migrations.load', {}).then(function (r) {
      root.textContent = '';
      if (ctx.migrations) { ctx.migrations(r.attention); }
      var rec = recording(r.recording);
      if (rec) { root.appendChild(rec); }
      root.appendChild(h('p', { class: 'wt-muted', text: t('Read only: running and rolling back stay in the administration.') }));
      if (!r.table) {
        root.appendChild(h('div', { class: 'wt-notice is-warn', role: 'status', text: t('The migration tables do not exist yet: exp:upgrade --apply makes them.') }));
      }
      if (r.lock) {
        root.appendChild(h('div', { class: 'wt-notice is-warn wt-mig-lock', role: 'status' }, [
          h('strong', { text: t('A run is active') }),
          h('span', { text: t('%purpose, by %user since %time').replace('%purpose', r.lock.purpose).replace('%user', r.lock.by).replace('%time', Migrations.at(r.lock.started, lang)) }),
          open(r.lock.url)
        ]));
      }
      if (r.problems) {
        root.appendChild(h('div', { class: 'wt-notice is-bad', role: 'status', text: t('%n problems reading the migrations').replace('%n', r.problems) }));
      }
      if (r.failed.length) {
        root.appendChild(section('wt-mig-f', t('Failed'), [h('ul', { class: 'wt-list wt-mig-list' }, r.failed.map(function (m) {
          return item(m, [chip(t('Failed'), 'failed')]);
        }))]));
      }
      var pending = r.pending.length
        ? [h('ul', { class: 'wt-list wt-mig-list' }, r.pending.map(function (m) { return item(m, [chip(t('Pending'), 'pending')]); }))]
        : [h('p', { class: 'wt-muted', text: t('No migration is pending.') })];
      if (r.more) { pending.push(h('p', { class: 'wt-muted', text: t('%n more in the administration').replace('%n', r.more) })); }
      root.appendChild(section('wt-mig-p', t('Pending'), pending));
      if (r.orphans.length) {
        root.appendChild(section('wt-mig-o', t('Recorded, but the file is gone'), [h('ul', { class: 'wt-list wt-mig-list' }, r.orphans.map(function (m) {
          return item(m, [chip(runText[m.state] || m.state, m.state)]);
        }))]));
      }
      var last = r.last;
      if (!last) {
        root.appendChild(section('wt-mig-l', t('Last run'), [h('p', { class: 'wt-muted', text: t('No migration has run yet.') })]));
        return;
      }
      var facts = [].concat(
        row(t('Run'), h('span', { class: 'wt-mig-id', text: last.id })),
        row(t('State'), chip(runText[last.state] || last.state, last.state)),
        row(t('Started'), Migrations.at(last.started, lang)),
        row(t('Finished'), Migrations.at(last.finished, lang)),
        row(t('Run by'), last.by),
        row(t('Caption'), last.caption),
        row(t('Tag'), last.tag),
        row(t('Push'), last.push ? chip(pushText[last.push] || last.push, last.push) : null)
      );
      var children = [h('dl', { class: 'wt-mig-run' }, facts)];
      if (last.message) { children.push(h('p', { class: 'wt-notice is-bad', text: last.message })); }
      if (last.url) { children.push(h('div', { class: 'wt-row' }, [open(last.url)])); }
      root.appendChild(section('wt-mig-l', t('Last run'), children));
      deploys(r.deploys);
    }, function (err) { root.textContent = ''; failure(ctx, err); });
    /* The latest deploys to remotes (WP18), read-only, in the kernel's words (d.labels): each with its state, whether
       the remotes are in sync, every remote's state, and a link to its page in the administration. */
    function deploys(d) {
      if (!d) { return; }
      var L = d.labels || {};
      var children = d.items.length ? [h('ul', { class: 'wt-list wt-mig-list' }, d.items.map(function (x) {
        var meta = [h('span', { class: 'wt-chip' + (Migrations.deployChip(x.kind) ? ' ' + Migrations.deployChip(x.kind) : ''), text: x.text })];
        if (x.in_sync === false) { meta.push(h('span', { class: 'wt-chip is-bad', text: L.outOfSync })); }
        if (x.transfer_only) { meta.push(h('span', { class: 'wt-chip', text: L.transferOnly })); }
        x.remotes.forEach(function (rm) {
          meta.push(h('span', { class: 'wt-chip' + (Migrations.deployChip(rm.kind) ? ' ' + Migrations.deployChip(rm.kind) : ''), text: rm.id + ': ' + rm.text }));
        });
        return h('li', { class: 'wt-mig' }, [
          h('div', { class: 'wt-mig-info' }, [
            h('span', { class: 'wt-mig-id', text: x.id }),
            h('span', { class: 'wt-url-meta' }, meta),
            h('span', { class: 'wt-muted wt-mig-desc', text: [Migrations.at(x.started, lang), x.by].filter(Boolean).join(' \u00b7 ') })
          ]),
          open(x.url)
        ]);
      }))] : [h('p', { class: 'wt-muted', text: L.none })];
      if (d.url) { children.push(h('div', { class: 'wt-row' }, [open(d.url)])); }
      root.appendChild(section('wt-mig-d', L.title, children));
    }
  }

  /* -------------------------------------------------------------------------------------------------- static */

  /* The Static panel's pure parts (S6), for the tests (tests/js/static.test.js): the text and chip of the page's state
     on a target, and of the target's last publish, from the kernel's words the action sends (r.labels). */
  var Static = {
    page: function (state, L) {
      if (state === 'stale') { return { text: L.yes, chip: 'is-warn' }; }
      if (state === 'current') { return { text: L.no, chip: 'is-ok' }; }
      return { text: L.missing, chip: '' };
    },
    last: function (last, L) {
      if (!last) { return { text: L.never, chip: '' }; }
      var map = { failed: [L.failed, 'is-bad'], unchanged: [L.unchanged, ''], pushed: [L.pushed, 'is-ok'], 'not-pushed': [L.notPushed, 'is-warn'] };
      var m = map[last.state] || [last.state, ''];
      return { text: m[0], chip: m[1] };
    },
    fill: function (text, values) {
      return Object.keys(values).reduce(function (s, k) { return s.split(k).join(values[k]); }, String(text || ''));
    }
  };

  /* The page in the kernel's static publishing (S6), for a user with staticpublish/read: per target the last publish,
     whether this page is stale there, its public address there and the job going on. "Publish" (staticpublish/publish
     of the target, "Verify it's you") starts the kernel's publish job for this page only: an incremental build of its
     node, then the publish; the job's page in the administration follows it. Every text is the kernel's own. */
  function staticPanel(ctx, root) {
    var h = ctx.h;
    var t = ctx.t;
    var lang = (document.documentElement && document.documentElement.lang) || undefined;
    if (!ctx.node) {
      root.appendChild(h('p', { text: t('This page is not content of the site. Open a page of the site to edit it.') }));
      return;
    }
    function row(label, value) {
      return value ? [h('dt', { text: label }), h('dd', {}, [value])] : [];
    }
    function chip(v) {
      return h('span', { class: 'wt-chip' + (v.chip ? ' ' + v.chip : ''), text: v.text });
    }
    function jobLink(L, job) {
      var text = Static.fill(L.job, { '%id': job.id });
      return job.url ? h('a', { href: job.url, text: text }) : h('span', { text: text });
    }
    function target(r, x) {
      var L = r.labels;
      var id = 'wt-static-' + x.id;
      var box = h('section', { class: 'wt-section wt-static', 'aria-labelledby': id }, [h('h3', { id: id, text: x.name })]);
      var last = Static.last(x.last, L);
      var lastValue = x.last ? h('span', {}, [h('span', { text: Migrations.at(new Date(x.last.at * 1000).toISOString(), lang) + ' ' }), chip(last)])
        : h('span', { class: 'wt-muted', text: last.text });
      var facts = [].concat(
        row(L.lastPublish, lastValue),
        row(L.stale, chip(Static.page(x.page.state, L))),
        row(L.address, x.page.url ? h('a', { href: x.page.url, rel: 'noopener', target: '_blank', text: x.page.url }) : null)
      );
      box.appendChild(h('dl', { class: 'wt-static-run' }, facts));
      var status = h('div', { role: 'status' });
      box.appendChild(status);
      if (x.job) {
        status.appendChild(h('p', { class: 'wt-notice is-warn' }, [h('span', { text: Static.fill(L.going, { '%user': x.job.user }) + ' ' }), jobLink(L, x.job)]));
      }
      var actions = [];
      if (x.publish && !x.job) {
        var button = h('button', { type: 'button', class: 'wt-btn is-primary', text: L.publish });
        button.addEventListener('click', function () {
          button.disabled = true;
          ctx.call('static.publish', { nodeId: ctx.node, target: x.id }).then(function (answer) {
            status.textContent = '';
            status.appendChild(h('p', { class: 'wt-notice is-ok' }, [h('span', { text: answer.message + ' ' }), jobLink(L, answer)]));
            ctx.say(answer.message, 'ok');
            button.remove();
          }, function (err) { button.disabled = false; failure(ctx, err); });
        });
        actions.push(button);
      }
      if (x.history) { actions.push(h('a', { class: 'wt-btn', href: x.history, text: L.history })); }
      if (actions.length) { box.appendChild(h('div', { class: 'wt-row' }, actions)); }
      return box;
    }
    root.appendChild(h('p', { class: 'wt-muted', text: t('Loading ...') }));
    ctx.call('static.load', { nodeId: ctx.node }).then(function (r) {
      var L = r.labels;
      root.textContent = '';
      if (!r.enabled) { root.appendChild(h('p', { class: 'wt-notice is-warn', role: 'status', text: L.off })); }
      if (!r.targets.length) {
        root.appendChild(h('p', { class: 'wt-muted', text: L.noTarget }));
        if (r.setup) {
          root.appendChild(h('div', { class: 'wt-row' }, [
            r.setup.make ? h('a', { class: 'wt-btn', href: r.setup.make, text: L.make }) : null,
            r.setup.tutorial ? h('a', { class: 'wt-btn', href: r.setup.tutorial, text: L.tutorial }) : null
          ]));
        }
        return;
      }
      r.targets.forEach(function (x) { root.appendChild(target(r, x)); });
    }, function (err) { root.textContent = ''; failure(ctx, err); });
  }

  /* --------------------------------------------------------------------------------------------- performance */

  /* The Performance panel's pure parts (E12), for the tests (tests/js/performance.test.js).
       cached()  the page was not rendered for this view: its render is older than the page's load (the page cache,
                 a proxy, the browser's history). now and clientNow are the server's and the device's clocks at the
                 same moment, navStart when the page's load began on the device (performance.timeOrigin).
       ms()      a time as the panel writes it: "84 ms", "1.24 s"
       bytes()   memory: "18 MB"
       share()   a block's time as a part of the slowest block's, 0 to 100 */
  var Performance = {
    SLACK: 500,
    cached: function (render, now, clientNow, navStart) {
      if (!render || !render.at || !now || !clientNow || !navStart) { return false; }
      return render.at - (now - clientNow) < navStart - Performance.SLACK;
    },
    ms: function (v, lang) {
      if (v === null || v === undefined || isNaN(v)) { return ''; }
      var f = function (n, d) {
        try { return new Intl.NumberFormat(lang, { maximumFractionDigits: d }).format(n); } catch (e) { return String(Math.round(n * Math.pow(10, d)) / Math.pow(10, d)); }
      };
      return v >= 1000 ? f(v / 1000, 2) + ' s' : f(v, v < 10 ? 1 : 0) + ' ms';
    },
    bytes: function (b, lang) {
      if (!b) { return ''; }
      var mb = b / 1048576;
      try { return new Intl.NumberFormat(lang, { maximumFractionDigits: mb < 10 ? 1 : 0 }).format(mb) + ' MB'; } catch (e) { return Math.round(mb) + ' MB'; }
    },
    share: function (ms, max) {
      if (!max || !ms) { return 0; }
      return Math.max(0, Math.min(100, Math.round(ms / max * 100)));
    }
  };

  /* How this page was rendered (E12), for admins and developers (expwt/performance): the page time, the SQL statements,
     peak memory, the content view cache, the cache-blocks and the time of every layout block. The figures are the
     kernel's debug bar data of the render itself; they came with the page (ctx.render), the action only adds the
     server's time and what the kernel collects. A page from a cache was not rendered for this view: the panel says so. */
  function performance(ctx, root) {
    var h = ctx.h;
    var t = ctx.t;
    var lang = (document.documentElement && document.documentElement.lang) || undefined;
    var r = ctx.render;
    var stateText = { hit: t('From the cache'), generated: t('Generated'), expired: t('Generated'), uncached: t('Generated'), off: t('Off'), pending: t('Pending') };
    function section(id, title, children) {
      return h('section', { class: 'wt-section', 'aria-labelledby': id }, [h('h3', { id: id, text: title })].concat(children));
    }
    function row(label, value) {
      return value ? [h('dt', { text: label }), h('dd', {}, [h('span', { class: 'wt-perf-figure', text: value })])] : [];
    }
    function chip(state) {
      return h('span', { class: 'wt-chip' + (state === 'hit' ? ' is-ok' : state === 'off' || state === 'pending' ? '' : ' is-warn'), text: stateText[state] || state });
    }
    function item(name, state, ms) {
      return h('li', {}, [h('span', { class: 'wt-perf-name', text: name }), state ? chip(state) : null, h('span', { class: 'wt-perf-ms', text: Performance.ms(ms, lang) })]);
    }
    root.appendChild(h('p', { class: 'wt-muted', text: t('Loading ...') }));
    /* the device's clock at the middle of the request: the server's time was taken then, give or take half the round trip */
    var asked = Date.now();
    ctx.call('performance.load', {}).then(function (s) {
      var clientNow = Math.round((asked + Date.now()) / 2);
      root.textContent = '';
      if (!r) {
        root.appendChild(h('div', { class: 'wt-notice is-warn', role: 'status', text: t('This page carries no figures of its render.') }));
        return;
      }
      var start = window.performance && window.performance.timeOrigin ? window.performance.timeOrigin : 0;
      if (Performance.cached(r, s.now, clientNow, start)) {
        var when = new Date(r.at - (s.now - clientNow));
        var at = when.toLocaleString();
        try { at = new Intl.DateTimeFormat(lang, { dateStyle: 'medium', timeStyle: 'medium' }).format(when); } catch (e) { /* the default */ }
        root.appendChild(h('div', { class: 'wt-notice is-warn wt-perf-cached', role: 'status', text: t('This page came from a cache: it was not rendered for this view. The figures are of the render at %time.').replace('%time', at) }));
      }
      root.appendChild(h('dl', { class: 'wt-mig-run wt-perf-facts' }, [].concat(
        row(t('Render time'), Performance.ms(r.total_ms, lang)),
        row(t('SQL statements'), r.sql ? r.sql.count + ' · ' + Performance.ms(r.sql.ms, lang) : ''),
        row(t('Peak memory'), Performance.bytes(r.memory, lang))
      )));
      if (r.source === 'none') {
        root.appendChild(h('div', { class: 'wt-notice', role: 'status', text: t('Only the time and memory: the kernel collects the rest with debug output on for you, or for expwt/performance from Exponential 6.0.15 on.') }));
        return;
      }
      if (r.view_cache) {
        root.appendChild(section('wt-perf-v', t('Content view cache'), [h('p', {}, [chip(r.view_cache.state),
          r.view_cache.ms ? h('span', { class: 'wt-perf-ms', text: ' ' + Performance.ms(r.view_cache.ms, lang) }) : null])]));
      }
      if (r.cache_blocks && r.cache_blocks.count) {
        var cb = r.cache_blocks;
        root.appendChild(section('wt-perf-c', t('Cache-blocks'), [
          h('p', { text: t('%hits of %count from the cache').replace('%hits', cb.hits).replace('%count', cb.count) }),
          h('ul', { class: 'wt-list wt-perf-list' }, cb.slowest.map(function (b) { return item(b.template + (b.line ? ':' + b.line : ''), b.state, b.ms); }))
        ]));
      }
      var blocks = r.blocks || [];
      /* blocks from the content view cache or a cache-block were not rendered now: the figures above say so */
      if (!blocks.length && ((r.view_cache && r.view_cache.state === 'hit') || (r.cache_blocks && r.cache_blocks.hits))) { return; }
      var max = blocks.reduce(function (m, b) { return Math.max(m, b.ms || 0); }, 0);
      root.appendChild(section('wt-perf-b', t('Time per layout block'), blocks.length
        ? [h('ol', { class: 'wt-list wt-perf-list' }, blocks.map(function (b) {
          return h('li', { class: 'wt-perf-l' + Math.min(3, b.level || 0) }, [
            h('span', { class: 'wt-perf-name', text: b.template }),
            h('meter', { class: 'wt-perf-meter', min: '0', max: '100', value: String(Performance.share(b.ms, max)), 'aria-hidden': 'true' }),
            h('span', { class: 'wt-perf-ms', text: Performance.ms(b.ms, lang) })
          ]);
        }))]
        : [h('p', { class: 'wt-muted', text: t('No layout block on this page.') })]));
    }, function (err) { root.textContent = ''; failure(ctx, err); });
  }

  /* ------------------------------------------------------------------ export (E13) */

  /* The Export panel's pure parts (E13), for the tests (tests/js/export.test.js).
       printTokens()  the value of data-expwt-print on <html> while printing: "content" leaves out the site's header,
                      menus and footer, "urls" writes each link's address after it (expwt.js has the print rules)
       bytes()        the bytes of a base64 text, for the downloaded file
       lifetime()     a lifetime in the user's language: "1 hour", "3 days" (Intl, no strings of our own)
       state()        a link's chip: its class and its text key */
  var Export = {
    printTokens: function (content, urls) {
      return ['print'].concat(content ? ['content'] : [], urls ? ['urls'] : []).join(' ');
    },
    bytes: function (base64) {
      var raw = window.atob(base64 || '');
      var out = new Uint8Array(raw.length);
      for (var i = 0; i < raw.length; i++) { out[i] = raw.charCodeAt(i); }
      return out;
    },
    lifetime: function (seconds, lang) {
      var unit = seconds % 86400 === 0 ? 'day' : seconds % 3600 === 0 ? 'hour' : 'minute';
      var n = seconds / (unit === 'day' ? 86400 : unit === 'hour' ? 3600 : 60);
      try { return new Intl.NumberFormat(lang, { style: 'unit', unit: unit, unitDisplay: 'long' }).format(n); } catch (e) { return n + ' ' + unit + (n === 1 ? '' : 's'); }
    },
    state: function (link) {
      if (link.state === 'active') { return { cls: 'wt-chip is-ok', key: 'Works until %time' }; }
      return { cls: 'wt-chip', key: link.state === 'revoked' ? 'Withdrawn' : 'Expired' };
    },
    /* A button's action while it runs: aria-busy on the button (a spinner, its label and focus stay), a second press
       sends nothing; while "Verify it's you" waits in its window, a row under the button's row brings the window back
       or cancels. The button always comes back and a failure is said, whatever the answer: before, the buttons were
       disabled until the answer and stayed grey for as long as the window waited, or for ever when a second button
       asked while it was open. work(opts) returns the promise of ctx.call(action, input, opts). */
    run: function (ctx, button, work) {
      if (button.getAttribute('aria-busy') === 'true') { return null; }
      var t = ctx.t;
      var row = null;
      var box = button.parentNode;
      button.setAttribute('aria-busy', 'true');
      button.setAttribute('aria-disabled', 'true');
      function done() {
        button.removeAttribute('aria-busy');
        button.removeAttribute('aria-disabled');
        if (row && row.parentNode) { row.parentNode.removeChild(row); }
      }
      var opts = { onReauth: function (r) {
        var next = box && box.nextSibling;
        if (next && next.className === 'wt-row wt-reauth-wait') { row = next; return; }
        row = ctx.h('div', { class: 'wt-row wt-reauth-wait', role: 'group', 'aria-label': t('Verify it\'s you') }, [
          ctx.h('button', { type: 'button', class: 'wt-btn is-primary', text: t('Verify it\'s you'), onclick: function () { r.focus(); } }),
          ctx.h('button', { type: 'button', class: 'wt-btn', text: t('Cancel'), onclick: function () { r.cancel(); } })
        ]);
        if (box && box.parentNode) { box.parentNode.insertBefore(row, box.nextSibling); }
      } };
      var p;
      try { p = Promise.resolve(work(opts)); } catch (e) { p = Promise.reject(e); }
      return p.then(function (value) { done(); return value; }, function (err) {
        done();
        if (!err || !err.cancelled) { failure(ctx, err); }
      });
    }
  };

  /* The page as a file (E13): print it or save it as a PDF (the browser's print dialog, with a print layout), its
     fields as Markdown or Word (the published version or the user's draft), and a signed, expiring share link to the
     user's draft for reviewers without an account (the kernel's expContentPreviewLink, with expwt/edit). Making a file
     or a link asks "Verify it's you" first (the export group), as the administration's exports do. */
  function exportPanel(ctx, root) {
    var h = ctx.h;
    var t = ctx.t;
    var lang = (document.documentElement && document.documentElement.lang) || undefined;
    function section(id, title, children) {
      return h('section', { class: 'wt-section', 'aria-labelledby': id }, [h('h3', { id: id, text: title })].concat(children));
    }
    function when(ts) {
      try { return new Intl.DateTimeFormat(lang, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(ts * 1000)); } catch (e) { return new Date(ts * 1000).toLocaleString(); }
    }

    /* print and PDF: the choices become data-expwt-print on <html> while the dialog is open */
    var onlyContent = h('input', { type: 'checkbox', id: 'wt-exp-content' });
    var urls = h('input', { type: 'checkbox', id: 'wt-exp-urls' });
    var html = document.documentElement;
    function print() {
      html.setAttribute('data-expwt-print', Export.printTokens(onlyContent.checked, urls.checked));
      var done = function () { html.removeAttribute('data-expwt-print'); window.removeEventListener('afterprint', done); };
      window.addEventListener('afterprint', done);
      window.print();
      /* a browser without afterprint, or a dialog that does not block: the attribute goes with the next frame after it */
      window.setTimeout(done, 1000);
    }
    root.appendChild(section('wt-exp-p', t('Print or save as PDF'), [
      h('p', { class: 'wt-muted', text: t('Prints the page as it is now. To get a PDF, choose "Save as PDF" in the print dialog.') }),
      h('label', { class: 'wt-check', for: 'wt-exp-content' }, [onlyContent, h('span', { text: t('Only the content, without header, menus and footer') })]),
      h('label', { class: 'wt-check', for: 'wt-exp-urls' }, [urls, h('span', { text: t('Link addresses after the links') })]),
      h('div', { class: 'wt-row' }, [h('button', { type: 'button', class: 'wt-btn is-primary', text: t('Print or save as PDF'), onclick: print })])
    ]));

    var fileBox = h('div', {}, [h('p', { class: 'wt-muted', text: t('Loading ...') })]);
    var shareBox = h('div', {});
    root.appendChild(section('wt-exp-f', t('Fields as a file'), [fileBox]));
    root.appendChild(section('wt-exp-s', t('Share the draft for review'), [shareBox]));

    if (!ctx.node) {
      fileBox.textContent = '';
      fileBox.appendChild(h('p', { class: 'wt-muted', text: t('This page is not there, or you may not read it.') }));
      shareBox.parentNode.parentNode.removeChild(shareBox.parentNode);
      return;
    }
    ctx.call('page.load', { nodeId: ctx.node }).then(function (page) {
      fileBox.textContent = '';
      var which = page.draft ? 'draft' : 'published';
      if (page.draft && page.publishedVersion) {
        var name = 'wt-exp-which';
        fileBox.appendChild(h('fieldset', { class: 'wt-section wt-choice' }, [h('legend', { text: t('Version') }),
          h('label', { class: 'wt-check' }, [h('input', { type: 'radio', name: name, checked: true, onchange: function () { which = 'draft'; } }), h('span', { text: t('Draft') })]),
          h('label', { class: 'wt-check' }, [h('input', { type: 'radio', name: name, onchange: function () { which = 'published'; } }), h('span', { text: t('Published') })])
        ]));
      }
      function download(format, button) {
        Export.run(ctx, button, function (opts) {
          return ctx.call('export.fields', { objectId: page.objectId, which: which, version: which === 'draft' && page.draft ? page.draft.version : undefined,
            language: page.language, format: format }, opts).then(function (file) {
            var url = URL.createObjectURL(new Blob([Export.bytes(file.data)], { type: file.mime }));
            var a = document.createElement('a');
            a.href = url;
            a.download = file.fileName;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.setTimeout(function () { URL.revokeObjectURL(url); }, 10000);
            ctx.say(file.fileName, 'ok');
          });
        });
      }
      /* the formats' own names, as the file managers show them: not translated */
      var md = h('button', { type: 'button', class: 'wt-btn', text: 'Markdown (.md)' });
      var docx = h('button', { type: 'button', class: 'wt-btn', text: 'Word (.docx)' });
      md.onclick = function () { download('md', md); };
      docx.onclick = function () { download('docx', docx); };
      fileBox.appendChild(h('div', { class: 'wt-row' }, [md, docx]));
      if (!ctx.boot || !ctx.boot.capabilities || !ctx.boot.capabilities.edit) {
        shareBox.parentNode.parentNode.removeChild(shareBox.parentNode);
        return;
      }
      share(page);
    }, function (err) { fileBox.textContent = ''; failure(ctx, err); });

    /* the share links: the user's draft, a lifetime and a note, the link to copy, and the page's links to withdraw */
    function share(page) {
      shareBox.appendChild(h('p', { class: 'wt-muted', text: t('Loading ...') }));
      ctx.call('share.load', { objectId: page.objectId, language: page.language }).then(function (s) {
        shareBox.textContent = '';
        if (!s.available) {
          shareBox.appendChild(h('div', { class: 'wt-notice', role: 'status', text: t('Share links need Exponential 6.0.15 or later, with the secret store.') }));
          return;
        }
        var made = h('div', { 'aria-live': 'polite' });
        if (!s.draft) {
          shareBox.appendChild(h('p', { class: 'wt-muted', text: t('Save a draft first: a share link shows your draft.') }));
        } else if (!s.canShare) {
          shareBox.appendChild(h('p', { class: 'wt-muted', text: t('You may not share this draft.') }));
        } else {
          var lifetime = h('select', { id: 'wt-exp-life' }, s.lifetimes.map(function (l) {
            return h('option', { value: String(l.seconds), selected: l.chosen, text: Export.lifetime(l.seconds, lang) });
          }));
          var note = h('input', { type: 'text', id: 'wt-exp-note', maxlength: '100', autocomplete: 'off' });
          var create = h('button', { type: 'button', class: 'wt-btn is-primary', text: t('Create link') });
          create.onclick = function () {
            Export.run(ctx, create, function (opts) {
              return ctx.call('share.create', { objectId: page.objectId, version: s.draft.version, language: s.draft.language,
                lifetime: parseInt(lifetime.value, 10), note: note.value }, opts).then(function (r) {
                note.value = '';
                made.textContent = '';
                made.appendChild(linkField(r.url, 'wt-exp-new'));
                made.appendChild(h('p', { class: 'wt-muted', text: t('Works until %time').replace('%time', when(r.expires)) }));
                list(r.links);
              });
            });
          };
          shareBox.appendChild(h('p', { class: 'wt-muted', text: t('Anyone with the link sees your draft without an account until the link expires. Send it only to your reviewers.') }));
          shareBox.appendChild(h('label', { class: 'wt-field', for: 'wt-exp-life' }, [h('span', { text: t('Works for') }), lifetime]));
          shareBox.appendChild(h('label', { class: 'wt-field', for: 'wt-exp-note' }, [h('span', { text: t('For whom (optional)') }), note]));
          shareBox.appendChild(h('div', { class: 'wt-row' }, [create]));
          shareBox.appendChild(made);
        }
        var listBox = h('ul', { class: 'wt-list wt-exp-links' });
        shareBox.appendChild(listBox);
        function list(links) {
          listBox.textContent = '';
          links.forEach(function (link) {
            var st = Export.state(link);
            var parts = [h('span', { class: st.cls, text: t(st.key).replace('%time', when(link.expires)) }),
              h('span', { class: 'wt-exp-what', text: [link.note, link.by, t('Opened: %count').replace('%count', link.opened)].filter(Boolean).join(' · ') })];
            if (link.url) { parts.push(linkField(link.url, 'wt-exp-l' + link.id)); }
            if (link.state === 'active') {
              var withdraw = h('button', { type: 'button', class: 'wt-btn is-danger', text: t('Withdraw') });
              withdraw.onclick = function () {
                Export.run(ctx, withdraw, function (opts) {
                  return ctx.call('share.revoke', { objectId: page.objectId, id: link.id }, opts).then(function (r) {
                    ctx.say(t('Withdrawn'), 'ok');
                    made.textContent = '';
                    list(r.links);
                  });
                });
              };
              parts.push(h('div', { class: 'wt-row' }, [withdraw]));
            }
            listBox.appendChild(h('li', { class: 'wt-exp-link' }, parts));
          });
        }
        list(s.links);
      }, function (err) { shareBox.textContent = ''; failure(ctx, err); });
    }

    /* a link to copy: the address in a read-only field (selected on focus) and a button that copies it */
    function linkField(url, id) {
      var input = h('input', { type: 'text', id: id, readonly: true, value: url, 'aria-label': t('Share the draft for review') });
      input.onfocus = function () { input.select(); };
      var copy = h('button', { type: 'button', class: 'wt-btn', text: t('Copy link') });
      copy.onclick = function () {
        var ok = function () { ctx.say(t('Copied.'), 'ok'); };
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(url).then(ok, function () { input.select(); });
        } else {
          input.select();
          try { if (document.execCommand('copy')) { ok(); } } catch (e) { /* selected for the user to copy */ }
        }
      };
      return h('div', { class: 'wt-exp-url' }, [input, copy]);
    }
  }

  /* ------------------------------------------------------------------ the device preview (E8) */

  /* Pure (tests/js/devices.test.js): how the frames of the device preview fit the window, the width typed for a frame
     of one's own, and which address a frame shows.
       fit()     one row at a common scale where every frame stays at least MIN pixels wide; else each frame on its own,
                 as large as the window allows (never larger than the device, at least TALL pixels high where the
                 window is lower), the frames wrapping
       custom()  a whole number of CSS pixels from min to max, else null
       src()     the user's draft where there is one and it is chosen, else the published page */
  var Devices = {
    MIN: 160,
    TALL: 560,
    KEY: 'expwt.devices',
    fit: function (frames, width, height, gap) {
      var n = frames.length;
      if (!n) { return { row: true, scales: [] }; }
      var floor = function (x) { return Math.max(0.05, Math.floor(Math.min(1, x) * 1000) / 1000); };
      var sum = 0;
      var tallest = 0;
      var narrowest = Infinity;
      frames.forEach(function (f) { sum += f.width; tallest = Math.max(tallest, f.height); narrowest = Math.min(narrowest, f.width); });
      var common = floor(Math.min((width - gap * (n - 1)) / sum, height / tallest));
      if (narrowest * common >= Devices.MIN) {
        return { row: true, scales: frames.map(function () { return common; }) };
      }
      /* each on its own: a low window (a phone, its choices above) still gets frames TALL pixels high, the body scrolls */
      var tall = Math.max(height, Devices.TALL);
      return { row: false, scales: frames.map(function (f) { return floor(Math.min(width / f.width, tall / f.height)); }) };
    },
    custom: function (text, min, max) {
      var s = String(text === null || text === undefined ? '' : text).trim();
      if (!/^[0-9]{1,5}$/.test(s)) { return null; }
      var n = parseInt(s, 10);
      return n >= min && n <= max ? n : null;
    },
    src: function (urls, which) {
      return which === 'draft' && urls.draft ? urls.draft : urls.published;
    },
    /* what this device remembers of the preview: the frames shown, the widths added, those of them hidden, the mode
       of each frame */
    remembered: function (raw, ids, custom) {
      var r = raw && typeof raw === 'object' ? raw : {};
      var shown = Array.isArray(r.shown) ? r.shown.filter(function (id) { return ids.indexOf(id) >= 0; }) : null;
      var widths = Array.isArray(r.widths) ? r.widths.map(function (w) { return Devices.custom(w, custom.min, custom.max); })
        .filter(function (w, i, all) { return w !== null && all.indexOf(w) === i; }).slice(0, 4) : [];
      var hidden = Array.isArray(r.hidden) ? r.hidden.map(function (w) { return Devices.custom(w, custom.min, custom.max); })
        .filter(function (w, i, all) { return w !== null && widths.indexOf(w) >= 0 && all.indexOf(w) === i; }) : [];
      var modes = {};
      if (r.modes && typeof r.modes === 'object') {
        Object.keys(r.modes).forEach(function (k) { if (r.modes[k] === 'light' || r.modes[k] === 'dark') { modes[k] = r.modes[k]; } });
      }
      return { shown: shown, widths: widths, hidden: hidden, modes: modes };
    },
    /* The toggles of the frames (one per device, one per width of one's own). A choice is { shown: device ids in the
       order of the settings, hidden: widths of one's own that are not shown }.
       flip()     shows a frame that is hidden and hides one that is shown (item: { id } of a device or { width })
       all()      every frame shown
       allShown() whether every frame is shown already
       visible()  the frames shown, the devices first, in the order of the toggles */
    flip: function (choice, item, ids) {
      var shown = choice.shown.slice();
      var hidden = choice.hidden.slice();
      if (item.width !== undefined && item.id === undefined) {
        hidden = hidden.indexOf(item.width) >= 0 ? hidden.filter(function (w) { return w !== item.width; }) : hidden.concat([item.width]);
      } else {
        var on = shown.indexOf(item.id) < 0;
        shown = ids.filter(function (id) { return id === item.id ? on : shown.indexOf(id) >= 0; });
      }
      return { shown: shown, hidden: hidden };
    },
    all: function (ids) {
      return { shown: ids.slice(), hidden: [] };
    },
    allShown: function (choice, ids, widths) {
      return ids.every(function (id) { return choice.shown.indexOf(id) >= 0; })
        && widths.every(function (w) { return choice.hidden.indexOf(w) < 0; });
    },
    visible: function (choice, ids, widths) {
      return ids.filter(function (id) { return choice.shown.indexOf(id) >= 0; }).map(function (id) { return { id: id }; })
        .concat(widths.filter(function (w) { return choice.hidden.indexOf(w) < 0; }).map(function (w) { return { width: w }; }));
    }
  };

  /* The device preview (E8): a view over the whole window with the page in a frame per device, side by side where
     they fit, the 200% zoom case among them, plus frames of a width of one's own; the user's draft or the published
     page; light or dark per frame (the media designs' html[data-exp-theme], set in that frame only; "Like the site"
     loads it again as the site has it). The pages in the frames come without a sidebar (?in=sidebar). Escape, Close
     or the backdrop end it. */
  function devices(ctx, p) {
    var h = ctx.h;
    var t = ctx.t;
    var host = ctx.host;
    ctx.call('preview.devices', { nodeId: p.nodeId, language: p.language }).then(function (r) {
      var storage = null;
      try { storage = window.localStorage; } catch (e) { storage = null; }
      var ids = r.devices.map(function (d) { return d.id; });
      var kept = Devices.remembered(storage ? Autosave.read(storage, Devices.KEY) : null, ids, r.custom);
      /* the frames shown: what this browser remembers, else ShownDeviceList[] of the installation */
      var choice = { shown: kept.shown || r.shown.slice(), hidden: kept.hidden };
      var widths = kept.widths;
      var modes = kept.modes;
      var which = r.urls.draft ? 'draft' : 'published';
      function keep() {
        if (storage) { Autosave.write(storage, Devices.KEY, { shown: choice.shown, widths: widths, hidden: choice.hidden, modes: modes }); }
      }
      if (host.devicesView) { host.devicesView.remove(); }
      var body = h('div', { class: 'wt-dev-body' });
      var status = h('p', { class: 'wt-sr', role: 'status' });
      var view = h('dialog', { class: 'wt-split wt-dev', 'aria-labelledby': 'wt-dev-title',
        onclose: function () { if (observer) { observer.disconnect(); } view.remove(); host.devicesView = null; } });
      host.devicesView = view;
      var close = function () { view.close(); };

      /* the version shown in every frame */
      var versions = null;
      if (r.urls.draft) {
        versions = h('fieldset', { class: 'wt-dev-group' }, [h('legend', { text: t('Version') })].concat(['draft', 'published'].map(function (v) {
          var input = h('input', { type: 'radio', name: 'wt-dev-version', value: v, checked: v === which, onchange: function () { which = v; draw(); } });
          var label = v === 'draft' ? t('Your draft of %time').replace('%time', when(r.draft.modified)) : t('Published');
          return h('label', { class: 'wt-check' }, [input, label]);
        })));
      }
      /* the frames shown: a toggle per device and per width of one's own (pressed: shown), and Show all. A toggle
         hides or shows its frame at once (a frame not shown yet loads then), the others fit the room again. */
      var toggles = h('div', { class: 'wt-dev-toggles' });
      var picks = h('fieldset', { class: 'wt-dev-group wt-dev-picks' }, [h('legend', { text: t('Frames shown') }), toggles]);
      function nameOf(item) {
        if (item.width !== undefined) { return t('%w pixels wide').replace('%w', item.width); }
        return r.devices[ids.indexOf(item.id)].name;
      }
      function drawToggles() {
        toggles.textContent = '';
        r.devices.map(function (d) { return { id: d.id }; }).concat(widths.map(function (w) { return { width: w }; })).forEach(function (item) {
          toggles.appendChild(h('button', { type: 'button', class: 'wt-btn wt-dev-toggle', 'data-key': item.width !== undefined ? 'w' + item.width : item.id,
            'aria-pressed': 'false', text: nameOf(item), onclick: function () {
              choice = Devices.flip(choice, item, ids);
              keep();
              apply();
            } }));
        });
        toggles.appendChild(h('button', { type: 'button', class: 'wt-btn wt-dev-all', text: t('Show all'), onclick: function () {
          choice = Devices.all(ids);
          keep();
          apply();
          status.textContent = t('All frames are shown.');
        } }));
      }
      /* a frame of a width of one's own */
      var widthInput = h('input', { type: 'number', id: 'wt-dev-width', min: String(r.custom.min), max: String(r.custom.max), step: '1', inputmode: 'numeric' });
      var widthNote = h('span', { class: 'wt-muted', id: 'wt-dev-width-note', text: t('From %min to %max pixels').replace('%min', r.custom.min).replace('%max', r.custom.max) });
      widthInput.setAttribute('aria-describedby', 'wt-dev-width-note');
      var addForm = h('form', { class: 'wt-dev-group wt-dev-add', novalidate: true, onsubmit: function (e) {
        e.preventDefault();
        var w = Devices.custom(widthInput.value, r.custom.min, r.custom.max);
        if (w === null) {
          widthInput.setAttribute('aria-invalid', 'true');
          widthNote.textContent = t('The width must be a whole number from %min to %max.').replace('%min', r.custom.min).replace('%max', r.custom.max);
          widthInput.focus();
          return;
        }
        widthInput.removeAttribute('aria-invalid');
        widthNote.textContent = t('From %min to %max pixels').replace('%min', r.custom.min).replace('%max', r.custom.max);
        if (widths.indexOf(w) < 0) { widths = widths.concat([w]).slice(-4); }
        /* a width added (or added again) is shown */
        choice = { shown: choice.shown, hidden: choice.hidden.filter(function (x) { return x !== w && widths.indexOf(x) >= 0; }) };
        widthInput.value = '';
        keep();
        draw();
        status.textContent = t('A frame %w pixels wide was added.').replace('%w', w);
      } }, [
        h('label', { class: 'wt-field' }, [h('span', { text: t('A width of your own') }), widthInput]),
        widthNote,
        h('button', { type: 'submit', class: 'wt-btn', text: t('Add the frame') })
      ]);
      var reload = h('button', { type: 'button', class: 'wt-btn', text: t('Load again'), onclick: function () { draw(); } });

      view.appendChild(h('div', { class: 'wt-split-head' }, [
        h('h2', { id: 'wt-dev-title', class: 'wt-split-title', text: t('Device preview') + ': ' + p.name }),
        h('button', { type: 'button', class: 'wt-btn is-icon', 'aria-label': t('Close'), title: t('Close'), onclick: close }, ['×'])
      ]));
      view.appendChild(h('div', { class: 'wt-dev-bar' }, [versions, picks, addForm, h('div', { class: 'wt-dev-group' }, [reload])]));
      if (!r.urls.draft) {
        view.appendChild(h('p', { class: 'wt-dev-note wt-muted', text: t('You have no draft of this page: the frames show the published page.') }));
      }
      view.appendChild(body);
      view.appendChild(status);

      var frames = [];
      /* A page loading in a frame may take the focus (the site's cookie notice focuses itself): while a frame loads and
         a moment after, the focus goes back to where it was in the dialog, so the toggles stay usable with the keyboard.
         A frame entered on purpose later (a click, Tab) keeps it. */
      var lastFocus = null;
      view.addEventListener('focusin', function (e) { if (e.target && e.target.tagName !== 'IFRAME') { lastFocus = e.target; } });
      function keepFocus(entry) {
        if (Date.now() > entry.until || !lastFocus || !lastFocus.isConnected || host.shadowRoot.activeElement !== entry.frame) { return; }
        try { lastFocus.focus({ preventScroll: true }); } catch (e) { /* gone */ }
      }
      /* light or dark in one frame: the design's attribute on that frame's page; where the design has none, its
         color-scheme follows the frame (the iframe's class) */
      function applyMode(entry) {
        var mode = modes[entry.key] || '';
        entry.frame.classList.toggle('is-light', mode === 'light');
        entry.frame.classList.toggle('is-dark', mode === 'dark');
        if (!mode) { return; }
        try {
          var root = entry.frame.contentDocument && entry.frame.contentDocument.documentElement;
          if (root) {
            root.setAttribute('data-exp-theme', mode);
            root.setAttribute('data-exp-theme-choice', mode);
          }
        } catch (e) { /* a page of another origin: its own mode stays */ }
      }
      function frameOf(d, custom) {
        var key = custom ? 'w' + d.width : d.id;
        /* the address is given when the frame is first shown (apply), so a hidden frame loads nothing */
        var frame = h('iframe', { class: 'wt-dev-frame', 'data-expwt-split': '', title: t('Preview on %device').replace('%device', d.name) });
        var entry = { key: key, device: d, frame: frame, item: custom ? { width: d.width } : { id: d.id }, loaded: false };
        frame.addEventListener('load', function () {
          applyMode(entry);
          entry.until = Date.now() + 2000;
          [0, 250, 750, 1500, 2000].forEach(function (ms) { window.setTimeout(function () { keepFocus(entry); }, ms); });
        });
        frame.addEventListener('focus', function () { keepFocus(entry); });
        var select = h('select', { 'aria-label': t('Light or dark') + ': ' + d.name, onchange: function () {
          var before = modes[key] || '';
          if (select.value) { modes[key] = select.value; } else { delete modes[key]; }
          keep();
          /* back to the site's own mode: the frame loads again, as the site has it */
          if (before && !select.value) { entry.until = Infinity; frame.src = Devices.src(r.urls, which); } else { applyMode(entry); }
        } }, [['', t('Like the site')], ['light', t('Light')], ['dark', t('Dark')]].map(function (o) {
          var opt = h('option', { value: o[0], text: o[1] });
          if ((modes[key] || '') === o[0]) { opt.setAttribute('selected', ''); }
          return opt;
        }));
        var caption = h('figcaption', { class: 'wt-dev-cap' }, [
          h('span', { class: 'wt-dev-name', text: d.name }),
          h('span', { class: 'wt-dev-size wt-muted', text: t('%w × %h').replace('%w', d.width).replace('%h', d.height) }),
          select,
          custom ? h('button', { type: 'button', class: 'wt-btn', 'aria-label': t('Remove the frame') + ': ' + d.name, text: t('Remove the frame'), onclick: function () {
            widths = widths.filter(function (w) { return w !== d.width; });
            choice = { shown: choice.shown, hidden: choice.hidden.filter(function (w) { return w !== d.width; }) };
            keep();
            draw();
          } }) : null
        ]);
        entry.figure = h('figure', { class: 'wt-dev-item', 'data-device': key }, [caption, h('div', { class: 'wt-dev-screen' }, [frame])]);
        return entry;
      }
      var empty = h('p', { class: 'wt-muted wt-dev-empty', text: t('Choose a device above.') });
      /* every frame there is (all devices and widths), then those not chosen hidden */
      function draw() {
        body.textContent = '';
        frames = r.devices.map(function (d) { return frameOf(d, false); })
          .concat(widths.map(function (w) { return frameOf({ id: 'w' + w, name: t('%w pixels wide').replace('%w', w), width: w, height: r.custom.height }, true); }));
        body.appendChild(empty);
        frames.forEach(function (f) { body.appendChild(f.figure); });
        drawToggles();
        apply();
      }
      /* the choice on the toggles and the frames: shown or hidden, a frame shown the first time loads; then the fit */
      function apply() {
        var on = Devices.visible(choice, ids, widths).map(function (item) { return item.width !== undefined ? 'w' + item.width : item.id; });
        Array.prototype.forEach.call(toggles.querySelectorAll('.wt-dev-toggle'), function (b) {
          b.setAttribute('aria-pressed', on.indexOf(b.getAttribute('data-key')) >= 0 ? 'true' : 'false');
        });
        frames.forEach(function (f) {
          var shownNow = on.indexOf(f.key) >= 0;
          f.figure.hidden = !shownNow;
          if (shownNow && !f.loaded) { f.loaded = true; f.until = Infinity; f.frame.src = Devices.src(r.urls, which); }
        });
        /* Show all stays where it is (and focusable) when everything is shown; it says so */
        var showAll = toggles.querySelector('.wt-dev-all');
        if (showAll) { showAll.setAttribute('aria-disabled', Devices.allShown(choice, ids, widths) ? 'true' : 'false'); }
        empty.hidden = on.length > 0;
        layout();
      }
      function layout(again) {
        var visible = frames.filter(function (f) { return !f.figure.hidden; });
        if (!visible.length || !body.clientWidth) { return; }
        var cs = window.getComputedStyle(body);
        var gap = parseFloat(cs.columnGap) || 0;
        var width = body.clientWidth - (parseFloat(cs.paddingLeft) || 0) - (parseFloat(cs.paddingRight) || 0);
        var cap = visible[0].figure.querySelector('.wt-dev-cap');
        var capHeight = cap ? cap.offsetHeight : 0;
        var bodyWidth = body.clientWidth;
        var height = body.clientHeight - (parseFloat(cs.paddingTop) || 0) - (parseFloat(cs.paddingBottom) || 0) - capHeight - gap;
        var fit = Devices.fit(visible.map(function (f) { return f.device; }), width, Math.max(160, height), gap);
        body.classList.toggle('is-row', fit.row);
        visible.forEach(function (f, i) {
          f.figure.style.setProperty('--wt-dw', String(f.device.width));
          f.figure.style.setProperty('--wt-dh', String(f.device.height));
          f.figure.style.setProperty('--wt-ds', String(fit.scales[i]));
        });
        /* a frame of another size can wrap its caption onto more or fewer lines (or bring or take away the body's
           scrollbar): measured once more with the new sizes */
        if (!again && ((cap ? cap.offsetHeight : 0) !== capHeight || body.clientWidth !== bodyWidth)) { layout(true); }
      }
      var observer = window.ResizeObserver ? new ResizeObserver(function () { layout(); }) : null;
      host.shadowRoot.appendChild(view);
      view.showModal();
      draw();
      if (observer) { observer.observe(body); }
      view.querySelector('.wt-split-head .wt-btn').focus();
    }, function (err) { failure(ctx, err); });
  }

  window.ExpwtMigrations = Migrations;
  window.ExpwtPerformance = Performance;
  window.ExpwtStatic = Static;
  window.ExpwtExport = Export;
  window.ExpwtHold = Hold;
  window.ExpwtSeo = SEO;
  window.ExpwtA11y = A11y;
  window.ExpwtComments = { actions: commentActions };
  window.ExpwtUrls = { address: addressOf };
  window.ExpwtCalendar = Calendar;
  window.ExpwtFocal = Focal;
  window.ExpwtDevices = Devices;
  window.ExpwtPanels = { page: page, create: create, structure: structure, seo: seo, sites: sites, forms: forms, newsletter: newsletter, urls: urls, calendar: calendar, export: exportPanel, insights: insights, inbox: inbox, migrations: migrations, static: staticPanel, performance: performance, me: me, shortcuts: shortcuts };
}());
