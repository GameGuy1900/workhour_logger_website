<?php
require_once __DIR__ . '/../includes/weeks.php';

function assert_equal($expected, $actual, string $message): void
{
    if ($expected != $actual) {
        fwrite(STDERR, "FAIL: $message\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n");
        exit(1);
    }
    echo "PASS: $message\n";
}

// week_start: Wednesday should map back to Monday of that week.
assert_equal('2026-09-07', week_start('2026-09-09'), 'week_start finds Monday for a mid-week date');
assert_equal('2026-09-07', week_start('2026-09-07'), 'week_start is idempotent on a Monday');
assert_equal('2026-08-31', week_start('2026-09-06'), 'week_start finds Monday for a Sunday date');

// Single week, target met exactly.
$summaries = compute_weekly_summaries(
    [['entry_date' => '2026-09-08', 'hours' => 12]],
    12,
    '2026-09-08'
);
assert_equal(1, count($summaries), 'one week produces one summary');
assert_equal(true, $summaries[0]['met'], 'meeting target exactly counts as met');
assert_equal(0.0, $summaries[0]['difference'], 'no difference when exactly met');

// Shortfall carries into the following week.
$entries = [
    ['entry_date' => '2026-09-07', 'hours' => 8],  // week 1: 8/12, short 4
    ['entry_date' => '2026-09-14', 'hours' => 16], // week 2: 16/16 required, met
];
$summaries = compute_weekly_summaries($entries, 12, '2026-09-20');
assert_equal(2, count($summaries), 'two logged weeks produce two summaries');
$week2 = $summaries[0]; // reverse order: most recent first
$week1 = $summaries[1];
assert_equal('2026-09-07', $week1['week_start'], 'week 1 starts correctly');
assert_equal(false, $week1['met'], 'week 1 is short');
assert_equal(4.0, -$week1['difference'], 'week 1 short by 4 hours');
assert_equal('2026-09-14', $week2['week_start'], 'week 2 starts correctly');
assert_equal(16.0, $week2['required'], 'week 2 required includes carried-over 4 hours');
assert_equal(true, $week2['met'], 'week 2 meets the carried-over target');

// A skipped week (no entries at all) still accrues a full shortfall that
// compounds into the next week.
$entries = [
    ['entry_date' => '2026-09-07', 'hours' => 0],
];
$summaries = compute_weekly_summaries($entries, 12, '2026-09-21');
assert_equal(3, count($summaries), 'gap weeks with no entries are still generated');
$week3 = $summaries[0];
assert_equal(36.0, $week3['required'], 'shortfalls compound across consecutive missed weeks');

// Surplus in a week does not reduce the following week's base target.
$entries = [
    ['entry_date' => '2026-09-07', 'hours' => 20], // 8 hours surplus
];
$summaries = compute_weekly_summaries($entries, 12, '2026-09-14');
$week2 = $summaries[0];
assert_equal(12.0, $week2['required'], 'surplus hours do not roll forward');

echo "All tests passed.\n";
