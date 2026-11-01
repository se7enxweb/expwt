/*
 * The accessibility check of the SEO panel (expwt-panels.js, ExpwtA11y, E11): axe-core's violations to one finding
 * per element, the worst first, with where it is; the block of a layout element and the template of a template
 * debug comment. Run with Node (no packages): node tests/js/a11y.test.js. Prints PASS or FAIL lines and exits 1 on
 * a failure.
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
var A = win.ExpwtA11y;

var failed = 0;
function same(name, got, want) {
  var ok = JSON.stringify(got) === JSON.stringify(want);
  if (!ok) { failed++; }
  console.log((ok ? 'PASS ' : 'FAIL ') + name + (ok ? '' : ': ' + JSON.stringify(got) + ' != ' + JSON.stringify(want)));
}

var violations = [
  { id: 'region', impact: 'moderate', help: 'All page content should be contained by landmarks', helpUrl: 'https://dequeuniversity.com/rules/axe/4.11/region',
    nodes: [{ target: ['.footer'] }] },
  { id: 'image-alt', impact: 'critical', help: 'Images must have alternative text', helpUrl: 'https://dequeuniversity.com/rules/axe/4.11/image-alt',
    nodes: [{ target: ['img.hero'] }, { target: ['#frame', 'img'] }] },
  { id: 'color-contrast', impact: 'serious', help: 'Elements must meet minimum color contrast ratio thresholds', helpUrl: 'javascript:alert(1)',
    nodes: [{ target: ['.teaser > a'], impact: 'minor' }] }
];
var where = function (sel) { return { field: sel === 'img.hero' ? 'image' : '' }; };
var f = A.findings(violations, where);
same('one finding per element, the worst first', f.map(function (x) { return x.id; }), ['image-alt-0', 'image-alt-1', 'region-0', 'color-contrast-0']);
same('the impact of the element wins', f[3].impact, 'minor');
same('where a single selector is', f[0].where, { field: 'image' });
same('a selector into a frame is not looked up', [f[1].target, f[1].where], [null, null]);
same('only the rule pages of axe-core are linked', [f[0].url, f[3].url], ['https://dequeuniversity.com/rules/axe/4.11/image-alt', '']);
same('nothing found', A.findings([], where), []);
same('not an answer', A.findings(null, where), []);
same('an unknown impact counts as minor', A.findings([{ id: 'x', impact: 'odd', nodes: [{ target: ['p'] }] }])[0].impact, 'minor');
var many = [{ id: 'm', impact: 'minor', nodes: [] }];
for (var i = 0; i < 250; i++) { many[0].nodes.push({ target: ['p:nth-child(' + i + ')'] }); }
same('at most LIMIT findings', A.findings(many).length, A.LIMIT);
same('the WCAG 2.2 AA tags', A.TAGS, ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']);
same('the block of a layout element', A.block('ngl-block ngl-tpl_block ngl-vt-tpl_block'), 'tpl_block');
same('no block', A.block('teaser'), '');
same('the template of a debug comment', A.template(' START: including template: extension/x/design/y/templates/line/article.tpl (design:line/article.tpl) '),
  'extension/x/design/y/templates/line/article.tpl');
same('another comment', A.template(' exp-field data-exp-field="12" '), '');

console.log(failed ? 'FAIL ' + failed : 'PASS all');
process.exit(failed ? 1 : 0);
