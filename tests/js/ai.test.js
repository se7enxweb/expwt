/*
 * Exponential Intelligence on a field of the Page panel (expwt-panels.js, ExpwtAi, E1): which actions a field gets,
 * and the text of a rich text suggestion. Run with Node (no packages): node tests/js/ai.test.js (expwtPureTest runs
 * it when Node is there). Prints PASS or FAIL lines and exits 1 on a failure.
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
var A = win.ExpwtAi;

var failed = 0;
function check(name, ok) {
  console.log((ok ? 'PASS ' : 'FAIL ') + name);
  if (!ok) { failed++; }
}
function names(list) { return list.map(function (a) { return a.name; }).join(','); }

var offer = { actions: [
  { name: 'write', datatypes: ['ezstring', 'eztext', 'ezxmltext'] },
  { name: 'shorten', datatypes: ['eztext', 'ezxmltext'] },
  { name: 'suggest', datatypes: ['ezstring', 'ezkeyword'] },
  { name: 'alttext', datatypes: ['ezimage'] }
], languages: [] };
var can = { edit: true };

check('a text line gets Write and Suggest', names(A.actionsFor(offer, can, 'ezstring')) === 'write,suggest');
check('rich text gets Write and Shorten', names(A.actionsFor(offer, can, 'ezxmltext')) === 'write,shorten');
check('keywords get Suggest (tags)', names(A.actionsFor(offer, can, 'ezkeyword')) === 'suggest');
check('an image gets its alt text', names(A.actionsFor(offer, can, 'ezimage')) === 'alttext');
check('a number gets nothing', A.actionsFor(offer, can, 'ezinteger').length === 0);
check('no offer (expai off, or no expai/write): nothing', A.actionsFor(null, can, 'ezstring').length === 0);
check('a page the user may not edit: nothing', A.actionsFor(offer, { edit: false }, 'ezstring').length === 0);
check('no permissions known: nothing', A.actionsFor(offer, null, 'ezstring').length === 0);

check('paragraphs on their own lines', A.plainText('<p>One</p><p>Two <b>bold</b></p>') === 'One\nTwo bold');
check('line breaks and list items', A.plainText('<ul><li>a</li><li>b</li></ul>x<br/>y') === 'a\nb\nx\ny');
check('entities read as their characters', A.plainText('<p>Fish &amp; chips &lt;3 &quot;yes&quot; it&#39;s&nbsp;hot</p>') === 'Fish & chips <3 "yes" it\'s hot');
check('no markup survives', A.plainText('<p onclick="x()">a<script>b</script></p>').indexOf('<') === -1);
check('empty and null', A.plainText('') === '' && A.plainText(null) === '');
check('no more than one empty line', A.plainText('<p>a</p><p></p><p></p><p>b</p>') === 'a\n\nb');

if (failed) { console.log('FAIL ' + failed + ' check(s)'); process.exit(1); }
console.log('PASS all');
