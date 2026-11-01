/*
 * The Export panel's pure parts (expwt-panels.js, ExpwtExport, E13): the print choices on <html>, the bytes of a
 * downloaded file, a link's lifetime in the user's language, and a link's state chip. Also checks that the page's
 * stylesheet (expwt.js) never prints the sidebar. Run with Node (no packages): node tests/js/export.test.js. Prints
 * PASS or FAIL lines and exits 1 on a failure.
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
var win = { atob: function (s) { return Buffer.from(s, 'base64').toString('binary'); } };
var sandbox = { window: win, document: {}, Intl: Intl, Date: Date, Uint8Array: Uint8Array };
vm.createContext(sandbox);
vm.runInContext(source, sandbox, { filename: 'expwt-panels.js' });
var E = win.ExpwtExport;
var failed = 0;

function same(name, got, want) {
  var ok = JSON.stringify(got) === JSON.stringify(want);
  if (!ok) { failed++; }
  console.log((ok ? 'PASS ' : 'FAIL ') + name + (ok ? '' : ': ' + JSON.stringify(got) + ' != ' + JSON.stringify(want)));
}

same('print, nothing chosen', E.printTokens(false, false), 'print');
same('print, only the content and the addresses', E.printTokens(true, true), 'print content urls');
same('print, the addresses', E.printTokens(false, true), 'print urls');
same('bytes of base64', Array.prototype.slice.call(E.bytes('UEsDBA==')), [80, 75, 3, 4]);
same('bytes of nothing', E.bytes('').length, 0);
same('an hour', E.lifetime(3600, 'en-US'), '1 hour');
same('a day', E.lifetime(86400, 'en-US'), '1 day');
same('three days', E.lifetime(259200, 'en-US'), '3 days');
same('German', E.lifetime(604800, 'de-DE'), '7 Tage');
same('ten minutes', E.lifetime(600, 'en-US'), '10 minutes');
same('active chip', E.state({ state: 'active' }), { cls: 'wt-chip is-ok', key: 'Works until %time' });
same('expired chip', E.state({ state: 'expired' }), { cls: 'wt-chip', key: 'Expired' });
same('withdrawn chip', E.state({ state: 'revoked' }), { cls: 'wt-chip', key: 'Withdrawn' });

var host = fs.readFileSync(path.join(__dirname, '../../design/standard/javascript/expwt.js'), 'utf8');
same('the sidebar and its spacers are never printed', /@media print\{exp-wt,\[data-expwt-spacer\]\{display:none\}/.test(host), true);
same('the push is undone in print', host.indexOf('html[data-expwt-push="right"],html[data-expwt-push="left"]{padding:0}') > 0, true);
same('the print rules are part of the page stylesheet', /\+ PRINT_RULES;/.test(host), true);

process.exit(failed ? 1 : 0);
