<?php
require_once __DIR__ . '/../includes/duration.php';

function assert_equal($expected, $actual, string $message): void
{
    if ($expected != $actual) {
        fwrite(STDERR, "FAIL: $message\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n");
        exit(1);
    }
    echo "PASS: $message\n";
}

// parse_time_to_minutes: valid and invalid formats.
assert_equal(240, parse_time_to_minutes('04:00'), 'parses HH:MM into minutes since midnight');
assert_equal(0, parse_time_to_minutes('00:00'), 'parses midnight as zero minutes');
assert_equal(null, parse_time_to_minutes('24:00'), 'rejects an hour outside 00-23');
assert_equal(null, parse_time_to_minutes('9:00'), 'rejects a missing leading zero');
assert_equal(null, parse_time_to_minutes(''), 'rejects an empty string');

// compute_duration_hours: same-day and overnight shifts.
assert_equal(4.0, compute_duration_hours('09:00', '13:00'), 'same-day shift computes a simple difference');
assert_equal(8.0, compute_duration_hours('22:00', '06:00'), 'end time before start time is treated as crossing midnight');
assert_equal(0.25, compute_duration_hours('23:45', '00:00'), 'a short shift just past midnight still computes correctly');
assert_equal(23.75, compute_duration_hours('00:15', '00:00'), 'end time one quarter-hour before start wraps to nearly 24 hours');

// Identical start/end time is ambiguous (0h vs 24h) and must be rejected.
try {
    compute_duration_hours('09:00', '09:00');
    fwrite(STDERR, "FAIL: identical start and end time should be rejected\n");
    exit(1);
} catch (InvalidArgumentException $e) {
    echo "PASS: identical start and end time throws\n";
}

// Invalid formats must be rejected with a clear exception, not a silent 0.
try {
    compute_duration_hours('9:00', '13:00');
    fwrite(STDERR, "FAIL: an invalid time format should be rejected\n");
    exit(1);
} catch (InvalidArgumentException $e) {
    echo "PASS: invalid time format throws\n";
}

echo "All tests passed.\n";
