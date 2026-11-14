/*
 * The addresses of expwt/api the sidebar asks (expwt.js, ExpwtApiUrl): view parameters, never a query; the input of a
 * read as base64url (RFC 4648 section 5, no padding) that the server's expwtApi::decodeInput() reads back.
 * Run with Node (no packages): node tests/js/apiurl.test.js. Prints PASS or FAIL lines and exits 1 on a failure.
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
var sandbox = { window: win, document: {}, HTMLElement: function () {}, Document: function () {}, CSSStyleSheet: function () {}, Element: function () {},
  TextEncoder: TextEncoder, btoa: btoa };
sandbox.HTMLElement.prototype = {};
vm.createContext(sandbox);
vm.runInContext(source, sandbox, { filename: 'expwt.js' });
var U = win.ExpwtApiUrl;
var failed = 0;

function same(name, got, want) {
  var ok = JSON.stringify(got) === JSON.stringify(want);
  if (!ok) { failed++; }
  console.log((ok ? 'PASS ' : 'FAIL ') + name + (ok ? '' : ': ' + JSON.stringify(got) + ' != ' + JSON.stringify(want)));
}
function back(encoded) {
  return JSON.parse(Buffer.from(encoded.replace(/-/g, '+').replace(/_/g, '/'), 'base64').toString('utf8'));
}

same('the boot of a page', U.read('/expwt/api', 'boot', { nodeId: 89 }), '/expwt/api/boot/(input)/eyJub2RlSWQiOjg5fQ');
same('an empty input leaves (input) out', U.read('/expwt/api', 'boot', {}), '/expwt/api/boot');
same('a siteaccess prefix stays', U.read('/site/expwt/api', 'page.load', {}), '/site/expwt/api/page.load');
same('a write: the action in the path, closed by a slash (no ".save" at the end)', U.write('/site/expwt/api/', 'state.save'), '/site/expwt/api/state.save/');
same('a write never ends like a backup file', /\.(bak|old|orig|save|swp|sql|log)$/.test(U.write('/expwt/api', 'draft.save')), false);
same('no query', U.read('/expwt/api', 'urls.load', { path: '/a?b' }).indexOf('?'), -1);

var input = { path: '/news/a+b?c=d', name: 'Grüße ✓', list: [1, 2, 3] };
var encoded = U.encode(input);
same('base64url only: no "/", "+" or "="', /^[A-Za-z0-9_-]+$/.test(encoded), true);
same('the server reads it back (UTF-8)', back(encoded), input);
same('the "-" of base64url', U.encode({ q: '??>' }), 'eyJxIjoiPz8-In0');
var big = { text: new Array(70001).join('x') };
same('a long input in one piece', back(U.encode(big)).text.length, 70000);

if (failed) { console.log(failed + ' FAILED'); process.exit(1); }
console.log('PASS all');
