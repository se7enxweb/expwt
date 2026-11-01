/*
 * The Calendar panel's month grid and the moves it sends (expwt-panels.js, ExpwtCalendar, E6). Run with Node (no
 * packages): TZ=Europe/Berlin node tests/js/calendar.test.js (any zone works). Prints PASS or FAIL lines and exits 1 on
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
var sandbox = { window: win, document: {}, Date: Date, Math: Math, JSON: JSON, String: String, isNaN: isNaN };
vm.createContext(sandbox);
vm.runInContext(source, sandbox, { filename: 'expwt-panels.js' });
var C = win.ExpwtCalendar;
var failed = 0;

function same(name, got, want) {
  var ok = JSON.stringify(got) === JSON.stringify(want);
  if (!ok) { failed++; }
  console.log((ok ? 'PASS ' : 'FAIL ') + name + (ok ? '' : ': ' + JSON.stringify(got) + ' != ' + JSON.stringify(want)));
}

var g = C.grid(2026, 10); /* November 2026: the 1st is a Sunday */
same('six weeks', g.days.length, 42);
same('from the Monday before the 1st', g.days[0].key, '2026-10-26');
same('that Monday is not in the month', g.days[0].inMonth, false);
same('the 1st is the seventh day', [g.days[6].key, g.days[6].inMonth, g.days[6].weekday], ['2026-11-01', true, 6]);
same('the grid ends on a Sunday', g.days[41].key, '2026-12-06');
same('from is the start of the first day', g.from, Math.floor(new Date(2026, 9, 26).getTime() / 1000));
same('to is the last second of the last day', g.to, Math.floor(new Date(2026, 11, 7).getTime() / 1000) - 1);
var june = C.grid(2026, 5); /* June 2026 starts on a Monday */
same('a month that starts on Monday starts the grid', june.days[0].key, '2026-06-01');

var ts = Math.floor(new Date(2026, 10, 12, 9, 30, 0).getTime() / 1000);
same('the day of a time', C.dayOf(ts), '2026-11-12');
var moved = C.movedTo(ts, '2026-11-20');
same('the same time of day on another day', moved.slice(0, 19), '2026-11-20T09:30:00');
same('with the browser\'s offset', /[+-]\d{2}:\d{2}$/.test(moved), true);
same('and it is that moment', Math.floor(new Date(moved).getTime() / 1000), Math.floor(new Date(2026, 10, 20, 9, 30, 0).getTime() / 1000));
same('a typed date and time', C.fromLocal('2026-12-01T07:15').slice(0, 19), '2026-12-01T07:15:00');
same('nothing typed is nothing', C.fromLocal(''), '');
same('a date alone is not enough', C.fromLocal('2026-12-01'), '');

var items = [{ at: ts, key: 'a' }, { at: ts + 3600, key: 'b' }, { at: ts + 86400, key: 'c' }];
var by = C.byDay(items);
same('items by day', Object.keys(by).sort(), ['2026-11-12', '2026-11-13']);
same('two on the 12th', by['2026-11-12'].length, 2);

same('a draft moves by its version', [C.action({ kind: 'publish' }), C.input({ kind: 'publish', objectId: 7, version: 3, nodeId: 9 }, 'X')],
  ['calendar.publish', { objectId: 7, version: 3, at: 'X' }]);
same('a take-down moves by its page', [C.action({ kind: 'takedown' }), C.input({ kind: 'takedown', objectId: 7, nodeId: 9 }, 'X')],
  ['calendar.takedown', { nodeId: 9, at: 'X' }]);
same('a variant start', [C.action({ kind: 'variant' }), C.input({ kind: 'variant', conditionId: 4, which: 'start', ruleId: 2 }, 'X')],
  ['calendar.variant', { conditionId: 4, which: 'start', at: 'X' }]);
same('a variant end', C.input({ kind: 'variant-end', conditionId: 4, which: 'end' }, 'X'), { conditionId: 4, which: 'end', at: 'X' });

console.log(failed ? 'FAIL ' + failed + ' failed' : 'PASS all');
process.exit(failed ? 1 : 0);
