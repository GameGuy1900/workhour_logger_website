CREATE TABLE IF NOT EXISTS entries (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    entry_date DATE NOT NULL,
    hours DECIMAL(4,2) NOT NULL,
    description TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_entry_date (entry_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Single-row table holding editable app settings (see settings.php).
CREATE TABLE IF NOT EXISTS settings (
    id TINYINT UNSIGNED NOT NULL,
    weekly_target_hours DECIMAL(5,2) NOT NULL DEFAULT 12.00,
    surplus_rate_eur DECIMAL(6,2) NOT NULL DEFAULT 10.00,
    goal_label VARCHAR(255) NOT NULL DEFAULT '',
    goal_amount_eur DECIMAL(9,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO settings (id, weekly_target_hours, surplus_rate_eur, goal_label, goal_amount_eur)
VALUES (1, 12.00, 10.00, '', 0.00);
