<?php

/**
 * Parses an "HH:MM" time string into minutes since midnight, or null if
 * the format doesn't match (e.g. empty string, missing leading zero).
 */
function parse_time_to_minutes(string $time): ?int
{
    if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time, $m)) {
        return null;
    }
    return ((int) $m[1]) * 60 + (int) $m[2];
}

/**
 * Computes the duration in hours between a start and end "HH:MM" time,
 * treating an end time before the start time as crossing midnight (e.g.
 * 22:00 -> 06:00 is an 8-hour night shift). Identical start and end times
 * are rejected rather than guessed, since that's ambiguous between 0 hours
 * and a full 24-hour shift.
 *
 * @throws InvalidArgumentException if either time is not a valid "HH:MM"
 *         string, or if start and end are identical.
 */
function compute_duration_hours(string $startTime, string $endTime): float
{
    $start = parse_time_to_minutes($startTime);
    $end = parse_time_to_minutes($endTime);

    if ($start === null || $end === null) {
        throw new InvalidArgumentException('Start and end time must be in HH:MM format.');
    }
    if ($start === $end) {
        throw new InvalidArgumentException('Start and end time cannot be the same.');
    }
    if ($end < $start) {
        $end += 24 * 60;
    }

    return ($end - $start) / 60;
}
