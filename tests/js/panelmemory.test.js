/*
 * The panel pushed last is open again after a reload (expwt.js, ExpwtPanelMemory): the panel to open, a panel kept
 * while the list is not complete, what this device saved and the server never confirmed, and the state after the boot
 * answer. Run with Node (no packages): node tests/js/panelmemory.test.js (expwtPureTest runs it when Node is there).
 * Prints PASS or FAIL lines and exits 1 on a failure.
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
var win = { customElements: { get: function () { return undefined; }, define: function () {} } };
var sandbox = { window: win, document: {}, HTMLElement: function () {}, Document: function () {}, CSSStyleSheet: function () {}, Element: function () {} };
sandbox.HTMLElement.prototype = {};
vm.createContext(sandbox);
vm.runInContext(source, sandbox, { filename: 'expwt.js' });
var M = win.ExpwtPanelMemory;

var failed = 0;
function check(name, ok) {
  console.log((ok ? 'PASS ' : 'FAIL ') + name);
  if (!ok) { failed++; }
}

check('the helper is there', !!M);
var all = ['page', 'create', 'seo', 'insights', 'performance', 'me', 'shop'];
var before = ['page', 'sites', 'export', 'me'];

// pick: the panel asked for, else the first
check('pick: a panel the user has', M.pick(all, 'performance') === 'performance');
check('pick: an extension\'s panel the user has', M.pick(all, 'shop') === 'shop');
check('pick: a panel the user no longer has opens the first', M.pick(all, 'gone') === 'page');
check('pick: the first of another order', M.pick(['me', 'page'], 'gone') === 'me');
check('pick: an empty list opens page', M.pick([], 'me') === 'page');

// keep: before the boot answer a panel not in the list yet is kept
check('keep: before the boot answer Performance is kept', M.keep(before, 'performance', false) === 'performance');
check('keep: before the boot answer an extension\'s panel is kept', M.keep(before, 'shop', false) === 'shop');
check('keep: after the boot answer Performance is there', M.keep(all, 'performance', true) === 'performance');
check('keep: after the boot answer a panel the user does not have opens the first', M.keep(all, 'migrations', true) === 'page');

// unsent: what this device saved and the server never confirmed
var now = 1800000000000;
var st = { panel: 'insights', width: 400 };
check('unsent: nothing stored', M.unsent(null, 14, now) === null);
check('unsent: not an object', M.unsent('x', 14, now) === null);
check('unsent: no state', M.unsent({ user: 14, at: now }, 14, now) === null);
check('unsent: the same user, a moment ago', M.unsent({ user: 14, at: now - 300, state: st }, 14, now) === st);
check('unsent: saved before the user was known', M.unsent({ user: null, at: now - 300, state: st }, 14, now) === st);
check('unsent: another user', M.unsent({ user: 15, at: now - 300, state: st }, 14, now) === null);
check('unsent: too old (another device may have saved since)', M.unsent({ user: 14, at: now - M.UNSENT_FOR, state: st }, 14, now) === null);
check('unsent: from the future (a clock that went back)', M.unsent({ user: 14, at: now + 5000, state: st }, 14, now) === null);

// onBoot: the server's state, this device's unsent one over it, open from this device
var server = { panel: 'me', width: 380, open: false, theme: 'dark' };
var s = M.onBoot(server, null, true);
check('onBoot: the server\'s panel without an unsent change', s.panel === 'me' && s.theme === 'dark');
check('onBoot: open from this device', s.open === true);
s = M.onBoot(server, { panel: 'performance', width: 420, open: false, theme: 'dark' }, true);
check('onBoot: the unsent panel wins (a reload right after the click)', s.panel === 'performance' && s.width === 420);
check('onBoot: open from this device over the unsent one', s.open === true);
check('onBoot: the server\'s state is not changed', server.panel === 'me');
check('onBoot: no server state', M.onBoot(null, null, false).open === false);

console.log(failed ? 'FAIL ' + failed + ' failed' : 'PASS all');
process.exit(failed ? 1 : 0);
