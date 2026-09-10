CREATE TABLE IF NOT EXISTS entries (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    entry_date DATE NOT NULL,
    hours DECIMAL(4,2) NOT NULL,
    description TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_entry_date (entry_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Single-row table holding the savings goal (not week-dependent).
CREATE TABLE IF NOT EXISTS settings (
    id TINYINT UNSIGNED NOT NULL,
    goal_label VARCHAR(255) NOT NULL DEFAULT '',
    goal_amount_eur DECIMAL(9,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO settings (id, goal_label, goal_amount_eur) VALUES (1, '', 0.00);

-- Effective-dated weekly target / surplus rate. Each row is a snapshot
-- that applies from `effective_from` (a Monday) onward, up to the next
-- row's effective_from. This lets you change these values without
-- retroactively changing already-completed weeks: saving a new value in
-- settings.php inserts/updates the row for the current week, so only
-- this week and future weeks pick up the change.
CREATE TABLE IF NOT EXISTS settings_history (
    effective_from DATE NOT NULL,
    weekly_target_hours DECIMAL(5,2) NOT NULL,
    surplus_rate_eur DECIMAL(6,2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (effective_from)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Fallback used for any week before the first real change you make.
INSERT IGNORE INTO settings_history (effective_from, weekly_target_hours, surplus_rate_eur)
VALUES ('1970-01-01', 12.00, 10.00);
