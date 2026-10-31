/*
 * Editing on the page is a state of its own (expwt.js, ExpwtEditState): never tied to whether the panel is open,
 * kept per browser tab in sessionStorage. Run with Node (no packages): node tests/js/editstate.test.js
 * (expwtPureTest runs it when Node is there). Prints PASS or FAIL lines and exits 1 on a failure.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */
'use strict';
var fs = require('fs');
var path = require('path');
var vm = require('vm');

var source = fs.readFileSync(path.join(__dirname, '../../design/standard/javascript/expwt.js'), 'utf8');
function Storage() { this.items = {}; }
Storage.prototype.getItem = function (k) { return Object.prototype.hasOwnProperty.call(this.items, k) ? this.items[k] : null; };
Storage.prototype.setItem = function (k, v) { this.items[k] = String(v); };
Storage.prototype.removeItem = function (k) { delete this.items[k]; };
var win = {
  customElements: { get: function () { return undefined; }, define: function () {} },
  sessionStorage: new Storage(), localStorage: new Storage()
};
var sandbox = { window: win, document: {}, HTMLElement: function () {}, Document: function () {}, CSSStyleSheet: function () {}, Element: function () {} };
sandbox.HTMLElement.prototype = {};
vm.createContext(sandbox);
vm.runInContext(source, sandbox, { filename: 'expwt.js' });
var S = win.ExpwtEditState;

var failed = 0;
function check(name, ok) {
  console.log((ok ? 'PASS ' : 'FAIL ') + name);
  if (!ok) { failed++; }
}

check('the helper is there', !!S);
var store = new Storage();
check('off when nothing is stored', S.read(store) === false);
S.write(store, true);
check('on after it was turned on', S.read(store) === true);
check('kept in sessionStorage under expwt.editing', store.getItem('expwt.editing') === '1');
S.write(store, false);
check('off again after Done', S.read(store) === false && store.getItem('expwt.editing') === null);
check('a storage that throws reads as off', S.read({ getItem: function () { throw new Error('denied'); } }) === false);
check('a storage that throws still turns it on for the page', S.write({ setItem: function () { throw new Error('denied'); } }, true) === true);

/* the state does not depend on the panel: open, closed, push or overlay */
[true, false].forEach(function (open) {
  [true, false].forEach(function (overlay) {
    check('editing stays on with open=' + open + ' overlay=' + overlay, S.write(store, true) && S.read(store) === true);
  });
});
check('pill: editing, panel closed', S.pillShown(true, false, false) === true);
check('pill: none over the open overlay (a phone; the panel says it)', S.pillShown(true, true, true) === false);
check('pill: none while the panel is beside the page', S.pillShown(true, true, false) === false);
check('pill: none when not editing', S.pillShown(false, false, true) === false);
check('sheet: on a phone (overlay)', S.useSheet(true, true) === true && S.useSheet(false, true) === true);
check('sheet: with the panel closed', S.useSheet(false, false) === true);
check('sheet: not while the panel is beside the page', S.useSheet(true, false) === false);
check('bottom room: a cookie bar 120px high', S.bottomRoom(800, [680]) === 120);
check('bottom room: the higher of the cookie bar and the debug bar', S.bottomRoom(800, [760, 680]) === 120);
check('bottom room: a tall cookie dialog from 400px', S.bottomRoom(800, [400, 760]) === 400);
check('bottom room: a fixed header at the top does not count', S.bottomRoom(800, [0, 40]) === 0);
check('bottom room: nothing fixed', S.bottomRoom(800, []) === 0);

console.log(failed ? 'FAIL ' + failed : 'PASS editing on the page is its own state');
process.exit(failed ? 1 : 0);
