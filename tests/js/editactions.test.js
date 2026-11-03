/*
 * The questions of the site design's edit actions (expwt-actions.js, design:parts/expwt_edit_actions.tpl): a click on
 * a button with data-expwt-confirm goes ahead only when the question is answered yes, other clicks are left alone, a
 * disabled button asks nothing, and loading the file twice keeps one listener. Run with Node (no packages):
 * node tests/js/editactions.test.js. Prints PASS or FAIL lines and exits 1 on a failure.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */
'use strict';
var fs = require('fs');
var path = require('path');
var vm = require('vm');

var source = fs.readFileSync(path.join(__dirname, '../../design/standard/javascript/expwt-actions.js'), 'utf8');
var listeners = [];
var answer = true;
var asked = [];
var win = { confirm: function (q) { asked.push(q); return answer; } };
var doc = { addEventListener: function (type, fn) { listeners.push({ type: type, fn: fn }); } };
var sandbox = { window: win, document: doc };
vm.createContext(sandbox);
vm.runInContext(source, sandbox, { filename: 'expwt-actions.js' });
vm.runInContext(source, sandbox, { filename: 'expwt-actions.js' });
var failed = 0;

function same(name, got, want) {
  var ok = JSON.stringify(got) === JSON.stringify(want);
  if (!ok) { failed++; }
  console.log((ok ? 'PASS ' : 'FAIL ') + name + (ok ? '' : ': ' + JSON.stringify(got) + ' != ' + JSON.stringify(want)));
}

function button(question, disabled) {
  return { disabled: !!disabled, getAttribute: function () { return question; },
           closest: function (sel) { return question !== null && sel === '[data-expwt-confirm]' ? this : null; } };
}

function click(target) {
  var ev = { target: target, prevented: false, preventDefault: function () { this.prevented = true; } };
  listeners.forEach(function (l) { if (l.type === 'click') { l.fn(ev); } });
  return ev.prevented;
}

same('loaded twice: one listener', listeners.length, 1);
answer = false;
same('discard answered no: not submitted', click(button('Discard?')), true);
same('the question is the attribute', asked, ['Discard?']);
answer = true;
same('discard answered yes: submitted', click(button('Discard?')), false);
asked = [];
same('a button without a question: submitted', click(button(null)), false);
same('a button without a question: nothing asked', asked, []);
same('a disabled button: nothing asked', (click(button('Discard?', true)), asked), []);
same('a text node target: ignored', click({}), false);
process.exit(failed ? 1 : 0);
