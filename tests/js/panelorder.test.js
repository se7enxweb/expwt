/*
 * The panel order and visibility of the user's roles (expwt.js, ExpwtPanelOrder, E9): the rule of the boot answer
 * applied to the panels the user gets, as expwtRolePanels::apply() does on the server. Run with Node (no packages):
 * node tests/js/panelorder.test.js. Prints PASS or FAIL lines and exits 1 on a failure.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */
'use strict';
var fs = require('fs');
var path = require('path');
var vm = require('vm');

var win = { customElements: { get: function () { return undefined; }, define: function () {} } };
var sandbox = { window: win, document: {}, HTMLElement: function () {}, Document: function () {}, CSSStyleSheet: function () {}, Element: function () {} };
sandbox.HTMLElement.prototype = {};
vm.createContext(sandbox);
vm.runInContext(fs.readFileSync(path.join(__dirname, '../../design/standard/javascript/expwt.js'), 'utf8'), sandbox, { filename: 'expwt.js' });
var O = win.ExpwtPanelOrder;

var failed = 0;
function same(name, got, want) {
  var ok = JSON.stringify(got) === JSON.stringify(want);
  if (!ok) { failed++; }
  console.log((ok ? 'PASS ' : 'FAIL ') + name + (ok ? '' : ': ' + JSON.stringify(got) + ' != ' + JSON.stringify(want)));
}
function list(ids) { return ids.map(function (id) { return { id: id, label: id.toUpperCase() }; }); }
function ids(l) { return l.map(function (p) { return p.id; }); }

var panels = list(['page', 'create', 'seo', 'calendar', 'deploys', 'me']);
same('no rule: the sidebar\'s own order', ids(O.apply(panels, null)), ['page', 'create', 'seo', 'calendar', 'deploys', 'me']);
same('a broken rule is ignored', ids(O.apply(panels, { order: 'x' })), ['page', 'create', 'seo', 'calendar', 'deploys', 'me']);
same('ordered first, the rest in the sidebar\'s order, hidden left out',
  ids(O.apply(panels, { order: ['calendar', 'gone', 'me', 'page'], hidden: ['seo', 'deploys'] })), ['calendar', 'me', 'page', 'create']);
same('Page and Me are never hidden', ids(O.apply(panels, { order: [], hidden: ['page', 'me', 'create'] })), ['page', 'seo', 'calendar', 'deploys', 'me']);
same('never empty', ids(O.apply(list(['seo']), { order: [], hidden: ['seo'] })), ['seo']);
same('the labels stay', O.apply(panels, { order: ['seo'], hidden: [] })[0], { id: 'seo', label: 'SEO' });
same('a panel named twice in the order once', ids(O.apply(panels, { order: ['seo', 'seo'], hidden: [] })).length, 6);

process.exit(failed ? 1 : 0);
