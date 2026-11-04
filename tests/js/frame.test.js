/*
 * The sidebar starts on the top page only (expwt.js, ExpwtFrame): not in the administration's version and layout
 * previews, the split view or the device preview, and not where the top page cannot be read. Run with Node
 * (no packages): node tests/js/frame.test.js. Prints PASS or FAIL lines and exits 1 on a failure.
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
var F = win.ExpwtFrame;

var failed = 0;
function same(name, got, want) {
  var ok = got === want;
  if (!ok) { failed++; }
  console.log((ok ? 'PASS ' : 'FAIL ') + name + (ok ? '' : ': ' + got + ' != ' + want));
}

var top = {};
top.self = top;
top.top = top;
same('the top page: the sidebar starts', F.inside(top), false);

var framed = { top: top };
framed.self = framed;
same('a page in a frame (an admin preview): no sidebar', F.inside(framed), true);

var foreign = { self: null };
foreign.self = foreign;
Object.defineProperty(foreign, 'top', { get: function () { throw new Error('SecurityError'); } });
same('a top page of another origin: no sidebar', F.inside(foreign), true);

if (failed) { process.exit(1); }
