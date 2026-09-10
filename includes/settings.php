<?php

/**
 * Returns the single settings row, seeding it with defaults if missing
 * (e.g. on a database created before the settings table existed).
 */
function get_settings(): array
{
    $db = get_db();
    $row = $db->query('SELECT * FROM settings WHERE id = 1')->fetch();
    if ($row === false) {
        $db->exec(
            "INSERT INTO settings (id, weekly_target_hours, surplus_rate_eur, goal_label, goal_amount_eur)
             VALUES (1, 12.00, 10.00, '', 0.00)"
        );
        $row = $db->query('SELECT * FROM settings WHERE id = 1')->fetch();
    }
    return $row;
}

function update_settings(float $weeklyTargetHours, float $surplusRateEur, string $goalLabel, float $goalAmountEur): void
{
    get_settings(); // ensure the row exists before updating it
    $stmt = get_db()->prepare(
        'UPDATE settings
         SET weekly_target_hours = :weekly_target_hours,
             surplus_rate_eur = :surplus_rate_eur,
             goal_label = :goal_label,
             goal_amount_eur = :goal_amount_eur
         WHERE id = 1'
    );
    $stmt->execute([
        ':weekly_target_hours' => $weeklyTargetHours,
        ':surplus_rate_eur' => $surplusRateEur,
        ':goal_label' => $goalLabel,
        ':goal_amount_eur' => $goalAmountEur,
    ]);
}
