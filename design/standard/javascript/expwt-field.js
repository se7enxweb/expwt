/*
 * expwt/field in the sidebar's frame: tells the sidebar (the parent window, same origin only) which draft the field
 * is in and, after a save, its new value; the frame's light or dark mode from ?theme=.
 */
(function () {
  'use strict';
  var html = document.documentElement;
  var theme = /[?&]theme=(light|dark)\b/.exec(window.location.search);
  if (theme) {
    html.classList.add('is-' + theme[1]);
  }
  var el = document.getElementById('expwt-field-message');
  var message = null;
  try { message = el ? JSON.parse(el.textContent) : null; } catch (e) { message = null; }
  if (message && window.parent && window.parent !== window) {
    window.parent.postMessage(message, window.location.origin);
  }
}());
