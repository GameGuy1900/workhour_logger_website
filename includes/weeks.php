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
 * Builds one summary row per week from the first logged week through the
 * current week, carrying any shortfall forward as extra required hours
 * on the following week.
 *
 * @param array $entries Rows with 'entry_date' (Y-m-d) and 'hours' (float).
 * @param float $baseTarget Base weekly target hours (e.g. 12).
 * @param string $today Y-m-d, injectable for testing.
 * @return array List of ['week_start', 'week_end', 'logged', 'required', 'difference', 'met']
 *               ordered oldest week first.
 */
function compute_weekly_summaries(array $entries, float $baseTarget, string $today): array
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
        $required = $baseTarget + $carry;
        $difference = $logged - $required;
        $met = $difference >= 0;

        $summaries[] = [
            'week_start' => $ws,
            'week_end' => $weekEnd,
            'logged' => $logged,
            'required' => $required,
            'difference' => $difference,
            'met' => $met,
        ];

        $carry = $met ? 0.0 : -$difference;
        $cursor->modify('+7 days');
    }

    return array_reverse($summaries);
}
