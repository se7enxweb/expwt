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
    var inline = false;
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
    root.appendChild(h('div', { class: 'wt-section' }, [h('h3', { text: p.name }), chips]));

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

    var tools = h('div', { class: 'wt-row' }, [
      h('button', { type: 'button', class: 'wt-btn', 'aria-pressed': 'false', id: 'wt-inline', text: t('Edit on the page'), onclick: function (e) {
        inline = !inline;
        e.currentTarget.setAttribute('aria-pressed', inline ? 'true' : 'false');
        setInline(inline);
      } }),
      h('a', { class: 'wt-btn', href: p.urls.preview, target: '_blank', rel: 'noopener', text: t('Preview') }),
      h('a', { class: 'wt-btn', href: p.urls.fullEdit, text: t('Full editor') })
    ]);
    root.appendChild(tools);

    var form = h('form', { class: 'wt-section', novalidate: true, onsubmit: function (e) { e.preventDefault(); save(); } });
    var others = [];
    p.fields.forEach(function (f) {
      if (!f.editable) { others.push(f.name); return; }
      var id = 'wt-f-' + f.identifier;
      var input = control(ctx, f, id, function (v) { change(f.identifier, v, true); });
      inputs[f.identifier] = input;
      var row = h('label', { class: 'wt-field', for: id, 'data-field': f.identifier,
        onmouseenter: function () { highlight(f.identifier, true); }, onmouseleave: function () { highlight(f.identifier, false); } },
        [h('span', { text: f.name + (f.required ? ' (' + t('required') + ')' : '') }), input.el]);
      if (f.input === 'richtext' || f.input === 'checkbox' || f.input === 'multiselect') {
        row = h('div', { class: 'wt-field', 'data-field': f.identifier, role: 'group', 'aria-label': f.name }, [
          h('span', { id: id + '-label', text: f.name + (f.required ? ' (' + t('required') + ')' : '') }), input.el]);
      }
      rows[f.identifier] = row;
      form.appendChild(row);
    });
    if (others.length) {
      form.appendChild(h('p', { class: 'wt-muted', text: t('In the full editor: %fields').replace('%fields', others.join(', ')) }));
    }
    root.appendChild(form);

    var conflictBox = h('div', {});
    root.appendChild(conflictBox);

    var publishAt = h('input', { type: 'datetime-local', id: 'wt-publish-at' });
    var unpublishAt = h('input', { type: 'datetime-local', id: 'wt-unpublish-at' });
    root.appendChild(h('details', { class: 'wt-section' }, [
      h('summary', { text: t('Publish later or take down at a date') }),
      h('label', { class: 'wt-field', for: 'wt-publish-at' }, [h('span', { text: t('Publish at') }), publishAt]),
      h('label', { class: 'wt-field', for: 'wt-unpublish-at' }, [h('span', { text: t('Take down at') }), unpublishAt])
    ]));

    var saveBtn = h('button', { type: 'button', class: 'wt-btn', text: t('Save draft'), onclick: function () { save(); } });
    var publishBtn = h('button', { type: 'button', class: 'wt-btn is-primary', text: t('Publish'), onclick: function () { publish(); } });
    var discardBtn = h('button', { type: 'button', class: 'wt-btn is-danger', text: t('Discard draft'), onclick: function () { discard(); } });
    root.appendChild(h('div', { class: 'wt-row' }, [saveBtn, publishBtn, discardBtn]));
    discardBtn.hidden = !version;

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
      var first = Object.keys(dirtyFields()).length ? save() : Promise.resolve(version);
      first.then(function (v) {
        if (!v) {
          ctx.say(t('Change something first: there is no draft to publish.'));
          return null;
        }
        var input = { objectId: p.objectId, version: v };
        var at = fromInput('datetime', publishAt.value);
        var until = fromInput('datetime', unpublishAt.value);
        if (at) { input.publishAt = at; }
        if (until) { input.unpublishAt = until; }
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

    /* Editing on the page: the page's own text that shows a field's value becomes editable in place. */
    var placed = {};
    function findOnPage(f) {
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
        });
      });
      placed = {};
      ctx.inlineCss('');
      if (!on) { return; }
      var count = 0;
      p.fields.forEach(function (f) {
        if (!f.inline || !f.editable) { return; }
        var els = findOnPage(f);
        if (!els.length) { return; }
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
        + '[data-expwt-field]:focus{outline-style:solid}[data-expwt-hl]{outline:3px solid Highlight;outline-offset:2px}');
      ctx.say(count ? t('Click a text on the page to edit it. It is saved with the draft.') : t('None of the fields shows on this page as it is; use the form.'));
    }

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
      case 'richtext':
        var area = h('div', { class: 'wt-rich', id: id, contenteditable: 'true', role: 'textbox', 'aria-multiline': 'true', 'aria-labelledby': id + '-label',
          oninput: function () { onChange(area.innerHTML.trim()); } });
        area.innerHTML = f.value;
        var cmd = function (name, label, arg) {
          return h('button', { type: 'button', class: 'wt-btn', text: label, onmousedown: function (e) { e.preventDefault(); },
            onclick: function () {
              area.focus();
              var value = arg;
              if (name === 'createLink') { value = window.prompt(t('Address of the link')); if (!value) { return; } }
              document.execCommand(name, false, value);
              onChange(area.innerHTML.trim());
            } });
        };
        el = h('div', {}, [h('div', { class: 'wt-row', role: 'toolbar', 'aria-label': t('Text format') }, [
          cmd('bold', t('Bold')), cmd('italic', t('Italic')), cmd('insertUnorderedList', t('List')), cmd('createLink', t('Link'))
        ]), area]);
        set = function (v) { area.innerHTML = v; };
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
      default:
        var type = { number: 'number', decimal: 'number', email: 'email' }[f.input] || 'text';
        var inp = h('input', { type: type, id: id, value: f.value, step: f.input === 'decimal' ? 'any' : null,
          'aria-describedby': ['url', 'keywords', 'relations', 'tags'].indexOf(f.input) >= 0 ? id + '-hint' : null,
          oninput: function () { onChange(inp.value); } });
        el = ['url', 'keywords', 'relations', 'tags'].indexOf(f.input) >= 0
          ? h('div', {}, [inp, h('span', { class: 'wt-muted', id: id + '-hint', text: t('In the text form of the field (values parted by |).') })])
          : inp;
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
      var group = h('fieldset', { class: 'wt-section' }, [h('legend', { text: t('Where') })]);
      [['children', t('Below this page'), c.children.length], ['siblings', t('Beside this page'), c.siblings.length]].forEach(function (o) {
        if (!o[2]) { return; }
        group.appendChild(h('label', { class: 'wt-check' }, [
          h('input', { type: 'radio', name: 'wt-where', checked: where === o[0], onchange: function () { where = o[0]; fill(); } }), o[1]
        ]));
      });
      fill();
      root.appendChild(h('form', { onsubmit: function (e) {
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
      acts.appendChild(h('button', { type: 'button', class: 'wt-btn', text: t('Add location ...'), onclick: function () { picker(ctx, root, 'tree.addlocation', p); } }));
      if (p.can.remove) {
        acts.appendChild(h('button', { type: 'button', class: 'wt-btn is-danger', text: t('Move to trash'), onclick: function () {
          if (!window.confirm(t('Move this page to the trash?'))) { return; }
          ctx.call('tree.trash', { nodeId: ctx.node }).then(function (x) { window.location.href = x.parentUrl; }, function (e) { failure(ctx, e); });
        } }));
      }
      root.appendChild(h('div', { class: 'wt-section' }, [h('h3', { text: p.name }), acts]));
      root.appendChild(sortList(ctx, kids));
    }, function (err) { failure(ctx, err); });
  }

  function sortList(ctx, kids) {
    var h = ctx.h;
    var t = ctx.t;
    var box = h('div', { class: 'wt-section' }, [h('h3', { text: t('Pages below this one') })]);
    if (!kids.children.length) {
      box.appendChild(h('p', { class: 'wt-muted', text: t('There are no pages below this one.') }));
      return box;
    }
    var list = h('ul', { class: 'wt-list' });
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
            onclick: function () { move(i, i - 1); } }, ['↑']));
          li.appendChild(h('button', { type: 'button', class: 'wt-btn is-icon', 'aria-label': t('Move down') + ': ' + c.name, disabled: i === order.length - 1,
            onclick: function () { move(i, i + 1); } }, ['↓']));
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
      box.appendChild(h('div', { class: 'wt-row' }, [saveBtn]));
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
    var title = action === 'tree.move' ? t('Move "%name" to ...') : t('Add "%name" also below ...');
    var heading = h('h3', { text: title.replace('%name', p.name) });
    var list = h('ul', { class: 'wt-list' });
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
      if (!window.confirm((action === 'tree.move' ? t('Move the page below "%name"?') : t('Add the page also below "%name"?')).replace('%name', name))) { return; }
      ctx.call(action, { nodeId: ctx.node, parentId: parentId }).then(function () {
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

      var prefs = h('div', { class: 'wt-section' }, [h('h3', { text: t('The sidebar') })]);
      var radios = function (legend, name, options, value, apply) {
        var fs = h('fieldset', { class: 'wt-section' }, [h('legend', { text: legend })]);
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
      root.appendChild(h('div', { class: 'wt-row' }, [h('a', { class: 'wt-btn', href: m.logout, text: t('Sign out') })]));
    }, function (err) { failure(ctx, err); });
  }

  window.ExpwtPanels = { page: page, create: create, structure: structure, me: me };
}());
