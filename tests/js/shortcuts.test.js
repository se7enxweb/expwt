/*
 * The sidebar's shortcuts (F30): defaults, the user's own keys, the checks (one letter, not the browser's or the
 * system's, not one another shortcut has), the page's access keys and the text fields on a Mac (expwt.js, ExpwtKeys).
 * Run with Node (no packages): node tests/js/shortcuts.test.js (expwtPureTest runs it when Node is there).
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
var K = win.ExpwtKeys;

var failed = 0;
function check(name, ok) {
  console.log((ok ? 'PASS ' : 'FAIL ') + name);
  if (!ok) { failed++; }
}
function key(code, extra) { return Object.assign({ altKey: true, shiftKey: true, ctrlKey: false, metaKey: false, code: code }, extra || {}); }

var map = K.map('E', {});
check('defaults: E open, D save, K edit on the page, H the shortcuts', map.open === 'E' && map.save === 'D' && map.inline === 'K' && map.help === 'H');
check('no default is reserved', Object.keys(map).every(function (a) { return K.RESERVED.indexOf(map[a]) < 0; }));
check('the setting ShortcutKey moves open', K.map('W', {}).open === 'W');
check('a user\'s own key wins', K.map('E', { save: 'q' }).save === 'Q');
check('a reserved own key is ignored', K.map('E', { save: 'S' }).save === 'D');
check('an own key another shortcut has is ignored', K.map('E', { save: 'E' }).save === 'D');
check('an unknown action is ignored', K.map('E', { publish: 'P' }).publish === undefined);

check('check: one letter', K.check('save', '7', map) === 'format' && K.check('save', 'QQ', map) === 'format' && K.check('save', '', map) === 'format');
['A', 'B', 'I', 'L', 'M', 'N', 'S', 'T'].forEach(function (r) {
  check('check: Alt+Shift+' + r + ' is the browser\'s or the system\'s', K.check('save', r, map) === 'reserved');
});
check('check: names the shortcut that has the key', K.check('save', 'h', map) === 'help');
check('check: its own key is fine', K.check('save', 'D', map) === '');
check('check: a free letter is fine', K.check('save', 'q', map) === '');

check('match: Alt+Shift+E opens', K.match(key('KeyE'), map, [], false) === 'open');
check('match: Alt+Shift+D saves', K.match(key('KeyD'), map, [], false) === 'save');
check('match: Alt+Shift+3 is the third panel', K.match(key('Digit3'), map, [], false) === 'panel:3');
check('match: Alt+Shift+0 is nothing', K.match(key('Digit0'), map, [], false) === null);
check('match: without Shift nothing (Alt opens the browser\'s menus)', K.match(key('KeyE', { shiftKey: false }), map, [], false) === null);
check('match: with Ctrl nothing (Ctrl+Alt is AltGr)', K.match(key('KeyE', { ctrlKey: true }), map, [], false) === null);
check('match: with Cmd nothing', K.match(key('KeyE', { metaKey: true }), map, [], false) === null);
check('match: a free letter is nothing', K.match(key('KeyQ'), map, [], false) === null);
check('match: a key the page gives a link stays the page\'s', K.match(key('KeyD'), map, ['D'], false) === null);
var field = { matches: function () { return true; } };
check('match: on a Mac a text field gets Option+Shift+letter', K.match(key('KeyD', { target: field }), map, [], true) === null);
check('match: elsewhere a text field does not', K.match(key('KeyD', { target: field }), map, [], false) === 'save');

var doc = { querySelectorAll: function () { return [
  { getAttribute: function () { return 'd'; }, closest: function () { return null; } },
  { getAttribute: function () { return 'x y'; }, closest: function () { return null; } },
  { getAttribute: function () { return 'k'; }, closest: function () { return {}; } }
]; } };
var page = K.pageKeys(doc);
check('the page\'s access keys, upper case, each of a list', page.indexOf('D') >= 0 && page.indexOf('X') >= 0 && page.indexOf('Y') >= 0);
check('an access key inside the sidebar is not the page\'s', page.indexOf('K') < 0);

process.exit(failed ? 1 : 0);
