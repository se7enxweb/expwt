/*
 * What the URLs panel sends as an address (expwt-panels.js, ExpwtUrls.address(), E5). Run with Node
 * (no packages): node tests/js/urls.test.js. Prints PASS or FAIL lines and exits 1 on a failure.
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
var sandbox = { window: win, document: {} };
vm.createContext(sandbox);
vm.runInContext(source, sandbox, { filename: 'expwt-panels.js' });
var address = win.ExpwtUrls.address;
var failed = 0;

function same(name, got, want) {
  var ok = JSON.stringify(got) === JSON.stringify(want);
  if (!ok) { failed++; }
  console.log((ok ? 'PASS ' : 'FAIL ') + name + (ok ? '' : ': ' + JSON.stringify(got) + ' != ' + JSON.stringify(want)));
}

same('one leading slash, no trailing one', address('promo/run/'), '/promo/run');
same('doubled slashes once', address('//promo///run'), '/promo/run');
same('the host and the query go', address('https://alpha.se7enx.com/promo/run?utm=x#top'), '/promo/run');
same('a wildcard keeps its star', address(' /old-campaign/* '), '/old-campaign/*');
same('nothing is nothing', address('  / '), '');
same('undefined is nothing', address(undefined), '');

process.exit(failed ? 1 : 0);
