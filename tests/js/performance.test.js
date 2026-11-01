/*
 * The Performance panel's pure parts (expwt-panels.js, ExpwtPerformance, E12): whether a page came from a cache (its
 * render older than its load, whatever the device's clock), times and memory as the panel writes them, and a block's
 * share of the slowest one. Run with Node (no packages): node tests/js/performance.test.js. Prints PASS or FAIL lines
 * and exits 1 on a failure.
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
var P = win.ExpwtPerformance;
var failed = 0;

function same(name, got, want) {
  var ok = JSON.stringify(got) === JSON.stringify(want);
  if (!ok) { failed++; }
  console.log((ok ? 'PASS ' : 'FAIL ') + name + (ok ? '' : ': ' + JSON.stringify(got) + ' != ' + JSON.stringify(want)));
}

var server = 1800000000000;
/* the device's clock 5 minutes behind the server's */
var device = server - 300000;
same('rendered for this view: after the load began', P.cached({ at: server - 400 }, server, device, device - 1000), false);
same('rendered just before the load began counts as this view (slack)', P.cached({ at: server - 2500 }, server, device, device - 1000), false);
same('a render an hour old came from a cache', P.cached({ at: server - 3600000 }, server, device, device - 1000), true);
same('a fast device clock does not make a fresh page look cached', P.cached({ at: server - 400 }, server, server + 600000, server + 600000 - 1000), false);
same('without the server time: unknown, not cached', P.cached({ at: 1 }, 0, device, device), false);
same('without figures: not cached', P.cached(null, server, device, device), false);
same('milliseconds', P.ms(84.26, 'en-US'), '84 ms');
same('below ten with a decimal', P.ms(3.04, 'en-US'), '3 ms');
same('a decimal below ten', P.ms(3.46, 'en-US'), '3.5 ms');
same('seconds', P.ms(1240, 'en-US'), '1.24 s');
same('nothing measured', P.ms(null, 'en-US'), '');
same('megabytes', P.bytes(18874368, 'en-US'), '18 MB');
same('small memory with a decimal', P.bytes(2621440, 'en-US'), '2.5 MB');
same('share of the slowest', [P.share(50, 200), P.share(200, 200), P.share(0, 200), P.share(10, 0), P.share(300, 200)], [25, 100, 0, 0, 100]);

process.exit(failed ? 1 : 0);
