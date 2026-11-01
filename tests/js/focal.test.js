/*
 * The focal point and crop of an image field (expwt-panels.js, ExpwtFocal, E7): the live previews compute what the
 * kernel's expImageFocalPoint::plan() computes (the same cases as its PHPUnit test), and the marker, the crop box and
 * what Save sends. Run with Node (no packages): node tests/js/focal.test.js. Prints PASS or FAIL lines and exits 1
 * on a failure.
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
var sandbox = { window: win, document: {}, Date: Date, Math: Math, JSON: JSON, String: String, isNaN: isNaN, parseInt: parseInt, parseFloat: parseFloat };
vm.createContext(sandbox);
vm.runInContext(source, sandbox, { filename: 'expwt-panels.js' });
var F = win.ExpwtFocal;
var failed = 0;

function same(name, got, want) {
  var ok = JSON.stringify(got) === JSON.stringify(want);
  if (!ok) { failed++; }
  console.log((ok ? 'PASS ' : 'FAIL ') + name + (ok ? '' : ': ' + JSON.stringify(got) + ' != ' + JSON.stringify(want)));
}
function f(name, data) { return { name: name, data: data || [] }; }

/* centered on the focal point, inside the image */
var crop100 = [f('geometry/crop', ['100', '100', '0', '0'])];
same('centered', F.plan(crop100, 400, 200, { x: 0.5, y: 0.5 }).source, { x: 150, y: 50, width: 100, height: 100 });
var edge = F.plan(crop100, 400, 200, { x: 0.95, y: 0 });
same('at the edge the window stays inside', edge.source, { x: 300, y: 0, width: 100, height: 100 });
same('the focal point moves within it', edge.focal, { x: 0.8, y: 0 });
same('a window larger than the image', F.plan([f('geometry/crop', [500, 300, 7, 7])], 400, 200, { x: 0.9, y: 0.9 }).source, { x: 0, y: 0, width: 400, height: 200 });

/* scale, then crop (ezflow's pattern) */
var sc = F.plan([f('geometry/scalewidth', [200]), f('geometry/crop', [200, 50, 0, 0])], 800, 800, { x: 0.5, y: 0.75 });
same('scale then crop', sc.source, { x: 0, y: 500, width: 800, height: 200 });
same('scale then crop size', [sc.width, sc.height], [200, 50]);
[
  ['geometry/scale', [100, 100], [100, 50]], ['geometry/scaledownonly', [1000, 1000], [400, 200]], ['geometry/scaledownonly', [100, 100], [100, 50]],
  ['geometry/scalewidthdownonly', [800], [400, 200]], ['geometry/scaleheight', [100], [200, 100]], ['geometry/scaleexact', [30, 70], [30, 70]],
  ['geometry/scalepercent', [50, 25], [200, 50]], ['border', [4, 2], [408, 204]], ['colorspace/gray', [], [400, 200]]
].forEach(function (c) {
  var r = F.plan([f(c[0], c[1])], 400, 200, { x: 0.5, y: 0.5 });
  same('size after ' + c[0], [r.width, r.height], c[2]);
});

/* without a focal point the offsets stay; an unknown filter stops the plan */
same('no focal point: own offsets', F.plan([f('geometry/crop', [100, 100, 20, 30])], 400, 200, null).source, { x: 20, y: 30, width: 100, height: 100 });
same('unknown filter', F.plan([f('resize', ['50%']), f('geometry/crop', [100, 100, 0, 0])], 400, 200, { x: 0.9, y: 0.9 }).source, null);
same('no size', F.plan(crop100, 0, 0, { x: 0.5, y: 0.5 }).source, null);

/* the crop box first */
var box = { x: 0.5, y: 0.25, width: 0.5, height: 0.5 };
var cut = F.plan([f('geometry/scaledownonly', [100, 100])], 400, 200, null, box);
same('crop box cut first', cut.source, { x: 200, y: 50, width: 200, height: 100 });
same('crop box size', [cut.width, cut.height], [100, 50]);
same('focal point inside the cut', F.plan([f('geometry/crop', [50, 50, 0, 0])], 400, 200, { x: 0.75, y: 0.5 }, box).source, { x: 275, y: 75, width: 50, height: 50 });
same('reference chain from the original', F.plan([f('geometry/scaledownonly', [200, 200]), f('geometry/crop', [50, 50, 0, 0])], 800, 400,
  { x: 0.75, y: 0.25 }, { x: 0.5, y: 0, width: 0.5, height: 1 }).source, { x: 550, y: 50, width: 100, height: 100 });

/* the preview frame */
same('view', F.view(F.plan(crop100, 400, 200, { x: 0.5, y: 0.5 }), 400, 200), { ratio: 1, imageWidth: 400, left: -150, top: -50 });
same('no view without a source', F.view({ source: null }, 400, 200), null);

/* the marker and the box */
same('point from a pointer', F.point({ left: 10, top: 20, width: 200, height: 100 }, 60, 70), { x: 0.25, y: 0.5 });
same('point outside is clamped', F.point({ left: 10, top: 20, width: 200, height: 100 }, -5, 500), { x: 0, y: 1 });
same('nudge', F.nudge({ x: 0.5, y: 0.5 }, 0.01, -0.1), { x: 0.51, y: 0.4 });
same('nudge stays in the crop box', F.nudge({ x: 0.5, y: 0.5 }, 0.5, 0.5, box), { x: 1, y: 0.75 });
same('nudge into the crop box', F.nudge({ x: 0.1, y: 0.1 }, 0, 0, box), { x: 0.5, y: 0.25 });
same('move', F.move(box, -0.7, 0.1), { x: 0, y: 0.35, width: 0.5, height: 0.5 });
same('move stays inside', F.move(box, 0.4, 0.4), { x: 0.5, y: 0.5, width: 0.5, height: 0.5 });
same('resize se', F.resize(box, 'se', { x: 0.8, y: 0.6 }), { x: 0.5, y: 0.25, width: 0.3, height: 0.35 });
same('resize nw', F.resize(box, 'nw', { x: 0.2, y: 0 }), { x: 0.2, y: 0, width: 0.8, height: 0.75 });
same('resize never below the least size', F.resize(box, 'ne', { x: 0.1, y: 0.9 }), { x: 0.5, y: 0.74, width: 0.01, height: 0.01 });
same('grow', F.grow(box, 0.1, -0.1), { x: 0.5, y: 0.25, width: 0.5, height: 0.4 });
same('start', F.start(), { x: 0.1, y: 0.1, width: 0.8, height: 0.8 });
same('steps', [F.step('ArrowLeft', false), F.step('ArrowDown', true), F.step('Enter', false)], [[-0.01, 0], [0, 0.1], null]);

/* what Save sends */
same('save both', F.body({ x: 0.2, y: 0.3 }, box), { focal: { x: 0.2, y: 0.3 }, crop: box });
same('save none', F.body(null, null), { focal: null, crop: null });
same('the whole image is no crop', F.body(null, { x: 0, y: 0, width: 1, height: 1 }), { focal: null, crop: null });

console.log(failed ? 'FAIL ' + failed + ' failed' : 'PASS all');
process.exit(failed ? 1 : 0);
