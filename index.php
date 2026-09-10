<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/weeks.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/assets.php';

$settingsHistory = get_settings_history();
$goalSettings = get_goal_settings();
$goalLabel = $goalSettings['goal_label'];
$goalAmountEur = (float) $goalSettings['goal_amount_eur'];

$entries = get_db()
    ->query('SELECT id, entry_date, hours, description FROM entries ORDER BY entry_date DESC, id DESC')
    ->fetchAll();

$today = (new DateTime())->format('Y-m-d');
$weeklySummaries = compute_weekly_summaries($entries, $settingsHistory, $today);
$currentWeek = $weeklySummaries[0];
$pastWeeks = array_slice($weeklySummaries, 1);

$totalSurplusHours = array_sum(array_column($weeklySummaries, 'surplus'));
$totalSurplusPay = array_sum(array_column($weeklySummaries, 'surplus_pay'));

$goalPaid = $goalAmountEur > 0 ? min($totalSurplusPay, $goalAmountEur) : 0.0;
$goalPercent = $goalAmountEur > 0 ? min(100, $totalSurplusPay / $goalAmountEur * 100) : 0.0;
$goalRemaining = $goalAmountEur > 0 ? max(0, $goalAmountEur - $totalSurplusPay) : 0.0;
$goalExtra = $goalAmountEur > 0 ? max(0, $totalSurplusPay - $goalAmountEur) : 0.0;

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Work Hour Logger</title>
<link rel="stylesheet" href="<?= h(asset_url('style.css')) ?>">
<script>
(function () {
    try {
        var stored = localStorage.getItem('theme');
        if (stored === 'dark' || stored === 'light') {
            document.documentElement.setAttribute('data-theme', stored);
        }
    } catch (e) {}
})();
</script>
</head>
<body>
<div class="container">
    <div class="top-bar">
        <h1>Work Hour Logger</h1>
        <button type="button" id="theme-toggle" class="theme-toggle">&#127769; Dark mode</button>
    </div>
    <p class="subtitle">
        Log your hours each day. Target: <?= h(number_format($currentWeek['weekly_target_hours'], 2)) ?> hours/week &mdash;
        any shortfall carries into next week.
        <a href="settings.php" class="settings-link">Settings</a>
    </p>

    <div class="card">
        <h2>This week (<?= h($currentWeek['week_start']) ?> &ndash; <?= h($currentWeek['week_end']) ?>)</h2>
        <div class="current-week">
            <span><?= h(number_format($currentWeek['logged'], 2)) ?> / <?= h(number_format($currentWeek['required'], 2)) ?> hours</span>
            <span class="<?= $currentWeek['met'] ? 'status-met' : 'status-short' ?>">
                <?= $currentWeek['met']
                    ? 'Target met (+' . h(number_format($currentWeek['difference'], 2)) . ')'
                    : h(number_format(-$currentWeek['difference'], 2)) . ' hours short' ?>
            </span>
        </div>
        <div class="progress-bar">
            <div class="progress-bar-fill <?= $currentWeek['met'] ? 'met' : '' ?>"
                 style="width: <?= min(100, $currentWeek['required'] > 0 ? ($currentWeek['logged'] / $currentWeek['required'] * 100) : 100) ?>%"></div>
        </div>
        <?php if ($currentWeek['surplus'] > 0): ?>
        <p class="surplus-note">
            <?= h(number_format($currentWeek['surplus'], 2)) ?> surplus hours
            &times; &euro;<?= h(number_format($currentWeek['surplus_rate_eur'], 2)) ?>/hr
            = &euro;<?= h(number_format($currentWeek['surplus_pay'], 2)) ?>
        </p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Surplus pay</h2>
        <p>Surplus hours are hours logged beyond a week's required hours, paid at that week's rate (currently &euro;<?= h(number_format($currentWeek['surplus_rate_eur'], 2)) ?>/hour).</p>
        <div class="current-week">
            <span><?= h(number_format($totalSurplusHours, 2)) ?> surplus hours total</span>
            <span class="status-met">&euro;<?= h(number_format($totalSurplusPay, 2)) ?></span>
        </div>
    </div>

    <?php if ($goalAmountEur > 0): ?>
    <div class="card">
        <h2><?= $goalLabel !== '' ? h($goalLabel) : 'Savings goal' ?></h2>
        <div class="current-week">
            <span>&euro;<?= h(number_format($goalPaid, 2)) ?> / &euro;<?= h(number_format($goalAmountEur, 2)) ?> paid off</span>
            <span class="<?= $goalPercent >= 100 ? 'status-met' : '' ?>">
                <?= $goalPercent >= 100 ? 'Paid off!' : h(number_format($goalPercent, 0)) . '%' ?>
            </span>
        </div>
        <div class="progress-bar">
            <div class="progress-bar-fill <?= $goalPercent >= 100 ? 'met' : '' ?>" style="width: <?= $goalPercent ?>%"></div>
        </div>
        <?php if ($goalPercent < 100): ?>
            <p class="surplus-note">&euro;<?= h(number_format($goalRemaining, 2)) ?> left to go.</p>
        <?php elseif ($goalExtra > 0): ?>
            <p class="surplus-note">&euro;<?= h(number_format($goalExtra, 2)) ?> surplus pay left over after paying it off.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="card">
        <h2>Log a day</h2>
        <form class="entry-form" action="add_entry.php" method="post">
            <div>
                <label for="date">Date</label>
                <input type="date" id="date" name="date" value="<?= h($today) ?>" required>
            </div>
            <div>
                <label for="hours">Hours</label>
                <input type="number" id="hours" name="hours" step="0.25" min="0.25" max="24" required>
            </div>
            <div>
                <label for="description">What did you do?</label>
                <textarea id="description" name="description" required></textarea>
            </div>
            <button type="submit">Add entry</button>
        </form>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>Weekly history</h2>
            <?php if (!empty($pastWeeks)): ?>
            <label class="row-limit">
                Show
                <select data-row-limit data-target="weekly-history-table">
                    <option value="10" selected>10</option>
                    <option value="20">20</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </label>
            <?php endif; ?>
        </div>
        <?php if (empty($pastWeeks)): ?>
            <p class="empty-state">No previous weeks yet.</p>
        <?php else: ?>
        <div class="table-scroll">
        <table id="weekly-history-table">
            <thead>
                <tr><th>Week</th><th>Logged</th><th>Required</th><th>Status</th><th>Surplus</th><th>Rate</th><th>Pay</th></tr>
            </thead>
            <tbody>
            <?php foreach ($pastWeeks as $week): ?>
                <tr>
                    <td><?= h($week['week_start']) ?> &ndash; <?= h($week['week_end']) ?></td>
                    <td><?= h(number_format($week['logged'], 2)) ?></td>
                    <td><?= h(number_format($week['required'], 2)) ?></td>
                    <td class="<?= $week['met'] ? 'status-met' : 'status-short' ?>">
                        <?= $week['met'] ? 'Met' : h(number_format(-$week['difference'], 2)) . ' short' ?>
                    </td>
                    <td><?= h(number_format($week['surplus'], 2)) ?></td>
                    <td>&euro;<?= h(number_format($week['surplus_rate_eur'], 2)) ?>/hr</td>
                    <td>&euro;<?= h(number_format($week['surplus_pay'], 2)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>Logged entries</h2>
            <?php if (!empty($entries)): ?>
            <label class="row-limit">
                Show
                <select data-row-limit data-target="logged-entries-table">
                    <option value="10" selected>10</option>
                    <option value="20">20</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </label>
            <?php endif; ?>
        </div>
        <?php if (empty($entries)): ?>
            <p class="empty-state">No entries yet.</p>
        <?php else: ?>
        <div class="table-scroll">
        <table id="logged-entries-table">
            <thead>
                <tr><th>Date</th><th>Hours</th><th>Description</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($entries as $entry): ?>
                <tr>
                    <td><?= h($entry['entry_date']) ?></td>
                    <td><?= h(number_format((float) $entry['hours'], 2)) ?></td>
                    <td class="wrap-cell"><?= nl2br(h($entry['description'])) ?></td>
                    <td>
                        <form action="delete_entry.php" method="post" onsubmit="return confirm('Delete this entry?');">
                            <input type="hidden" name="id" value="<?= h($entry['id']) ?>">
                            <button type="submit" class="delete-link" style="background:none;border:none;padding:0;cursor:pointer;">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<script src="<?= h(asset_url('theme.js')) ?>"></script>
<script src="<?= h(asset_url('row-limit.js')) ?>"></script>
</body>
</html>
