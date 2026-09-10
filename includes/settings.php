<?php

/**
 * Returns the savings goal row (label + price), seeding it with defaults
 * if missing (e.g. on a database created before this table existed).
 */
function get_goal_settings(): array
{
    $db = get_db();
    $row = $db->query('SELECT * FROM settings WHERE id = 1')->fetch();
    if ($row === false) {
        $db->exec("INSERT INTO settings (id, goal_label, goal_amount_eur) VALUES (1, '', 0.00)");
        $row = $db->query('SELECT * FROM settings WHERE id = 1')->fetch();
    }
    return $row;
}

function update_goal_settings(string $goalLabel, float $goalAmountEur): void
{
    get_goal_settings(); // ensure the row exists before updating it
    $stmt = get_db()->prepare(
        'UPDATE settings SET goal_label = :goal_label, goal_amount_eur = :goal_amount_eur WHERE id = 1'
    );
    $stmt->execute([
        ':goal_label' => $goalLabel,
        ':goal_amount_eur' => $goalAmountEur,
    ]);
}

/**
 * Returns every settings_history row (weekly target + surplus rate over
 * time), seeding a default fallback row if the table is empty.
 */
function get_settings_history(): array
{
    $db = get_db();
    $rows = $db->query('SELECT * FROM settings_history ORDER BY effective_from ASC')->fetchAll();
    if (empty($rows)) {
        $db->exec(
            "INSERT INTO settings_history (effective_from, weekly_target_hours, surplus_rate_eur)
             VALUES ('1970-01-01', 12.00, 10.00)"
        );
        $rows = $db->query('SELECT * FROM settings_history ORDER BY effective_from ASC')->fetchAll();
    }
    return $rows;
}

/**
 * Returns the weekly target / surplus rate currently in effect (i.e. for
 * the week containing $today), for pre-filling the settings form.
 */
function get_current_settings(string $today): array
{
    return settings_for_week(get_settings_history(), week_start($today));
}

/**
 * Records a change to the weekly target / surplus rate, effective from
 * the given week onward. Saving again within the same week updates that
 * week's row rather than creating a duplicate, so only one snapshot per
 * week ever exists. Weeks before $effectiveFrom keep whatever snapshot
 * was already in effect for them.
 */
function save_settings_change(string $effectiveFrom, float $weeklyTargetHours, float $surplusRateEur): void
{
    $stmt = get_db()->prepare(
        'INSERT INTO settings_history (effective_from, weekly_target_hours, surplus_rate_eur)
         VALUES (:effective_from, :weekly_target_hours, :surplus_rate_eur)
         ON DUPLICATE KEY UPDATE
             weekly_target_hours = VALUES(weekly_target_hours),
             surplus_rate_eur = VALUES(surplus_rate_eur)'
    );
    $stmt->execute([
        ':effective_from' => $effectiveFrom,
        ':weekly_target_hours' => $weeklyTargetHours,
        ':surplus_rate_eur' => $surplusRateEur,
    ]);
}
