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

same('one "Verify it\'s you" window at a time: a second action waits for the same answer', /var pending = this\.reauthPending;/.test(host), true);
same('the panel call passes its options (onReauth) to the sidebar', host.indexOf('call: function (action, input, opts) { return self.call(action, input, false, opts); }') > 0, true);
same('the retry after "Verify it\'s you" keeps the options', host.indexOf('return self.reauth(action, opts).then(function () { return self.call(action, input, true, opts); });') > 0, true);
same('no button of the Export panel is disabled while it works', /(md|docx|create|withdraw)\.disabled = /.test(source), false);

/* Export.run: the button's busy state, always undone; failures said; Cancel of "Verify it's you" not said as a failure */
function el(tag, attrs, kids) {
  var node = { tag: tag, attrs: {}, children: [], parentNode: null, className: (attrs && attrs.class) || '', onclick: attrs && attrs.onclick,
    setAttribute: function (k, v) { this.attrs[k] = String(v); }, getAttribute: function (k) { return Object.prototype.hasOwnProperty.call(this.attrs, k) ? this.attrs[k] : null; },
    removeAttribute: function (k) { delete this.attrs[k]; },
    appendChild: function (c) { c.parentNode = this; this.children.push(c); return c; },
    removeChild: function (c) { this.children.splice(this.children.indexOf(c), 1); c.parentNode = null; return c; },
    insertBefore: function (c, ref) { c.parentNode = this; var i = ref ? this.children.indexOf(ref) : -1; if (i < 0) { this.children.push(c); } else { this.children.splice(i, 0, c); } return c; } };
  Object.defineProperty(node, 'nextSibling', { get: function () { var p = this.parentNode; if (!p) { return null; } var i = p.children.indexOf(this); return p.children[i + 1] || null; } });
  (kids || []).forEach(function (k) { node.appendChild(k); });
  return node;
}
function fixture() {
  var said = [];
  var section = el('section');
  var button = el('button');
  var row = section.appendChild(el('div', { class: 'wt-row' }, [button]));
  var ctx = { h: el, t: function (s) { return s; }, say: function (text, kind) { said.push([text, kind]); } };
  return { said: said, section: section, button: button, row: row, ctx: ctx };
}
var pending = [];
function check(name, fn) { pending.push(Promise.resolve().then(fn).catch(function (e) { failed++; console.log('FAIL ' + name + ': ' + e.message); })); }

check('run: busy while working, back after the answer', function () {
  var f = fixture();
  var resolve;
  var p = E.run(f.ctx, f.button, function () { return new Promise(function (r) { resolve = r; }); });
  same('run: aria-busy while working', f.button.getAttribute('aria-busy'), 'true');
  same('run: aria-disabled while working', f.button.getAttribute('aria-disabled'), 'true');
  same('run: a second press sends nothing', E.run(f.ctx, f.button, function () { throw new Error('sent twice'); }), null);
  resolve('file');
  return p.then(function (v) {
    same('run: the answer is passed on', v, 'file');
    same('run: not busy after the answer', f.button.getAttribute('aria-busy'), null);
    same('run: not disabled after the answer', f.button.getAttribute('aria-disabled'), null);
  });
});
check('run: a refusal brings the button back and is said', function () {
  var f = fixture();
  var err = new Error('Please confirm it is you first.');
  return E.run(f.ctx, f.button, function () { return Promise.reject(err); }).then(function () {
    same('run: not busy after a refusal', f.button.getAttribute('aria-busy'), null);
    same('run: the refusal is said', f.said, [['Please confirm it is you first.', 'bad']]);
  });
});
check('run: an error thrown before the call brings the button back', function () {
  var f = fixture();
  return E.run(f.ctx, f.button, function () { throw new Error('broken'); }).then(function () {
    same('run: not busy after a throw', f.button.getAttribute('aria-busy'), null);
    same('run: the throw is said', f.said, [['broken', 'bad']]);
  });
});
check('run: "Verify it\'s you" waiting shows a row to bring the window back or cancel', function () {
  var f = fixture();
  var focused = 0;
  var reject;
  var p = E.run(f.ctx, f.button, function (opts) {
    return new Promise(function (res, rej) {
      reject = rej;
      opts.onReauth({ focus: function () { focused++; }, cancel: function () { var c = new Error('cancelled'); c.cancelled = true; rej(c); } });
    });
  });
  var wait = f.row.nextSibling;
  same('run: the waiting row follows the buttons', wait && wait.className, 'wt-row wt-reauth-wait');
  same('run: the waiting row has two buttons', wait.children.length, 2);
  wait.children[0].onclick();
  same('run: "Verify it\'s you" brings the window back', focused, 1);
  wait.children[1].onclick();
  return p.then(function () {
    same('run: not busy after Cancel', f.button.getAttribute('aria-busy'), null);
    same('run: the waiting row is gone after Cancel', f.row.nextSibling, null);
    same('run: Cancel is not said as a failure', f.said, []);
  });
});
check('run: two buttons waiting for the same window share one row', function () {
  var f = fixture();
  var other = f.row.appendChild(el('button'));
  var done;
  var shared = new Promise(function (r) { done = r; });
  var handle = { focus: function () {}, cancel: function () {} };
  var p1 = E.run(f.ctx, f.button, function (opts) { opts.onReauth(handle); return shared; });
  var p2 = E.run(f.ctx, other, function (opts) { opts.onReauth(handle); return shared; });
  same('run: one waiting row for both', f.section.children.length, 2);
  done('ok');
  return Promise.all([p1, p2]).then(function () {
    same('run: both buttons back', [f.button.getAttribute('aria-busy'), other.getAttribute('aria-busy')], [null, null]);
    same('run: no row left', f.section.children.length, 1);
  });
});

Promise.all(pending).then(function () { process.exit(failed ? 1 : 0); });
