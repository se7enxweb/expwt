/*
 * The buttons of a comment in the Page panel's comments (expwt-panels.js, ExpwtComments.actions(), E4). Run with Node
 * (no packages): node tests/js/comments.test.js. Prints PASS or FAIL lines and exits 1 on a failure.
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
var actions = win.ExpwtComments.actions;
var failed = 0;

function same(name, got, want) {
  var ok = JSON.stringify(got) === JSON.stringify(want);
  if (!ok) { failed++; }
  console.log((ok ? 'PASS ' : 'FAIL ') + name + (ok ? '' : ': ' + JSON.stringify(got) + ' != ' + JSON.stringify(want)));
}

same('a hidden comment for a moderator: approve, reply, spam',
  actions({ hidden: true, can: { moderate: true, spam: true } }, true), ['approve', 'reply', 'spam']);
same('a shown comment for a moderator: hide, reply, spam',
  actions({ hidden: false, can: { moderate: true, spam: true } }, true), ['hide', 'reply', 'spam']);
same('an editor who may only comment: reply',
  actions({ hidden: false, can: { moderate: false, spam: false } }, true), ['reply']);
same('no rights at all: no buttons',
  actions({ hidden: false, can: { moderate: false, spam: false } }, false), []);
same('may remove but not hide: spam only',
  actions({ hidden: false, can: { moderate: false, spam: true } }, false), ['spam']);
same('no can given: nothing',
  actions({ hidden: true }, true), ['reply']);

process.exit(failed ? 1 : 0);
