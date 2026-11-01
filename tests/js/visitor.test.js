/*
 * Visitor accessibility (expwt.js, ExpwtVisitor, E15): what is kept of a visitor's choices (anything else falls back
 * to the site's own look), the attributes on <html> that switch the rules on, and the stylesheet: every rule waits
 * for its attribute, the text sizes scale the site's own root size, the colors are tokens with light and dark values,
 * the sidebar and images are left alone. Run with Node (no packages): node tests/js/visitor.test.js. Prints PASS or
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

var source = fs.readFileSync(path.join(__dirname, '../../design/standard/javascript/expwt.js'), 'utf8');
var win = { customElements: { get: function () { return undefined; }, define: function () {} } };
var sandbox = { window: win, document: {}, HTMLElement: function () {}, Document: function () {}, CSSStyleSheet: function () {}, Element: function () {} };
sandbox.HTMLElement.prototype = {};
vm.createContext(sandbox);
vm.runInContext(source, sandbox, { filename: 'expwt.js' });
var V = win.ExpwtVisitor;
var failed = 0;

function same(name, got, want) {
  var ok = JSON.stringify(got) === JSON.stringify(want);
  if (!ok) { failed++; }
  console.log((ok ? 'PASS ' : 'FAIL ') + name + (ok ? '' : ': ' + JSON.stringify(got) + ' != ' + JSON.stringify(want)));
}
function check(name, ok) { same(name, !!ok, true); }

var plain = { size: 100, contrast: false, motion: false, font: false, underline: false };
same('nothing stored: the site\'s own look', V.normalize(null), plain);
same('unknown sizes and values fall back', V.normalize({ size: 300, contrast: 'yes', motion: 1, extra: true }), plain);
same('a stored choice is kept', V.normalize({ size: '150', contrast: true, underline: true }),
  { size: 150, contrast: true, motion: false, font: false, underline: true });
check('the default is told apart', V.isDefault(plain) && !V.isDefault(V.normalize({ font: true })) && !V.isDefault(V.normalize({ size: 125 })));
same('no attributes for the default', V.attrs(plain), { text: null, a11y: null });
same('the attributes of a choice', V.attrs(V.normalize({ size: 200, contrast: true, motion: true, font: true, underline: true })),
  { text: '200', a11y: 'contrast motion font underline' });

var css = V.css(10);
check('the text sizes scale the site\'s root size', css.indexOf('html[data-expwt-text="125"]{font-size:12.5px!important}') >= 0
  && css.indexOf('html[data-expwt-text="200"]{font-size:20px!important}') >= 0 && css.indexOf('data-expwt-text="100"') < 0);
check('16 px when the root size cannot be read', V.css(0).indexOf('html[data-expwt-text="150"]{font-size:24px!important}') >= 0);
var rules = css.replace(/@media[^{]*\{([^{}]*\{[^}]*\})\}/g, '$1').match(/[^{}]+\{[^}]*\}/g);
var free = rules.filter(function (r) {
  var sel = r.slice(0, r.indexOf('{'));
  return !/data-expwt-(a11y|text)/.test(sel) && !/^(:root|html\[data-exp-theme="dark"\]|html:not\(\[data-exp-theme="light"\]\))$/.test(sel.trim());
});
same('every rule waits for its attribute (only the tokens do not)', free, []);
check('the colors are tokens with dark values', /:root\{--expwt-a11y-fg:#000;--expwt-a11y-bg:#fff/.test(css)
  && /html\[data-exp-theme="dark"\]\{--expwt-a11y-fg:#fff;--expwt-a11y-bg:#000/.test(css)
  && /@media \(prefers-color-scheme:dark\)\{html:not\(\[data-exp-theme="light"\]\)\{--expwt-a11y-fg:#fff/.test(css));
check('high contrast leaves the sidebar, images and video alone', /contrast"\] body :where\(\*:not\(exp-wt\):not\(img\):not\(video\)/.test(css));
check('the readable font leaves code and icon glyphs alone', /font"\] body \*:not\(exp-wt\):not\(code\)[^{]*:not\(\[class\*="icon"\]\)/.test(css));
check('reduced motion stops animations and transitions', /motion"\] \*[^{]*\{animation-duration:1ms!important;animation-iteration-count:1!important/.test(css));
check('links are underlined', /underline"\] a\[href\]\{text-decoration-line:underline!important/.test(css));
check('the colors come from the tokens only', !/(color|background):#/.test(css.replace(/--expwt-a11y-[a-z]+:#[0-9a-f]+/g, '')));

process.exit(failed ? 1 : 0);
