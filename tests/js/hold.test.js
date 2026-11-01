/*
 * The review hold's pure part (expwt-panels.js, ExpwtHold, E14): what the Page panel's hold box says and offers for
 * a page without the kernel's state group, a page not held, a held page the user may or may not edit, and a hold
 * placed in the administration (no who and why). Run with Node (no packages): node tests/js/hold.test.js. Prints PASS
 * or FAIL lines and exits 1 on a failure.
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
var H = win.ExpwtHold;
var failed = 0;
var t = function (s) { return s; };
var when = function (ts) { return 'T' + ts; };

function same(name, got, want) {
  var ok = JSON.stringify(got) === JSON.stringify(want);
  if (!ok) { failed++; }
  console.log((ok ? 'PASS ' : 'FAIL ') + name + (ok ? '' : ': ' + JSON.stringify(got) + ' != ' + JSON.stringify(want)));
}

function hold(change) {
  return Object.assign({ held: false, canPlace: false, canRelease: false, editable: true, by: '', since: 0, reason: '' }, change || {});
}

var missing = H.view({ hold: null, holdMissing: true }, t, when);
same('no state group: kind', missing.kind, 'missing');
same('no state group: nothing to do', missing.action, '');
same('no state group: names the upgrade step', /6\.0\.15-review-hold/.test(missing.lines[0]), true);

same('not held, may not place', H.view({ hold: hold() }, t, when), { kind: 'free', lines: [], action: '', reason: null });
same('not held, may place: reason required', H.view({ hold: hold({ canPlace: true }) }, t, when),
  { kind: 'free', lines: [], action: 'place', reason: { required: true } });

var held = H.view({ hold: hold({ held: true, canRelease: true, editable: false, by: 'Rita', since: 99, reason: 'Claims' }) }, t, when);
same('held: who, why, what it means', held.lines, ['Held by Rita since T99.', 'Reason: Claims', 'Nobody edits or publishes this page until a reviewer releases it.']);
same('held: release, reason optional', [held.action, held.reason], ['release', { required: false }]);

var admin = H.view({ hold: hold({ held: true, editable: true }) }, t, when);
same('held in the administration, user still edits', admin.lines, ['You may still edit it, but publishing waits until it is released, unless your role allows it.']);
same('held, may not release', admin.action, '');

process.exit(failed ? 1 : 0);
