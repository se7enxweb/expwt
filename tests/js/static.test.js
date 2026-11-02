/*
 * The Static panel's pure parts (expwt-panels.js, ExpwtStatic, S6): the page's state on a target and the last publish
 * in the kernel's words. Run with Node (no packages): node tests/js/static.test.js. Prints PASS or FAIL lines and
 * exits 1 on a failure.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */
'use strict';
var fs = require('fs');
var path = require('path');
var vm = require('vm');

var source = fs.readFileSync(path.join(__dirname, '../../design/standard/javascript/expwt-panels.js'), 'utf8');
var win = {};
var sandbox = { window: win, document: {}, Intl: Intl, Date: Date };
vm.createContext(sandbox);
vm.runInContext(source, sandbox, { filename: 'expwt-panels.js' });
var S = win.ExpwtStatic;
var L = { yes: 'Ja', no: 'Nein', missing: 'Nicht im Build.', never: 'nie', failed: 'fehlgeschlagen', unchanged: 'nichts geändert', pushed: 'gepusht', notPushed: 'nicht gepusht' };
var failed = 0;

function same(name, got, want) {
  var ok = JSON.stringify(got) === JSON.stringify(want);
  if (!ok) { failed++; }
  console.log((ok ? 'PASS ' : 'FAIL ') + name + (ok ? '' : ': ' + JSON.stringify(got) + ' != ' + JSON.stringify(want)));
}

same('a stale page warns', S.page('stale', L), { text: 'Ja', chip: 'is-warn' });
same('a current page is fine', S.page('current', L), { text: 'Nein', chip: 'is-ok' });
same('a page not in the build is plain', [S.page('missing', L), S.page(undefined, L)], [{ text: 'Nicht im Build.', chip: '' }, { text: 'Nicht im Build.', chip: '' }]);
same('never published', S.last(null, L), { text: 'nie', chip: '' });
same('the states of a publish', ['failed', 'unchanged', 'pushed', 'not-pushed'].map(function (s) { return S.last({ state: s, at: 1 }, L).chip; }), ['is-bad', '', 'is-ok', 'is-warn']);
same('an unknown state is shown as it is', S.last({ state: 'odd' }, L), { text: 'odd', chip: '' });
same('placeholders filled', S.fill('Auftrag %id von %user', { '%id': '20261101-120000-a1b2c3', '%user': 'editor' }), 'Auftrag 20261101-120000-a1b2c3 von editor');

process.exit(failed ? 1 : 0);
