/*
 * The rules of the SEO check (expwt-panels.js, ExpwtSeo.rules(), F26): what the rendered page shows to findings.
 * Run with Node (no packages): node tests/js/seo.test.js (expwtPureTest runs it when Node is there). Prints PASS or
 * FAIL lines and exits 1 on a failure.
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
var S = win.ExpwtSeo;

var failed = 0;
function check(name, ok) {
  console.log((ok ? 'PASS ' : 'FAIL ') + name);
  if (!ok) { failed++; }
}
function find(list, id) { return list.filter(function (f) { return f.id === id; })[0] || null; }

var good = {
  title: 'You Should Eat More Vegetables, Every Single Day', description: 'Why vegetables help: fibre, vitamins and less sugar, with five recipes that take ten minutes each.',
  robots: 'index, follow', canonical: ['https://example.org/news/vegetables'], here: 'https://example.org/news/vegetables',
  headings: [1, 2, 3, 2], images: [{ alt: 'A bowl of soup' }, { alt: '' }], links: [{ path: '/news', status: 'ok' }, { path: '/x.pdf', status: 'skipped' }]
};
check('the helper is there', !!S && typeof S.rules === 'function');
var r = S.rules(good);
check('a good page has no finding to look at', r.every(function (f) { return f.level === 'ok'; }));
check('a decorative image (alt="") is fine', !r.some(function (f) { return f.id.indexOf('alt-') === 0; }));
check('the links line counts the links checked (not the skipped)', find(r, 'links').text === 'All 1 links checked on this page lead somewhere.');

var bad = S.rules({ title: '', description: '', robots: 'noindex,nofollow', canonical: [], here: 'https://example.org/a',
  headings: [2, 4, 1, 1], images: [{ alt: null }, { alt: 'x' }], links: [{ path: '/gone', status: 'broken' }] });
check('no title', find(bad, 'title').level === 'bad' && find(bad, 'title').ref.field === 'title');
check('no description: the metadata field', find(bad, 'description').level === 'bad' && find(bad, 'description').ref.field === 'meta');
check('two main headings: the second one', find(bad, 'h1').level === 'warn' && find(bad, 'h1').ref.heading === 3);
check('a heading jumps from 2 to 4', find(bad, 'order-1').text === 'A heading jumps from level 2 to level 4.' && find(bad, 'order-1').ref.heading === 1);
check('an image without alt: that image', find(bad, 'alt-0').level === 'bad' && find(bad, 'alt-0').ref.image === 0 && !find(bad, 'alt-1'));
check('no canonical address', find(bad, 'canonical').level === 'warn');
check('noindex is a problem', find(bad, 'robots').level === 'bad' && find(bad, 'robots').text.indexOf('noindex,nofollow') > 0);
check('a broken link: that link', find(bad, 'link-0').level === 'bad' && find(bad, 'link-0').ref.link === 0 && find(bad, 'link-0').text === 'A link leads nowhere: /gone');
check('problems first, then the rest', bad.map(function (f) { return f.level; }).join(',').indexOf('bad,warn') < 0
  || bad.findIndex(function (f) { return f.level === 'warn'; }) > bad.map(function (f) { return f.level; }).lastIndexOf('bad'));

var lengths = S.rules({ title: 'Short', description: 'Short too', canonical: ['https://example.org/other'], here: 'https://example.org/a', headings: [], images: [], links: [] });
check('a short title is to look at, with its length', find(lengths, 'title').level === 'warn' && find(lengths, 'title').text.indexOf(' 5 ') > 0);
check('a short description is to look at', find(lengths, 'description').level === 'warn');
check('no heading at all', find(lengths, 'h1').level === 'bad' && find(lengths, 'h1').ref === null);
check('a canonical address of another page', find(lengths, 'canonical').text === 'The canonical address is another page: https://example.org/other');
check('the same page with a trailing slash is this page', find(S.rules({ canonical: ['https://e.org/a/'], here: 'https://e.org/a' }), 'canonical').level === 'ok');
var tr = S.rules({ title: '' }, function (s) { return s === 'The page has no title.' ? 'Die Seite hat keinen Titel.' : s; });
check('the texts are translated', find(tr, 'title').text === 'Die Seite hat keinen Titel.');

console.log(failed ? 'FAIL ' + failed : 'PASS the rules of the SEO check');
process.exit(failed ? 1 : 0);
