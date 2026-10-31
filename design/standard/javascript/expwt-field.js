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
      if (b.closest(editorUi)) { return; }
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
  document.addEventListener("input", function () { touched = true; }, true);

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
      eds.forEach(function (ed) { if (css && ed.dom && ed.dom.loadCSS) { ed.dom.loadCSS(css); } });
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

  function save() {
    var b = form && form.querySelector('[name=StoreButton]');
    if (!b) { return; }
    if (initial !== null && !touched && snapshot() === initial) {
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
      save();
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

  /* eztags (its plain JavaScript editor in the frame, override.ini): a field with the frame's tag tree is built with
     eztags' Tree builder, so a tag chosen in the tree is added; set before eztags builds its fields */
  Array.prototype.forEach.call(document.querySelectorAll('.eztags-wrapper'), function (w) {
    var field = w.querySelector('[data-eztags]');
    if (field && w.querySelector('.ez-tags-tree-selector')) { field.setAttribute('data-builder', 'Tree'); }
  });

  /* what an edit template adds later (eztags' chips, its suggestions, its dialogs): the same look */
  function later(root) {
    style(root);
    Array.prototype.forEach.call(root.querySelectorAll('.js-tags-remove'), function (a) {
      a.classList.add('a4-chip-remove');
      a.setAttribute('role', 'button');
    });
  }
  if (window.MutationObserver && form) {
    var pending = false;
    new MutationObserver(function () {
      if (pending) { return; }
      pending = true;
      window.requestAnimationFrame(function () { pending = false; later(document); });
    }).observe(document.body, { childList: true, subtree: true });
  }

  style(document);
  later(document);
  menu();
  if (message) { post(message); }
  if (document.readyState === 'complete') { settle(); } else { window.addEventListener('load', settle); }
  if (window.ResizeObserver) { new ResizeObserver(size).observe(document.body); }
}());
