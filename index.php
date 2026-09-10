<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/weeks.php';

$entries = get_db()
    ->query('SELECT id, entry_date, hours, description FROM entries ORDER BY entry_date DESC, id DESC')
    ->fetchAll();

$today = (new DateTime())->format('Y-m-d');
$weeklySummaries = compute_weekly_summaries($entries, (float) WEEKLY_TARGET_HOURS, $today);
$currentWeek = $weeklySummaries[0];
$pastWeeks = array_slice($weeklySummaries, 1);

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
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h1>Work Hour Logger</h1>
    <p class="subtitle">Log your hours each day. Target: <?= h(WEEKLY_TARGET_HOURS) ?> hours/week &mdash; any shortfall carries into next week.</p>

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
    </div>

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
        <h2>Weekly history</h2>
        <?php if (empty($pastWeeks)): ?>
            <p class="empty-state">No previous weeks yet.</p>
        <?php else: ?>
        <table>
            <thead>
                <tr><th>Week</th><th>Logged</th><th>Required</th><th>Status</th></tr>
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
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Logged entries</h2>
        <?php if (empty($entries)): ?>
            <p class="empty-state">No entries yet.</p>
        <?php else: ?>
        <table>
            <thead>
                <tr><th>Date</th><th>Hours</th><th>Description</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($entries as $entry): ?>
                <tr>
                    <td><?= h($entry['entry_date']) ?></td>
                    <td><?= h(number_format((float) $entry['hours'], 2)) ?></td>
                    <td><?= nl2br(h($entry['description'])) ?></td>
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
        <?php endif; ?>
    </div>
</div>
</body>
</html>
