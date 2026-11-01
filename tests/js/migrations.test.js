/*
 * The Migrations panel's pure parts (expwt-panels.js, ExpwtMigrations, R4.5): the chip of a state, how many need
 * attention, a time of the report. Run with Node (no packages): node tests/js/migrations.test.js. Prints PASS or FAIL
 * lines and exits 1 on a failure.
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
var M = win.ExpwtMigrations;
var failed = 0;

function same(name, got, want) {
  var ok = JSON.stringify(got) === JSON.stringify(want);
  if (!ok) { failed++; }
  console.log((ok ? 'PASS ' : 'FAIL ') + name + (ok ? '' : ': ' + JSON.stringify(got) + ' != ' + JSON.stringify(want)));
}

same('failed is bad', M.chip('failed'), 'is-bad');
same('done and pushed are fine', [M.chip('done'), M.chip('pushed')], ['is-ok', 'is-ok']);
same('running and pending wait', [M.chip('running'), M.chip('pending')], ['is-warn', 'is-warn']);
same('anything else is plain', [M.chip('rolled-back'), M.chip('disabled'), M.chip(undefined)], ['', '', '']);
same('attention: pending, those not listed, failed, orphans',
  M.attention({ pending: [1, 2], more: 3, failed: [1], orphans: [1] }), 7);
same('attention of nothing', M.attention({}), 0);
same('no time is nothing', M.at(null, 'en-US'), '');
same('a time that is not one is shown as it is', M.at('soon', 'en-US'), 'soon');
same('a time in the language given', M.at('2026-10-31T10:00:00Z', 'en-US').indexOf('2026') >= 0, true);

process.exit(failed ? 1 : 0);
