/* expwt/actions: the questions of the actions a site design includes (design:parts/expwt_edit_actions.tpl). A button
   with data-expwt-confirm submits only when the question is answered yes. One listener on the document, so it serves
   every form of the page and the file may be loaded more than once; no inline script (Content-Security-Policy). */
(function () {
  'use strict';
  if (window.expwtActionsReady) {
    return;
  }
  window.expwtActionsReady = true;
  document.addEventListener('click', function (event) {
    var target = event.target;
    var button = target && target.closest ? target.closest('[data-expwt-confirm]') : null;
    if (!button || button.disabled) {
      return;
    }
    if (!window.confirm(button.getAttribute('data-expwt-confirm'))) {
      event.preventDefault();
    }
  });
}());
