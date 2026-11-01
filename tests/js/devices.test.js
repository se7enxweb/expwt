/*
 * The device preview's pure parts (expwt-panels.js, ExpwtDevices, E8): how the frames fit the window, the width of a
 * frame of one's own, the address a frame shows, what this device remembers. Run with Node (no packages):
 * node tests/js/devices.test.js. Prints PASS or FAIL lines and exits 1 on a failure.
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
var sandbox = { window: win, document: {}, Intl: Intl, Date: Date };
vm.createContext(sandbox);
vm.runInContext(source, sandbox, { filename: 'expwt-panels.js' });
var D = win.ExpwtDevices;
var failed = 0;

function same(name, got, want) {
  var ok = JSON.stringify(got) === JSON.stringify(want);
  if (!ok) { failed++; }
  console.log((ok ? 'PASS ' : 'FAIL ') + name + (ok ? '' : ': ' + JSON.stringify(got) + ' != ' + JSON.stringify(want)));
}

var phone = { width: 390, height: 844 };
var pixel = { width: 412, height: 915 };
var zoom = { width: 960, height: 900 };
var desk = { width: 1440, height: 900 };
var tablet = { width: 820, height: 1180 };

same('nothing to fit', D.fit([], 1000, 800, 10), { row: true, scales: [] });
same('two phones on a wide window: one row, never larger than the device', D.fit([phone, pixel], 2000, 1200, 12), { row: true, scales: [1, 1] });
var r = D.fit([phone, pixel, zoom, desk], 1400, 700, 12);
same('four frames on a desktop window: one row at a common scale', r.row, true);
same('the row fits the width', (390 + 412 + 960 + 1440) * r.scales[0] + 3 * 12 <= 1400, true);
same('every frame of the row stays readable', 390 * r.scales[0] >= D.MIN, true);
r = D.fit([phone, pixel, tablet, zoom, desk], 1400, 700, 12);
same('five frames: too small in one row, so each on its own', r.row, false);
same('each as large as the window allows', r.scales, [0.829, 0.765, 0.593, 0.777, 0.777]);
r = D.fit([phone, desk], 358, 500, 12);
same('a phone window: each frame within its width', [r.row, 390 * r.scales[0] <= 358, 1440 * r.scales[1] <= 358], [false, true, true]);
same('a low window still gets frames TALL pixels high', D.fit([phone, desk], 358, 300, 12).scales, [0.663, 0.248]);
same('a tiny window still gives a scale above zero', D.fit([desk], 10, 10, 0).scales[0] > 0, true);

same('a width of one own', D.custom(' 1024 ', 240, 2560), 1024);
same('the limits are allowed', [D.custom('240', 240, 2560), D.custom(2560, 240, 2560)], [240, 2560]);
same('outside the limits, not a whole number or nothing', [D.custom('239', 240, 2560), D.custom('2561', 240, 2560), D.custom('10.5', 240, 2560),
  D.custom('1e3', 240, 2560), D.custom('', 240, 2560), D.custom(null, 240, 2560), D.custom('-400', 240, 2560)], [null, null, null, null, null, null, null]);

var urls = { published: '/site/a?in=sidebar', draft: '/site/content/versionview/7/3/eng-US?in=sidebar' };
same('the draft when chosen', D.src(urls, 'draft'), urls.draft);
same('the published page when chosen', D.src(urls, 'published'), urls.published);
same('the published page without a draft', D.src({ published: '/p', draft: null }, 'draft'), '/p');

var custom = { min: 240, max: 2560, height: 900 };
same('nothing remembered', D.remembered(null, ['a'], custom), { shown: null, widths: [], modes: {} });
same('only what is still there and valid', D.remembered({ shown: ['a', 'gone'], widths: [300, '300', 9999, 'x', 500, 600, 700, 800], modes: { a: 'dark', b: 'darker', w300: 'light' } }, ['a', 'b'], custom),
  { shown: ['a'], widths: [300, 500, 600, 700], modes: { a: 'dark', w300: 'light' } });
same('nothing shown is remembered as nothing', D.remembered({ shown: [] }, ['a'], custom).shown, []);

process.exit(failed ? 1 : 0);
