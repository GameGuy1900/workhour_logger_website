<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/weeks.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/assets.php';

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES);
}

$today = (new DateTime())->format('Y-m-d');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $weeklyTargetHours = $_POST['weekly_target_hours'] ?? '';
    $surplusRateEur = $_POST['surplus_rate_eur'] ?? '';
    $goalLabel = trim($_POST['goal_label'] ?? '');
    $goalAmountEur = $_POST['goal_amount_eur'] ?? '';

    if ($weeklyTargetHours === '' || !is_numeric($weeklyTargetHours) || (float) $weeklyTargetHours <= 0) {
        $errors[] = 'Weekly target hours must be a number greater than 0.';
    }
    if ($surplusRateEur === '' || !is_numeric($surplusRateEur) || (float) $surplusRateEur < 0) {
        $errors[] = 'Surplus rate must be a number of 0 or more.';
    }
    if ($goalAmountEur === '' || !is_numeric($goalAmountEur) || (float) $goalAmountEur < 0) {
        $errors[] = 'Goal price must be a number of 0 or more.';
    }

    if (empty($errors)) {
        save_settings_change(week_start($today), (float) $weeklyTargetHours, (float) $surplusRateEur);
        update_goal_settings($goalLabel, (float) $goalAmountEur);
        header('Location: settings.php?saved=1');
        exit;
    }
}

$currentSettings = get_current_settings($today);
$goalSettings = get_goal_settings();
$history = array_reverse(get_settings_history());
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Settings - Work Hour Logger</title>
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
        <h1>Settings</h1>
        <button type="button" id="theme-toggle" class="theme-toggle">&#127769; Dark mode</button>
    </div>
    <p class="subtitle"><a href="index.php">&larr; Back to logger</a></p>

    <?php if (isset($_GET['saved'])): ?>
        <div class="card notice-saved">Settings saved.</div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="card notice-error">
            <?php foreach ($errors as $error): ?>
                <p><?= h($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <form class="entry-form" action="settings.php" method="post">
            <div>
                <label for="weekly_target_hours">Weekly target hours</label>
                <input type="number" id="weekly_target_hours" name="weekly_target_hours" step="0.25" min="0.25"
                       value="<?= h($_POST['weekly_target_hours'] ?? $currentSettings['weekly_target_hours']) ?>" required>
            </div>
            <div>
                <label for="surplus_rate_eur">Surplus pay rate (&euro; per hour)</label>
                <input type="number" id="surplus_rate_eur" name="surplus_rate_eur" step="0.01" min="0"
                       value="<?= h($_POST['surplus_rate_eur'] ?? $currentSettings['surplus_rate_eur']) ?>" required>
            </div>
            <p class="field-hint">
                Changes apply from this week onward. Already-completed weeks keep whatever
                target and rate were active at the time, so past weeks never get recalculated.
            </p>
            <div>
                <label for="goal_label">Savings goal name (optional)</label>
                <input type="text" id="goal_label" name="goal_label" maxlength="255"
                       placeholder="e.g. New headphones"
                       value="<?= h($_POST['goal_label'] ?? $goalSettings['goal_label']) ?>">
            </div>
            <div>
                <label for="goal_amount_eur">Goal price (&euro;)</label>
                <input type="number" id="goal_amount_eur" name="goal_amount_eur" step="0.01" min="0"
                       value="<?= h($_POST['goal_amount_eur'] ?? $goalSettings['goal_amount_eur']) ?>" required>
                <p class="field-hint">
                    Your accumulated surplus pay counts toward this price. Set to 0 to disable the goal.
                </p>
            </div>
            <button type="submit">Save settings</button>
        </form>
    </div>

    <?php if (count($history) > 1): ?>
    <div class="card">
        <div class="card-header">
            <h2>Change history</h2>
            <label class="row-limit">
                Show
                <select data-row-limit data-target="settings-history-table">
                    <option value="10">10</option>
                    <option value="20" selected>20</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </label>
        </div>
        <div class="table-scroll">
        <table id="settings-history-table">
            <thead>
                <tr><th>Effective from</th><th>Target hours</th><th>Rate</th></tr>
            </thead>
            <tbody>
            <?php foreach ($history as $row): ?>
                <tr>
                    <td><?= h($row['effective_from']) ?></td>
                    <td><?= h(number_format((float) $row['weekly_target_hours'], 2)) ?></td>
                    <td>&euro;<?= h(number_format((float) $row['surplus_rate_eur'], 2)) ?>/hr</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endif; ?>
</div>
<script src="<?= h(asset_url('theme.js')) ?>"></script>
<script src="<?= h(asset_url('row-limit.js')) ?>"></script>
</body>
</html>
