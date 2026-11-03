/*
 * How the tabs of the website sidebar are labeled (expwt.js, ExpwtTabLabels): icons, icons and text, or text; the
 * kernel's Bootstrap Icons for the built-in panels, a named one for an extension's panel, the panel's name whenever
 * there is no icon. Run with Node (no packages): node tests/js/tablabels.test.js (expwtPureTest runs it when Node
 * is there). Prints PASS or FAIL lines and exits 1 on a failure.
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
var win = { customElements: { get: function () { return undefined; }, define: function (name, cls) { win.ExpWtClass = cls; } } };
var sandbox = { window: win, document: {}, HTMLElement: function () {}, Document: function () {}, CSSStyleSheet: function () {}, Element: function () {} };
sandbox.HTMLElement.prototype = {};
vm.createContext(sandbox);
vm.runInContext(source, sandbox, { filename: 'expwt.js' });
var L = win.ExpwtTabLabels;
var M = win.ExpwtPanelMemory;

var failed = 0;
function check(name, ok) {
  console.log((ok ? 'PASS ' : 'FAIL ') + name);
  if (!ok) { failed++; }
}

check('the helper is there', !!L);
if (!L) {
  console.log('FAIL the remaining checks need the helper');
  process.exit(1);
}

var B = '/share/icons/bootstrap-icons/svg/';
var BUILT_IN = ['welcome', 'page', 'create', 'structure', 'seo', 'sites', 'forms', 'newsletter', 'urls', 'calendar',
  'export', 'insights', 'inbox', 'migrations', 'static', 'performance', 'me'];

// mode: the user's choice, else the installation's, else icons
check('mode: the user\'s choice', L.mode('both', 'text') === 'both');
check('mode: no choice takes the installation\'s', L.mode('', 'text') === 'text');
check('mode: an unknown choice takes the installation\'s', L.mode('words', 'text') === 'text');
check('mode: nothing valid is icons', L.mode('x', 'y') === 'icons');
check('mode: undefined is icons', L.mode(undefined, undefined) === 'icons');

// the state keeps the choice, or '' for the installation's
check('stateValue: icons', L.stateValue('icons') === 'icons');
check('stateValue: both', L.stateValue('both') === 'both');
check('stateValue: text', L.stateValue('text') === 'text');
check('stateValue: unknown is the installation\'s', L.stateValue('icon') === '');
check('stateValue: missing is the installation\'s', L.stateValue(undefined) === '');
check('stateValue: a number is the installation\'s', L.stateValue(7) === '');

// the choices of the Me and Welcome panels, in this order
check('choices: icons, both, text', JSON.stringify(L.CHOICES) === JSON.stringify([['icons', 'Icons'], ['both', 'Icons and text'], ['text', 'Text']]));

// icon names
check('iconName: a name', L.iconName('file-earmark-text') && L.iconName('globe2'));
check('iconName: no path', !L.iconName('../x') && !L.iconName('a/b'));
check('iconName: no markup', !L.iconName('<svg>'));
check('iconName: no empty or odd dashes', !L.iconName('') && !L.iconName('-a') && !L.iconName('a--b') && !L.iconName('A'));
check('iconName: no array', !L.iconName(['house']));

// every built-in panel has its own icon, and the kernel ships it
check('ICONS: every built-in panel', BUILT_IN.every(function (id) { return typeof L.ICONS[id] === 'string'; }));
check('ICONS: no other', Object.keys(L.ICONS).length === BUILT_IN.length);
check('ICONS: no two the same', new Set(Object.keys(L.ICONS).map(function (k) { return L.ICONS[k]; })).size === BUILT_IN.length);
check('ICONS: every name is a name', Object.keys(L.ICONS).every(function (k) { return L.iconName(L.ICONS[k]); }));
var kernel = path.join(process.env.EXPONENTIAL_ROOT || process.cwd(), 'share/icons/bootstrap-icons/svg');
if (fs.existsSync(kernel)) {
  var missing = Object.keys(L.ICONS).filter(function (k) { return !fs.existsSync(path.join(kernel, L.ICONS[k] + '.svg')); });
  check('ICONS: the kernel ships every one' + (missing.length ? ' (missing: ' + missing.join(', ') + ')' : ''), missing.length === 0);
} else {
  console.log('SKIP ICONS: the kernel ships every one (no ' + kernel + '; run from the installation\'s root or set EXPONENTIAL_ROOT)');
}

// parts: what a tab shows
var p = L.parts({ id: 'page', label: 'Page' }, 'icons', B);
check('icons: the icon', p.icon === B + 'file-earmark-text.svg');
check('icons: no visible name', p.text === false);
check('icons: the name stays', p.name === 'Page');
p = L.parts({ id: 'page', label: 'Page' }, 'both', B);
check('icons and text: icon and name', p.icon === B + 'file-earmark-text.svg' && p.text === true);
p = L.parts({ id: 'page', label: 'Page' }, 'text', B);
check('text: no icon, the name', p.icon === null && p.text === true);
p = L.parts({ id: 'page', label: 'Page' }, 'icons', '');
check('a kernel without the icon set: the name', p.icon === null && p.text === true && p.name === 'Page');
p = L.parts({ id: 'shop', label: 'Shop' }, 'icons', B);
check('an extension\'s panel without an icon: the fallback icon, its label the tooltip', p.icon === B + 'puzzle.svg' && p.text === false && p.name === 'Shop');
p = L.parts({ id: 'shop', label: 'Shop' }, 'text', B);
check('an extension\'s panel without an icon, text: its label', p.icon === null && p.text === true);
p = L.parts({ id: 'shop', label: 'Shop', icon: 'cart' }, 'icons', B);
check('an extension\'s panel with an icon', p.icon === B + 'cart.svg' && p.text === false && p.name === 'Shop');
p = L.parts({ id: 'shop', label: 'Shop', icon: '../x' }, 'icons', B);
check('an extension\'s icon that is no name: the fallback icon', p.icon === B + 'puzzle.svg');
p = L.parts({ id: 'shop', label: 'Shop', icon: '' }, 'icons', B);
check('an extension\'s empty icon: the fallback icon', p.icon === B + 'puzzle.svg');
p = L.parts({ id: 'toString', label: 'X' }, 'icons', B);
check('no icon from the object\'s prototype: the fallback icon', p.icon === B + 'puzzle.svg');
check('FALLBACK: a name the kernel ships', L.FALLBACK === 'puzzle' && (!fs.existsSync(kernel) || fs.existsSync(path.join(kernel, 'puzzle.svg'))));
p = L.parts({ id: 'static', label: 'Statisch veröffentlichen' }, 'icons', B);
check('static publishing: the kernel\'s label and the built-in icon', p.name === 'Statisch veröffentlichen' && p.icon === B + L.ICONS['static'] + '.svg');

// fill: the button of a tab, with a stand-in for the sidebar's element factory
function fake(tag, attrs, children) {
  var el = { tag: tag, attrs: {}, children: [], props: {}, text: '' };
  el.style = { setProperty: function (k, v) { el.props[k] = v; } };
  el.setAttribute = function (k, v) { el.attrs[k] = String(v); };
  el.getAttribute = function (k) { return Object.prototype.hasOwnProperty.call(el.attrs, k) ? el.attrs[k] : null; };
  el.removeAttribute = function (k) { delete el.attrs[k]; };
  el.appendChild = function (c) { el.children.push(c); return c; };
  Object.keys(attrs || {}).forEach(function (k) {
    if (k === 'text') { el.text = attrs[k]; } else if (attrs[k] !== null && attrs[k] !== undefined) { el.attrs[k] = String(attrs[k]); }
  });
  (children || []).forEach(function (c) { el.children.push(typeof c === 'string' ? { tag: '#text', text: c } : c); });
  return el;
}
function byClass(el, cls) {
  return el.children.filter(function (c) { return c.attrs && (' ' + (c.attrs['class'] || '') + ' ').indexOf(' ' + cls + ' ') >= 0; });
}

var b = fake('button');
L.fill(b, L.parts({ id: 'page', label: 'Page' }, 'icons', B), fake);
var ic = byClass(b, 'wt-tab-icon');
check('fill icons: one icon', ic.length === 1);
check('fill icons: the icon is hidden from screen readers', ic.length === 1 && ic[0].attrs['aria-hidden'] === 'true');
check('fill icons: the icon by a custom property, not a style attribute', ic.length === 1 && ic[0].props['--wt-icon'] === 'url("' + B + 'file-earmark-text.svg")' && !('style' in ic[0].attrs));
check('fill icons: the name for screen readers', byClass(b, 'wt-sr').length === 1 && byClass(b, 'wt-sr')[0].text === 'Page');
check('fill icons: no visible name', byClass(b, 'wt-tab-text').length === 0);
check('fill icons: the name for the bubble, no title', b.attrs['data-tip'] === 'Page' && !('title' in b.attrs));

b = fake('button');
L.fill(b, L.parts({ id: 'page', label: 'Page' }, 'both', B), fake);
check('fill icons and text: icon and visible name', byClass(b, 'wt-tab-icon').length === 1 && byClass(b, 'wt-tab-text').length === 1 && byClass(b, 'wt-tab-text')[0].text === 'Page');
check('fill icons and text: no hidden name, no tooltip', byClass(b, 'wt-sr').length === 0 && !('title' in b.attrs) && !('data-tip' in b.attrs));
b = fake('button', { title: 'old', 'data-tip': 'old' });
L.fill(b, L.parts({ id: 'page', label: 'Page' }, 'text', B), fake);
check('fill text after icons: no bubble, no title left over', !('data-tip' in b.attrs) && !('title' in b.attrs));

// tipAt: below the tab and centered, above it at the window's bottom, never past a side
var view = { width: 1000, height: 800 };
var at = L.tipAt({ left: 100, right: 132, top: 50, bottom: 82, width: 32, height: 32 }, { width: 60, height: 20 }, view);
check('tipAt: centered below the tab', at.x === 86 && at.y === 86);
at = L.tipAt({ left: 0, right: 32, top: 50, bottom: 82, width: 32, height: 32 }, { width: 60, height: 20 }, view);
check('tipAt: not past the left side', at.x === 4);
at = L.tipAt({ left: 980, right: 1000, top: 50, bottom: 82, width: 20, height: 32 }, { width: 60, height: 20 }, view);
check('tipAt: not past the right side', at.x === 936);
at = L.tipAt({ left: 100, right: 132, top: 770, bottom: 800, width: 32, height: 30 }, { width: 60, height: 20 }, view);
check('tipAt: above the tab at the window\'s bottom', at.y === 746);
at = L.tipAt({ left: 100, right: 132, top: 5, bottom: 795, width: 32, height: 790 }, { width: 60, height: 20 }, view);
check('tipAt: below when there is no room above either', at.y === 799);

b = fake('button');
L.fill(b, L.parts({ id: 'page', label: 'Page' }, 'text', B), fake);
check('fill text: the name only', byClass(b, 'wt-tab-icon').length === 0 && byClass(b, 'wt-tab-text').length === 1 && byClass(b, 'wt-sr').length === 0);

// a badge the sidebar adds after fill stays beside the label
b = fake('button');
L.fill(b, L.parts({ id: 'inbox', label: 'Inbox' }, 'icons', B), fake);
b.appendChild(fake('span', { 'class': 'wt-badge', 'data-badge': 'waiting' }, [fake('span', { 'aria-hidden': 'true', text: '3' }), fake('span', { 'class': 'wt-sr', text: ' 3 new in your inbox' })]));
check('badge on an icon tab: icon, name and badge', byClass(b, 'wt-tab-icon').length === 1 && byClass(b, 'wt-sr').length === 1 && byClass(b, 'wt-badge').length === 1);
check('badge on an icon tab: the name first, then the badge', b.children.indexOf(byClass(b, 'wt-sr')[0]) < b.children.indexOf(byClass(b, 'wt-badge')[0]));

// the choice kept on this device wins over the server's after the boot answer, as the theme does
var s = M.onBoot({ labels: 'text', theme: 'dark' }, { labels: 'both' }, false);
check('onBoot: this device\'s unsent choice over the server\'s', s.labels === 'both' && s.theme === 'dark');
s = M.onBoot({ labels: 'text' }, null, false);
check('onBoot: the server\'s choice on another device', s.labels === 'text');

// the sidebar's element: keys on icon tabs, the chooser, and a changed choice drawn at once
var W = win.ExpWtClass;
check('the element is defined', !!W);
if (W) {
  // a document for the element factory: just what h() uses
  sandbox.document.createElement = function (tag) {
    var el = fake(tag);
    el.listeners = {};
    el.addEventListener = function (type, fn) { el.listeners[type] = fn; };
    Object.defineProperty(el, 'textContent', { set: function (v) { el.text = v; }, get: function () { return el.text; } });
    return el;
  };
  sandbox.document.createTextNode = function (text) { return { tag: '#text', text: text }; };
  sandbox.getComputedStyle = function () { return { direction: 'ltr' }; };

  // Keys unchanged: the right arrow selects the next icon tab and shows its panel
  var shown = [];
  var buttons = ['page', 'sites', 'me'].map(function (id) {
    var bt = fake('button', { 'data-panel': id });
    bt.focused = false;
    bt.focus = function () { bt.focused = true; };
    L.fill(bt, L.parts({ id: id, label: id }, 'icons', B), fake);
    return bt;
  });
  W.prototype.tabsKey.call({ tabButtons: buttons, show: function (id) { shown.push(id); } },
    { key: 'ArrowRight', target: buttons[0], preventDefault: function () {} });
  check('keys on icon tabs: the right arrow goes to the next tab', buttons[1].focused && shown.join() === 'sites');
  shown = [];
  W.prototype.tabsKey.call({ tabButtons: buttons, show: function (id) { shown.push(id); } },
    { key: 'End', target: buttons[0], preventDefault: function () {} });
  check('keys on icon tabs: End goes to the last tab', buttons[2].focused && shown.join() === 'me');

  // the bubble: shown with the tab's name, placed by custom properties, hidden by Escape before anything else
  var tip = fake('span', { 'class': 'wt-tip' });
  tip.hidden = true;
  tip.offsetWidth = 40;
  tip.offsetHeight = 20;
  Object.defineProperty(tip, 'textContent', { set: function (v) { tip.text = v; }, get: function () { return tip.text; } });
  sandbox.document.documentElement = { clientWidth: 1000 };
  win.innerWidth = 1000;
  win.innerHeight = 800;
  sandbox.innerHeight = 800;
  var th = { tip: tip, tabButtons: buttons, show: function (id) { shown.push(id); }, tipHide: W.prototype.tipHide };
  buttons[0].getBoundingClientRect = function () { return { left: 100, right: 132, top: 10, bottom: 42, width: 32, height: 32 }; };
  W.prototype.tipShow.call(th, buttons[0]);
  check('tipShow: the name of the tab', tip.hidden === false && tip.text === 'page');
  check('tipShow: placed by custom properties', tip.props['--wt-tip-x'] === '96px' && tip.props['--wt-tip-y'] === '46px' && !('style' in tip.attrs));
  shown = [];
  var stopped = false;
  W.prototype.tabsKey.call(th, { key: 'Escape', target: buttons[0], preventDefault: function () {}, stopPropagation: function () { stopped = true; } });
  check('Escape: hides the bubble, nothing else', tip.hidden === true && stopped && shown.length === 0);
  stopped = false;
  W.prototype.tabsKey.call(th, { key: 'Escape', target: buttons[0], preventDefault: function () {}, stopPropagation: function () { stopped = true; } });
  check('Escape without a bubble: passed on', !stopped);
  var plain = fake('button');
  W.prototype.tipShow.call(th, plain);
  check('tipShow: a tab with its name visible has no bubble', tip.hidden === true);

  // the chooser: three radios, the choice in effect checked, a change kept with the sidebar's settings
  var changes = [];
  var host = { state: { labels: '' }, cfg: { labels: 'text' }, t: function (s) { return s; }, labelMode: W.prototype.labelMode,
    setState: function (c) { changes.push(c); } };
  var fs2 = W.prototype.labelChooser.call(host);
  var legend = fs2.children.filter(function (c) { return c.tag === 'legend'; });
  check('chooser: its legend', legend.length === 1 && legend[0].text === 'Tab labels');
  var radios = fs2.children.filter(function (c) { return c.tag === 'label'; }).map(function (l) { return l.children[0]; });
  check('chooser: three radios in the order icons, both, text', radios.map(function (r) { return r.value; }).join() === 'icons,both,text');
  check('chooser: the installation\'s choice is checked without one of the user\'s', radios[2].checked === true && !radios[0].checked && !radios[1].checked);
  check('chooser: the words', fs2.children.filter(function (c) { return c.tag === 'label'; }).map(function (l) { return l.children[1].text; }).join('|') === 'Icons|Icons and text|Text');
  radios[0].listeners.change();
  check('chooser: picking icons keeps it with the settings', changes.length === 1 && changes[0].labels === 'icons');
  host.state.labels = 'both';
  radios = W.prototype.labelChooser.call(host).children.filter(function (c) { return c.tag === 'label'; }).map(function (l) { return l.children[0]; });
  check('chooser: the user\'s choice is checked', radios[1].checked === true && !radios[2].checked);

  // Choice takes effect at once: a changed choice sets data-labels and draws the tabs anew; the first draw does not
  function hostFor(labels) {
    var o = { attrs: {}, built: 0, tabButtons: [], state: { labels: labels }, cfg: { labels: 'icons' }, labelMode: W.prototype.labelMode };
    o.getAttribute = function (k) { return Object.prototype.hasOwnProperty.call(o.attrs, k) ? o.attrs[k] : null; };
    o.hasAttribute = function (k) { return Object.prototype.hasOwnProperty.call(o.attrs, k); };
    o.setAttribute = function (k, v) { o.attrs[k] = v; };
    o.buildTabs = function () { o.built++; };
    return o;
  }
  var ho = hostFor('');
  W.prototype.applyLabels.call(ho);
  check('applyLabels: the first draw sets the choice without drawing twice', ho.attrs['data-labels'] === 'icons' && ho.built === 0);
  ho.state.labels = 'text';
  W.prototype.applyLabels.call(ho);
  check('applyLabels: a changed choice draws the tabs at once', ho.attrs['data-labels'] === 'text' && ho.built === 1);
  W.prototype.applyLabels.call(ho);
  check('applyLabels: the same choice draws nothing', ho.built === 1);
}

console.log(failed ? 'FAIL ' + failed + ' failed' : 'PASS all');
process.exit(failed ? 1 : 0);
