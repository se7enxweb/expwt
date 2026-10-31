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

  function save() {
    var b = form && form.querySelector('[name=StoreButton]');
    if (!b) { return; }
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

  style(document);
  menu();
  if (message) { post(message); }
}());
