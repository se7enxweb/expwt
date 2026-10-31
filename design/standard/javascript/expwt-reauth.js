/* expwt/reauth: tells the sidebar in the window that opened this one that "Verify it's you" was answered, and closes. */
(function () {
  'use strict';
  var el = document.querySelector('[data-expwt-reauth]');
  if (!el) {
    return;
  }
  try {
    if (window.opener && window.opener !== window) {
      window.opener.postMessage({ expwt: 'reauth-done', action: el.getAttribute('data-expwt-reauth') }, window.location.origin);
    }
  } catch (e) {
    return;
  }
  setTimeout(function () { window.close(); }, 800);
}());
