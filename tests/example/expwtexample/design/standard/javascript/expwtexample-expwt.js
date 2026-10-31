/*
 * expwtexample: the panel "Page cache" of the website sidebar (settings/expwt.ini.append.php, [Panel_pagecache]).
 * The sidebar loads this file when the panel is first shown; it registers the panel. fn( ctx, root ) gets what the
 * sidebar's own panels get: ctx.h (makes an element), ctx.t (the sidebar's strings), ctx.call (an action through the
 * installation's data layer, with the CSRF token and "Verify it's you"), ctx.say (the status line), ctx.node (the page),
 * ctx.registry(); root is the panel's body (inside the sidebar's Shadow DOM: the sidebar's classes and tokens apply).
 */
(function () {
  'use strict';
  window.expwtRegister('panel', 'pagecache', function (ctx, root) {
    var h = ctx.h;
    if (!ctx.node) {
      root.appendChild(h('p', { class: 'wt-muted', text: 'Open a page of the site.' }));
      return;
    }
    var facts = h('dl', { class: 'expwtexample-facts' });
    var picked = h('p', { class: 'wt-muted', role: 'status', 'aria-live': 'polite', text: 'Click a heading of the page.' });
    var clear = h('button', { type: 'button', class: 'wt-btn is-primary', text: 'Clear the cache of this page', onclick: function () {
      clear.disabled = true;
      ctx.call('expwtexample.clearcache', { nodeId: ctx.node }).then(function () {
        ctx.say('The cache of this page is cleared.', 'ok');
      }, function (err) { ctx.say(err.message, 'bad'); }).then(function () { clear.disabled = false; });
    } });
    root.appendChild(h('div', { class: 'wt-section' }, [facts, h('div', { class: 'wt-row' }, [clear]), picked]));
    ctx.call('expwtexample.info', { nodeId: ctx.node }).then(function (info) {
      [['Page', info.name], ['Node', info.nodeId], ['Object', info.objectId], ['Pages below', info.children],
       ['Changed', new Date(info.modified * 1000).toLocaleString()]].forEach(function (row) {
        facts.appendChild(h('dt', { text: row[0] }));
        facts.appendChild(h('dd', { text: String(row[1]) }));
      });
    }, function (err) { ctx.say(err.message, 'bad'); });
    /* a region of the page outlined for this panel ([Highlight_pagecache_headings]) was picked */
    root.addEventListener('expwt-pick', function (e) {
      picked.textContent = 'Picked: ' + e.detail.element.textContent.trim();
    });
  });
}());
