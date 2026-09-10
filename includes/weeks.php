<?php

/**
 * Returns the Monday (as Y-m-d) of the week containing $date.
 */
function week_start(string $date): string
{
    $dt = new DateTime($date);
    $dayOfWeek = (int) $dt->format('N'); // 1 (Mon) .. 7 (Sun)
    $dt->modify('-' . ($dayOfWeek - 1) . ' days');
    return $dt->format('Y-m-d');
}

/**
 * Finds the settings snapshot in effect for a given week.
 *
 * @param array $settingsHistory Rows with 'effective_from' (Y-m-d),
 *              'weekly_target_hours' (float), 'surplus_rate_eur' (float), in any order.
 * @param string $weekStart Y-m-d Monday of the week to look up.
 * @return array The row with the latest 'effective_from' <= $weekStart, or the
 *               earliest row if $weekStart predates all of them.
 */
function settings_for_week(array $settingsHistory, string $weekStart): array
{
    usort($settingsHistory, fn($a, $b) => strcmp($a['effective_from'], $b['effective_from']));

    $applicable = $settingsHistory[0];
    foreach ($settingsHistory as $row) {
        if ($row['effective_from'] <= $weekStart) {
            $applicable = $row;
        }
    }
    return $applicable;
}

/**
 * Builds one summary row per week from the first logged week through the
 * current week, carrying any shortfall forward as extra required hours
 * on the following week. Each week's target/rate come from whichever
 * settings_history snapshot was in effect for that week, so changing
 * settings today never changes the numbers for already-completed weeks.
 *
 * @param array $entries Rows with 'entry_date' (Y-m-d) and 'hours' (float).
 * @param array $settingsHistory See settings_for_week().
 * @param string $today Y-m-d, injectable for testing.
 * @return array List of ['week_start', 'week_end', 'logged', 'weekly_target_hours',
 *               'surplus_rate_eur', 'required', 'difference', 'met', 'surplus', 'surplus_pay']
 *               ordered oldest week first. 'surplus' is hours logged beyond that week's
 *               required hours (0 when the week fell short); it is never carried forward.
 */
function compute_weekly_summaries(array $entries, array $settingsHistory, string $today): array
{
    $loggedByWeek = [];
    foreach ($entries as $entry) {
        $ws = week_start($entry['entry_date']);
        $loggedByWeek[$ws] = ($loggedByWeek[$ws] ?? 0) + (float) $entry['hours'];
    }

    $currentWeekStart = week_start($today);

    $weekStarts = array_keys($loggedByWeek);
    $weekStarts[] = $currentWeekStart;
    sort($weekStarts);
    $firstWeekStart = $weekStarts[0];

    $summaries = [];
    $carry = 0.0;
    $cursor = new DateTime($firstWeekStart);
    $end = new DateTime($currentWeekStart);

    while ($cursor <= $end) {
        $ws = $cursor->format('Y-m-d');
        $weekEnd = (clone $cursor)->modify('+6 days')->format('Y-m-d');
        $logged = $loggedByWeek[$ws] ?? 0.0;

        $weekSettings = settings_for_week($settingsHistory, $ws);
        $weeklyTargetHours = (float) $weekSettings['weekly_target_hours'];
        $surplusRateEur = (float) $weekSettings['surplus_rate_eur'];

        $required = $weeklyTargetHours + $carry;
        $difference = $logged - $required;
        $met = $difference >= 0;
        $surplus = $met ? $difference : 0.0;

        $summaries[] = [
            'week_start' => $ws,
            'week_end' => $weekEnd,
            'logged' => $logged,
            'weekly_target_hours' => $weeklyTargetHours,
            'surplus_rate_eur' => $surplusRateEur,
            'required' => $required,
            'difference' => $difference,
            'met' => $met,
            'surplus' => $surplus,
            'surplus_pay' => $surplus * $surplusRateEur,
        ];

        $carry = $met ? 0.0 : -$difference;
        $cursor->modify('+7 days');
    }

    return array_reverse($summaries);
}
