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

function flat_settings(float $target, float $rate): array
{
    return [['effective_from' => '1970-01-01', 'weekly_target_hours' => $target, 'surplus_rate_eur' => $rate]];
}

// week_start: Wednesday should map back to Monday of that week.
assert_equal('2026-09-07', week_start('2026-09-09'), 'week_start finds Monday for a mid-week date');
assert_equal('2026-09-07', week_start('2026-09-07'), 'week_start is idempotent on a Monday');
assert_equal('2026-08-31', week_start('2026-09-06'), 'week_start finds Monday for a Sunday date');

// Single week, target met exactly.
$summaries = compute_weekly_summaries(
    [['entry_date' => '2026-09-08', 'hours' => 12]],
    flat_settings(12, 10),
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
$summaries = compute_weekly_summaries($entries, flat_settings(12, 10), '2026-09-20');
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
$summaries = compute_weekly_summaries($entries, flat_settings(12, 10), '2026-09-21');
assert_equal(3, count($summaries), 'gap weeks with no entries are still generated');
$week3 = $summaries[0];
assert_equal(36.0, $week3['required'], 'shortfalls compound across consecutive missed weeks');

// Surplus in a week does not reduce the following week's base target.
$entries = [
    ['entry_date' => '2026-09-07', 'hours' => 20], // 8 hours surplus
];
$summaries = compute_weekly_summaries($entries, flat_settings(12, 10), '2026-09-14');
$week1 = $summaries[1];
$week2 = $summaries[0];
assert_equal(8.0, $week1['surplus'], 'surplus is hours logged beyond the required amount');
assert_equal(12.0, $week2['required'], 'surplus hours do not roll forward');
assert_equal(0.0, $week2['surplus'], 'a week with no entries has zero surplus');

// A short week has zero surplus, even though it is short rather than over.
$entries = [
    ['entry_date' => '2026-09-07', 'hours' => 8],
];
$summaries = compute_weekly_summaries($entries, flat_settings(12, 10), '2026-09-07');
assert_equal(0.0, $summaries[0]['surplus'], 'a short week has zero surplus');

// Surplus pay is surplus hours times that week's rate.
$entries = [
    ['entry_date' => '2026-09-07', 'hours' => 20], // 8 hours surplus at €10/hr
];
$summaries = compute_weekly_summaries($entries, flat_settings(12, 10), '2026-09-07');
assert_equal(80.0, $summaries[0]['surplus_pay'], 'surplus pay = surplus hours * rate');

// --- settings_for_week: effective-dated lookup ---

$history = [
    ['effective_from' => '1970-01-01', 'weekly_target_hours' => 12, 'surplus_rate_eur' => 10],
    ['effective_from' => '2026-09-14', 'weekly_target_hours' => 20, 'surplus_rate_eur' => 15],
];
assert_equal(12.0, settings_for_week($history, '2026-09-07')['weekly_target_hours'], 'week before a change uses the old target');
assert_equal(20.0, settings_for_week($history, '2026-09-14')['weekly_target_hours'], 'the change\'s own effective week uses the new target');
assert_equal(20.0, settings_for_week($history, '2026-09-21')['weekly_target_hours'], 'weeks after a change keep using the new target');
assert_equal(12.0, settings_for_week($history, '1970-01-01')['weekly_target_hours'], 'the very first known week uses the fallback row');

// Unsorted input is handled the same way (defensive against callers).
$unsorted = [
    ['effective_from' => '2026-09-14', 'weekly_target_hours' => 20, 'surplus_rate_eur' => 15],
    ['effective_from' => '1970-01-01', 'weekly_target_hours' => 12, 'surplus_rate_eur' => 10],
];
assert_equal(12.0, settings_for_week($unsorted, '2026-09-07')['weekly_target_hours'], 'lookup does not depend on input order');

// --- compute_weekly_summaries: a mid-stream target change never touches past weeks ---

$history = [
    ['effective_from' => '1970-01-01', 'weekly_target_hours' => 12, 'surplus_rate_eur' => 10],
    ['effective_from' => '2026-09-14', 'weekly_target_hours' => 20, 'surplus_rate_eur' => 20],
];
$entries = [
    ['entry_date' => '2026-09-07', 'hours' => 12], // week 1: exactly met the OLD 12h target
    ['entry_date' => '2026-09-14', 'hours' => 20], // week 2: exactly met the NEW 20h target
];
$summaries = compute_weekly_summaries($entries, $history, '2026-09-14');
$week1 = $summaries[1];
$week2 = $summaries[0];
assert_equal(12.0, $week1['required'], 'a week before the change keeps its original required hours');
assert_equal(true, $week1['met'], 'a week before the change is judged against the original target');
assert_equal(10.0, $week1['surplus_rate_eur'], 'a week before the change keeps its original rate');
assert_equal(20.0, $week2['required'], 'a week on/after the change uses the new required hours');
assert_equal(20.0, $week2['surplus_rate_eur'], 'a week on/after the change uses the new rate');

echo "All tests passed.\n";
