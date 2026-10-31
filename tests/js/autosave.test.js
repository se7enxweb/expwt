/*
 * Lost work (F28): the rules of the sidebar's autosave and of the copy kept on this device (expwt-panels.js,
 * ExpwtAutosave; expwt.js, ExpwtEditState.unsaved). Run with Node (no packages): node tests/js/autosave.test.js
 * (expwtPureTest runs it when Node is there). Prints PASS or FAIL lines and exits 1 on a failure.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */
'use strict';
var fs = require('fs');
var path = require('path');
var vm = require('vm');

function Storage() { this.items = {}; this.length = 0; }
Storage.prototype.sync = function () { this.length = Object.keys(this.items).length; };
Storage.prototype.key = function (i) { return Object.keys(this.items)[i] || null; };
Storage.prototype.getItem = function (k) { return Object.prototype.hasOwnProperty.call(this.items, k) ? this.items[k] : null; };
Storage.prototype.setItem = function (k, v) { this.items[k] = String(v); this.sync(); };
Storage.prototype.removeItem = function (k) { delete this.items[k]; this.sync(); };

function load(file, sandbox) {
  vm.createContext(sandbox);
  vm.runInContext(fs.readFileSync(path.join(__dirname, '../../design/standard/javascript/' + file), 'utf8'), sandbox, { filename: file });
}
var win = {};
load('expwt-panels.js', { window: win, document: {} });
var A = win.ExpwtAutosave;
var host = { customElements: { get: function () { return undefined; }, define: function () {} } };
var hostSandbox = { window: host, document: {}, HTMLElement: function () {}, Document: function () {}, CSSStyleSheet: function () {}, Element: function () {} };
hostSandbox.HTMLElement.prototype = {};
load('expwt.js', hostSandbox);
var E = host.ExpwtEditState;

var failed = 0;
function check(name, ok) {
  console.log((ok ? 'PASS ' : 'FAIL ') + name);
  if (!ok) { failed++; }
}

check('the helper is there', !!A && !!E);
check('the panel\'s copy is kept per page and language', A.key(57, 'eng-GB') === 'expwt.copy.57.eng-GB');
check('an editor\'s copy per field', A.frameKey(57, 'eng-GB', 'body') === 'expwt.copyf.57.eng-GB.body');
check('every few seconds', A.EVERY >= 2000 && A.EVERY <= 10000);

/* the panel's fields: only when something changed, and once typing paused */
var now = 1000000;
check('nothing changed: no save', A.formDue(0, now - 60000, now) === false);
check('typing right now: not yet', A.formDue(2, now - 200, now) === false);
check('typing paused: saved', A.formDue(1, now - A.QUIET, now) === true);

/* the editors: changed, loaded, never the one with the focus (saving it would reload it under the user's hands) */
var a = { changed: true, ready: true, frame: { id: 'a' } };
var b = { changed: true, ready: true, frame: { id: 'b' } };
var c = { changed: false, ready: true, frame: { id: 'c' } };
var d = { changed: true, ready: false, frame: { id: 'd' } };
var e = { changed: true, ready: true, frame: null };
var due = A.framesDue([a, b, c, d, e], b.frame);
check('the focused editor is skipped', due.indexOf(b) < 0);
check('a changed editor without the focus is saved', due.indexOf(a) >= 0);
check('an editor nobody changed is never saved (opening one makes no draft)', due.indexOf(c) < 0);
check('an editor still loading or not loaded is not asked', due.indexOf(d) < 0 && due.indexOf(e) < 0);
check('no focus anywhere: every changed editor', A.framesDue([a, b, c], null).length === 2);

/* the copy on this device */
var s = new Storage();
check('written and read back', A.write(s, 'expwt.copy.1.eng-GB', { at: now, node: 2, fields: { title: 'x' } }) && A.read(s, 'expwt.copy.1.eng-GB').fields.title === 'x');
check('removed with null', A.write(s, 'expwt.copy.1.eng-GB', null) && A.read(s, 'expwt.copy.1.eng-GB') === null);
check('a full or blocked storage is no error', A.write({ setItem: function () { throw new Error('quota'); } }, 'k', { at: 1 }) === false);
check('a broken entry reads as none', A.read({ getItem: function () { return '{'; } }, 'k') === null);
check('fresh for two weeks', A.fresh({ at: now }, now + 13 * 86400000) === true && A.fresh({ at: now }, now + 15 * 86400000) === false);
check('a copy from the future (a wrong clock) is not offered', A.fresh({ at: now + 5000 }, now) === false);

/* what is offered back: only fields whose copy is not what the server has now */
var back = A.differs({ title: 'Mine', intro: 'Same', gone: 'x' }, { title: 'Theirs', intro: 'Same' });
check('a field the server has differently is offered', back.indexOf('title') >= 0);
check('a field the server already has is not', back.indexOf('intro') < 0);
check('a field the page no longer has is not', back.indexOf('gone') < 0);

/* old copies go when a page is opened */
var t = new Storage();
A.write(t, 'expwt.copy.1.eng-GB', { at: now - 20 * 86400000, node: 2 });
A.write(t, 'expwt.copyf.1.eng-GB.body', { at: now - 20 * 86400000, entries: [] });
A.write(t, 'expwt.copy.3.eng-GB', { at: now - 1000, node: 4 });
A.write(t, 'other', 'kept');
A.expired(t, now);
check('copies older than two weeks are removed', t.getItem('expwt.copy.1.eng-GB') === null && t.getItem('expwt.copyf.1.eng-GB.body') === null);
check('a recent copy and other keys stay', t.getItem('expwt.copy.3.eng-GB') !== null && t.getItem('other') !== null);
check('the keys of a prefix', A.keys(t, 'expwt.copy.').length === 1);

/* the closed tab: this page has changes only this device kept */
check('the mark for the page of the copy', E.unsaved(t, 4, now) === true);
check('no mark on another page', E.unsaved(t, 2, now) === false);
check('no mark without a node or a storage', E.unsaved(t, 0, now) === false && E.unsaved(null, 4, now) === false);
check('no mark for an old copy', E.unsaved(t, 4, now + 15 * 86400000) === false);

process.exit(failed ? 1 : 0);
