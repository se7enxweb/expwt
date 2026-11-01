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
same('nothing remembered', D.remembered(null, ['a'], custom), { shown: null, widths: [], hidden: [], modes: {} });
same('only what is still there and valid', D.remembered({ shown: ['a', 'gone'], widths: [300, '300', 9999, 'x', 500, 600, 700, 800], modes: { a: 'dark', b: 'darker', w300: 'light' } }, ['a', 'b'], custom),
  { shown: ['a'], widths: [300, 500, 600, 700], hidden: [], modes: { a: 'dark', w300: 'light' } });
same('nothing shown is remembered as nothing', D.remembered({ shown: [] }, ['a'], custom).shown, []);
same('the widths hidden: only widths there are, each once', D.remembered({ widths: [300, 500], hidden: [500, '500', 700, 'x', 300] }, ['a'], custom).hidden, [500, 300]);
same('no widths hidden remembered (a choice of before)', D.remembered({ shown: ['a'], widths: [300] }, ['a'], custom).hidden, []);

/* the toggles of the frames shown */
var ids = ['iphone13', 'pixel7', 'tablet', 'zoom200', 'desktop'];
var all = D.all(ids);
same('show all: every device, no width hidden', all, { shown: ids, hidden: [] });
same('show all is a copy', all.shown !== ids, true);
var c = D.flip(all, { id: 'tablet' }, ids);
same('a device hidden', c, { shown: ['iphone13', 'pixel7', 'zoom200', 'desktop'], hidden: [] });
same('the choice before stays as it was', all.shown.length, 5);
same('shown again, in the order of the settings', D.flip(D.flip(c, { id: 'iphone13' }, ids), { id: 'iphone13' }, ids).shown, ['iphone13', 'pixel7', 'zoom200', 'desktop']);
same('a device shown again keeps its place', D.flip(c, { id: 'tablet' }, ids).shown, ids);
c = D.flip(c, { width: 1024 }, ids);
same('a width of one own hidden', c.hidden, [1024]);
same('and shown again', D.flip(c, { width: 1024 }, ids).hidden, []);
same('not all shown', D.allShown(c, ids, [1024]), false);
same('all shown', D.allShown(D.all(ids), ids, [1024]), true);
same('a width hidden counts only while it is there', D.allShown({ shown: ids, hidden: [1024] }, ids, []), true);
same('the frames visible: devices first, then the widths', D.visible({ shown: ['desktop', 'iphone13'], hidden: [500] }, ids, [300, 500, 700]),
  [{ id: 'iphone13' }, { id: 'desktop' }, { width: 300 }, { width: 700 }]);
same('nothing visible', D.visible({ shown: [], hidden: [300] }, ids, [300]), []);
same('all hidden, show all brings them all back', D.visible(D.all(ids), ids, [300]).length, 6);
var fitted = D.fit([{ width: 390, height: 844 }, { width: 1440, height: 900 }], 1400, 700, 12);
same('fewer frames are larger: two of five shown fit one row at a larger scale', fitted.row && fitted.scales[0] > D.fit([phone, pixel, zoom, desk], 1400, 700, 12).scales[0], true);

process.exit(failed ? 1 : 0);
