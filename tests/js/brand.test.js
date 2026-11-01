/*
 * The branding of the siteaccess in the sidebar (expwt.js, ExpwtBrand, E10): the stylesheet of the accent tokens for
 * light and dark, only known tokens with color values, the name with its fallback and the logo address. Run with Node
 * (no packages): node tests/js/brand.test.js. Prints PASS or FAIL lines and exits 1 on a failure.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */
'use strict';
var fs = require('fs');
var path = require('path');
var vm = require('vm');

var win = { customElements: { get: function () { return undefined; }, define: function () {} } };
var sandbox = { window: win, document: {}, HTMLElement: function () {}, Document: function () {}, CSSStyleSheet: function () {}, Element: function () {} };
sandbox.HTMLElement.prototype = {};
vm.createContext(sandbox);
vm.runInContext(fs.readFileSync(path.join(__dirname, '../../design/standard/javascript/expwt.js'), 'utf8'), sandbox, { filename: 'expwt.js' });
var B = win.ExpwtBrand;

var failed = 0;
function same(name, got, want) {
  var ok = JSON.stringify(got) === JSON.stringify(want);
  if (!ok) { failed++; }
  console.log((ok ? 'PASS ' : 'FAIL ') + name + (ok ? '' : ': ' + JSON.stringify(got) + ' != ' + JSON.stringify(want)));
}

same('no branding: no stylesheet', B.css(undefined), '');
same('empty branding: no stylesheet', B.css({}), '');
same('light and dark blocks', B.css({ light: { '--a4-accent': '#1d3557', '--a4-accent-soft': 'rgba(29, 53, 87, 0.12)' }, dark: { '--a4-accent': '#8da4c4' } }),
  ':host{--a4-accent:#1d3557;--a4-accent-soft:rgba(29, 53, 87, 0.12)}:host([data-theme="dark"]){--a4-accent:#8da4c4}');
same('only known tokens, only colors', B.css({ light: { '--a4-accent': 'red;}body{x:y', '--a4-ink': '#000000', '--a4-ring': '#ABCDEF', '--a4-on-accent': '#ffffff' } }),
  ':host{--a4-on-accent:#ffffff}');
same('the name', B.name({ name: ' Acme ' }, 'Website sidebar'), 'Acme');
same('no name: the fallback', B.name({}, 'Website sidebar'), 'Website sidebar');
same('a path logo', B.logo({ logo: '/extension/acme/logo.svg' }), '/extension/acme/logo.svg');
same('an https logo', B.logo({ logo: 'https://cdn.example.com/l.png' }), 'https://cdn.example.com/l.png');
same('no protocol-relative logo', B.logo({ logo: '//evil.example/l.png' }), '');
same('no script logo', B.logo({ logo: 'javascript:alert(1)' }), '');
same('no quote in a logo', B.logo({ logo: '/l.png" onerror="x' }), '');

console.log(failed ? 'FAIL ' + failed : 'PASS all');
process.exit(failed ? 1 : 0);
