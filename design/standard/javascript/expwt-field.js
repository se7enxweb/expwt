/*
 * expwt/field in the sidebar's frame:
 * - the edit template's buttons and inputs get the sidebar's look (a4-btn, a4-field; expwt-field.css); the rich text
 *   editor's own toolbar keeps its own;
 * - the editor's options that are no main action (ezoe's engine switch, switching the editor off) go behind "More";
 * - the sidebar (the parent window, same origin only) is told which draft the field is in and, after a save, its new
 *   value; it asks the frame to save (its own Save draft) or to take a file (an image dropped on the page).
 */
(function () {
  'use strict';
  /* a page in the frame that is not the field's editor itself (its browse): the mode from the address */
  var html = document.documentElement;
  var mode = /[?&]theme=(light|dark)\b/.exec(window.location.search);
  if (mode && !html.classList.contains('is-light') && !html.classList.contains('is-dark')) { html.classList.add('is-' + mode[1]); }
  if (/[?&]in=sidebar\b/.test(window.location.search)) { html.classList.add('is-embedded'); }
  var el = document.getElementById('expwt-field-message');
  var message = null;
  try { message = el ? JSON.parse(el.textContent) : null; } catch (e) { message = null; }
  var strings = message && message.strings ? message.strings : {};
  var form = document.querySelector('.expwt-field-form');
  var editorUi = '.tox, .mce-tinymce, .mce-container, .oe-window, .exp-oe-window, .ProseMirror, [class*="tiptap-toolbar"], .exp-oe-tiptap-toolbar';

  function style(root) {
    Array.prototype.forEach.call(root.querySelectorAll('button, input[type=submit], input[type=button], input[type=reset], a.button'), function (b) {
      if (b.closest(editorUi) || b.matches('.a4-chip-remove, .expwt-chip-add')) { return; }
      b.classList.add('a4-btn');
      var name = b.getAttribute('name') || '';
      if (name === 'StoreButton' || b.classList.contains('defaultbutton')) { b.classList.add('is-primary'); }
      if (/remove|delete/i.test(name)) { b.classList.add('is-danger'); }
    });
    Array.prototype.forEach.call(root.querySelectorAll('input:not([type=hidden]):not([type=submit]):not([type=button]):not([type=reset]), select, textarea'), function (i) {
      if (i.closest(editorUi)) { return; }
      i.classList.add('a4-field');
      /* an edit template's input without a label of its own (xrowmetadata ...): the label of its block */
      if (!i.labels || !i.labels.length) {
        if (!i.getAttribute('aria-label') && !i.getAttribute('aria-labelledby')) {
          var block = i.closest('.block, fieldset, div');
          var text = block ? block.querySelector('label, legend') : null;
          var name = text && text.textContent.trim();
          if (name) { i.setAttribute('aria-label', name); }
        }
      }
    });
  }

  /* the editor's options behind "More": ezoe's engine switch and its "Disable editor" / "Enable editor" */
  function menu() {
    if (!form) { return; }
    var options = Array.prototype.filter.call(form.querySelectorAll('.ezoe-engine-switch, [name*="_disable_editor"], [name*="_enable_editor"]'), function (o) {
      return !o.closest(editorUi) && !(o.parentElement && o.parentElement.closest('.ezoe-engine-switch'));
    });
    if (!options.length) { return; }
    var details = document.createElement('details');
    details.className = 'a4-menu';
    var summary = document.createElement('summary');
    summary.className = 'a4-btn';
    summary.textContent = strings.More || 'More';
    var items = document.createElement('div');
    items.className = 'a4-menu-items';
    options.forEach(function (o) { items.appendChild(o); });
    details.appendChild(summary);
    details.appendChild(items);
    var tools = document.querySelector('.expwt-field-tools');
    (tools || form).appendChild(details);
  }

  function post(m) {
    if (window.parent && window.parent !== window) {
      window.parent.postMessage(m, window.location.origin);
    }
  }

  /* What the form would send: a save of a field nobody changed is left out (no draft made for nothing) */
  function snapshot() {
    if (!form || !window.FormData) { return null; }
    if (window.tinymce && window.tinymce.triggerSave) { window.tinymce.triggerSave(); }
    var parts = [];
    new FormData(form).forEach(function (v, k) {
      if (k === 'ezxform_token') { return; }
      parts.push(k + '=' + (typeof v === 'string' ? v : (v.name || '') + ':' + (v.size || 0)));
    });
    return parts.join('&');
  }
  var initial = null;
  /* typed into (an editor that writes its form field only when the form is sent, such as Tiptap): changed */
  var touched = false;
  document.addEventListener('input', function () { touched = true; changedSoon(); }, true);
  document.addEventListener('change', function () { changedSoon(); }, true);

  /* Lost work (F28): what was typed here is kept on this device until the server has it (localStorage, the key the
     sidebar's Page panel looks for: expwt.copyf.<object>.<language>.<field>), and the sidebar hears that this editor
     has changes (its autosave saves it once the focus has left the frame). A save removes the copy. */
  function copyKey() {
    return message ? 'expwt.copyf.' + message.objectId + '.' + message.language + '.' + message.identifier : null;
  }
  function keep(value) {
    var key = copyKey();
    if (!key) { return; }
    try {
      if (value === null) { window.localStorage.removeItem(key); } else { window.localStorage.setItem(key, JSON.stringify(value)); }
    } catch (e) { /* no storage on this device (private mode, full): the server's draft only */ }
  }
  /* the form's values as they would be sent, without the token and files */
  function entries() {
    if (!form || !window.FormData) { return []; }
    if (window.tinymce && window.tinymce.triggerSave) { window.tinymce.triggerSave(); }
    var out = [];
    new FormData(form).forEach(function (v, k) {
      if (k !== 'ezxform_token' && typeof v === 'string') { out.push([k, v]); }
    });
    return out;
  }
  var changeTimer = null;
  function changedSoon() {
    if (initial === null || !message) { return; }
    clearTimeout(changeTimer);
    changeTimer = setTimeout(function () {
      var now = snapshot();
      var changed = now !== null && now !== initial;
      keep(changed ? { at: Date.now(), entries: entries() } : null);
      post({ expwt: 'field-changed', changed: changed, identifier: message.identifier, objectId: message.objectId });
    }, 600);
  }
  if (message && message.expwt === 'field-saved') { keep(null); }

  /* the copy put back: sent as this form would send it (the field's own inputs, not the editors' state), and saved */
  function restore() {
    var key = copyKey();
    var copy = null;
    try { copy = key ? JSON.parse(window.localStorage.getItem(key) || 'null') : null; } catch (e) { copy = null; }
    if (!copy || !copy.entries || !form) { return; }
    var send = document.createElement('form');
    send.method = 'post';
    send.action = form.action;
    send.hidden = true;
    var add = function (name, value) {
      var box = document.createElement('textarea');
      box.name = name;
      box.value = value;
      send.appendChild(box);
    };
    copy.entries.forEach(function (pair) { add(pair[0], pair[1]); });
    var token = form.querySelector('[name=ezxform_token]');
    if (token) { add('ezxform_token', token.value); }
    add('StoreButton', '1');
    document.body.appendChild(send);
    send.submit();
  }

  /* the rich text editors of the frame: TinyMCE 3 lists them in tinymce.editors, TinyMCE 6 and later in tinymce.get() */
  function editors() {
    var t = window.tinymce;
    if (!t) { return []; }
    if (t.editors && typeof t.editors === 'object') {
      var list = Array.isArray(t.editors) ? t.editors : Object.keys(t.editors).map(function (k) { return t.editors[k]; });
      list = list.filter(function (ed, i) { return ed && ed.id && list.indexOf(ed) === i; });
      if (list.length) { return list; }
    }
    var all = t.get ? t.get() : null;
    return Array.isArray(all) ? all : [];
  }

  /* the rich text editors (TinyMCE) started: their content keeps to the frame's width (expwt-field-content.css in
     their own document), and what they hold now counts as unchanged */
  function settle() {
    var tries = 0;
    (function wait() {
      var eds = editors();
      var ready = !window.tinymce || (eds.length && eds.every(function (ed) { return ed.initialized; }));
      if (!ready && tries++ < 60) { setTimeout(wait, 100); return; }
      var css = document.documentElement.getAttribute('data-expwt-content-css');
      eds.forEach(function (ed) {
        if (css && ed.dom && ed.dom.loadCSS) { ed.dom.loadCSS(css); }
        /* typing in the rich text editor happens in its own document: its events count as typed here (F28) */
        if (ed.on) { ed.on('input change undo redo ExecCommand', changedSoon); }
      });
      setTimeout(function () { initial = snapshot(); size(); }, 300);
    }());
  }

  /* the frame's height, for the sidebar to give the frame (its height attribute) */
  var sizing = false;
  function size() {
    if (sizing || !message) { return; }
    sizing = true;
    window.requestAnimationFrame(function () {
      sizing = false;
      post({ expwt: 'field-size', identifier: message.identifier, objectId: message.objectId,
             height: Math.ceil(document.documentElement.scrollHeight) });
    });
  }

  /* auto: the sidebar's autosave, which saves only what the form would send differently */
  function save(auto) {
    var b = form && form.querySelector('[name=StoreButton]');
    if (!b) { return; }
    if (initial !== null && (!touched || auto) && snapshot() === initial) {
      post({ expwt: 'field-unchanged', identifier: message && message.identifier, objectId: message && message.objectId });
      return;
    }
    /* the rich text editors write their text into the form's textarea on submit */
    if (window.tinymce && window.tinymce.triggerSave) { window.tinymce.triggerSave(); }
    if (form.requestSubmit) { form.requestSubmit(b); } else { b.click(); }
  }

  window.addEventListener('message', function (e) {
    if (e.origin !== window.location.origin || e.source !== window.parent || !e.data) { return; }
    if (e.data.expwt === 'field-save') {
      save(!!e.data.auto);
    } else if (e.data.expwt === 'field-restore') {
      restore();
    } else if (e.data.expwt === 'field-file' && e.data.file) {
      /* an image dropped on the page: into the datatype's own file input, then saved as Save draft would */
      var input = form && form.querySelector('input[type=file]');
      if (!input || !window.DataTransfer) {
        post({ expwt: 'field-file-refused', identifier: message && message.identifier, objectId: message && message.objectId });
        return;
      }
      var list = new DataTransfer();
      list.items.add(e.data.file);
      input.files = list.files;
      save();
    }
  });

  /* An object relation list (the kernel's edit template, an admin table with selection boxes and an order column):
     a compact list in the sidebar's model instead, each related object a row with its name, its type as a muted tag,
     up and down for the order and a remove button. The datatype's own inputs stay in the rows (the relation ids,
     their order, the selection box), and its own buttons do the work: remove checks the row's box and sends the
     datatype's "Remove selected"; "Add existing objects" browses as before. */
  function relations() {
    Array.prototype.forEach.call(document.querySelectorAll('[id^="ezobjectrelationlist_browse_"]'), function (block) {
      var table = block.querySelector('table.list');
      var removeAll = block.querySelector('[name$="_remove_objects]"]');
      Array.prototype.forEach.call(block.querySelectorAll('.ezobject-relation-search-text, .ezobject-relation-search-btn, .ezobject-relation-search-browse'), function (el) {
        el.hidden = true;
      });
      if (removeAll && removeAll.parentElement) { removeAll.parentElement.hidden = true; }
      if (!table || !table.tBodies.length) { return; }
      /* no related object: the table holds only the datatype's row template (--name--, "no_relation", which is
         sent); hidden, with "There are no related objects." below it */
      if (block.querySelector('.ezobject-relation-no-relation')) { table.hidden = true; return; }
      var list = document.createElement('ul');
      list.className = 'a4-rel-list';
      var renumber = function () {
        Array.prototype.forEach.call(list.children, function (li, i) {
          var prio = li.querySelector('[name*="_priority"]');
          if (prio) { prio.value = String(i + 1); }
          li.querySelector('.a4-rel-up').disabled = i === 0;
          li.querySelector('.a4-rel-down').disabled = i === list.children.length - 1;
        });
      };
      var button = function (cls, text, label, onclick) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'a4-btn is-icon ' + cls;
        b.textContent = text;
        b.setAttribute('aria-label', label);
        b.title = label;
        b.addEventListener('click', onclick);
        return b;
      };
      Array.prototype.forEach.call(table.tBodies[0].rows, function (tr) {
        var cells = tr.cells;
        if (cells.length < 3) { return; }
        var name = cells[1].textContent.trim();
        var type = cells[2].textContent.trim();
        var li = document.createElement('li');
        var label = document.createElement('span');
        label.className = 'a4-rel-name';
        label.textContent = name;
        var tag = document.createElement('span');
        tag.className = 'a4-tag';
        tag.textContent = type;
        li.appendChild(label);
        li.appendChild(tag);
        Array.prototype.forEach.call(tr.querySelectorAll('input'), function (input) {
          if (input.type === 'checkbox') { input.hidden = true; }
          if (/_priority/.test(input.name)) { input.type = 'hidden'; }
          li.appendChild(input);
        });
        li.appendChild(button('a4-rel-up', '↑', '↑ ' + name, function () {
          if (li.previousElementSibling) { list.insertBefore(li, li.previousElementSibling); renumber(); }
        }));
        li.appendChild(button('a4-rel-down', '↓', '↓ ' + name, function () {
          if (li.nextElementSibling) { list.insertBefore(li.nextElementSibling, li); renumber(); }
        }));
        if (removeAll) {
          li.appendChild(button('a4-rel-remove', '×', (removeAll.value || '×') + ': ' + name, function () {
            Array.prototype.forEach.call(block.querySelectorAll('input[type=checkbox][name^="_selection"], input[type=checkbox][name*="_selection"]'), function (c) { c.checked = false; });
            var box = li.querySelector('input[type=checkbox]');
            if (box) { box.checked = true; }
            removeAll.click();
          }));
        }
        list.appendChild(li);
      });
      table.parentNode.replaceChild(list, table);
      renumber();
    });
  }
  relations();

  /* a table of inputs (authors, a matrix, options ...) or with a row of column names (the current image: preview,
     file name, MIME type, size) wider than the frame: one card per row, each cell with its column's name (also the
     input's name for screen readers); a table again once the frame is wide enough. A table in the text being
     edited (a rich text editor's own content) stays a table. */
  function rows() {
    Array.prototype.forEach.call(document.querySelectorAll('.expwt-field-body table'), function (table) {
      if (table.closest('[contenteditable], .ProseMirror')) { return; }
      var head = table.tHead ? table.tHead.rows[0] : (table.rows[0] && table.rows[0].querySelector('th') ? table.rows[0] : null);
      var inputs = table.querySelector('tbody input:not([type=hidden]):not([type=checkbox]), tbody select, tbody textarea');
      if (!inputs && !(head && head.cells.length > 1 && table.rows.length > 1)) { return; }
      if (head && !table.hasAttribute('data-a4-labelled')) {
        table.setAttribute('data-a4-labelled', '');
        Array.prototype.forEach.call(table.rows, function (tr) {
          if (tr === head) { tr.classList.add('a4-rows-head'); return; }
          Array.prototype.forEach.call(tr.cells, function (td, i) {
            var th = head.cells[i], name = th ? th.textContent.trim() : '';
            if (!name) { return; }
            td.setAttribute('data-a4-label', name);
            Array.prototype.forEach.call(td.querySelectorAll('input:not([type=hidden]), select, textarea'), function (i) {
              if (!i.labels || !i.labels.length) { if (!i.getAttribute('aria-label')) { i.setAttribute('aria-label', name); } }
            });
          });
        });
      }
      table.classList.remove('a4-rows');
      /* in the sidebar's frame a table without inputs (the current image) is always the card: its columns would
         only just fit the panel's width, and not after a longer file name */
      var card = !inputs && document.documentElement.classList.contains('is-embedded');
      if (card || table.scrollWidth > table.clientWidth + 1) { table.classList.add('a4-rows'); }
    });
  }
  rows();
  var rowsPending = false;
  window.addEventListener('resize', function () {
    if (rowsPending) { return; }
    rowsPending = true;
    window.requestAnimationFrame(function () { rowsPending = false; rows(); });
  });

  /* eztags (expwt/eztags_edit.tpl in the frame) enables "Add new" and searches on keyup only: a name pasted or
     dictated into its search counts as typed */
  document.addEventListener('input', function (e) {
    if (e.target && e.target.classList && e.target.classList.contains('tags-input-field') && !e.isComposing) {
      e.target.dispatchEvent(new KeyboardEvent('keyup', { bubbles: true }));
    }
  });

  /* what an edit template adds later (eztags' chips, its suggestions, its dialogs): the same look */
  function later(root) {
    style(root);
  }
  if (window.MutationObserver && form) {
    var pending = false;
    new MutationObserver(function () {
      if (pending) { return; }
      pending = true;
      window.requestAnimationFrame(function () { pending = false; later(document); });
      /* a picker that writes hidden inputs (eztags' chips) changes the form without an input event */
      changedSoon();
    }).observe(document.body, { childList: true, subtree: true });
  }

  style(document);
  later(document);
  menu();
  if (message) { post(message); }
  if (document.readyState === 'complete') { settle(); } else { window.addEventListener('load', settle); }
  if (window.ResizeObserver) { new ResizeObserver(size).observe(document.body); }
}());
