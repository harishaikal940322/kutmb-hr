import { strict as assert } from 'node:assert';
import { calendarGuidance } from '../../resources/js/pages/leave/calendarGuidance.ts';

const guide = (date, days = [], end = '2026-12-31', kind = 'holiday') =>
    calendarGuidance(
        date,
        kind,
        days.map((value) => ({ date: value })),
        '2026-01-01',
        end,
    ).join(' ');
assert.match(guide('2026-03-01'), /2026-03-02/);
assert.match(guide('2026-03-01', ['2026-03-02']), /2026-03-03/);
assert.match(
    guide('2026-03-01', ['2026-03-02', '2026-03-03']),
    /tidak tersedia/,
);
assert.match(guide('2026-03-01', [], '2026-03-01'), /tidak tersedia/);
assert.match(guide('2026-02-28'), /Sabtu/);
assert.doesNotMatch(guide('2026-02-28'), /Cadangan untuk/);
assert.match(guide('2026-03-02', ['2026-03-02']), /telah wujud/);
assert.doesNotMatch(
    guide('2026-03-01', [], '2026-12-31', 'replacement'),
    /Cadangan untuk/,
);
assert.equal(guide(''), '');
