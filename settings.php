<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/settings.php';

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES);
}

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
        update_settings(
            (float) $weeklyTargetHours,
            (float) $surplusRateEur,
            $goalLabel,
            (float) $goalAmountEur
        );
        header('Location: settings.php?saved=1');
        exit;
    }
}

$settings = get_settings();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Settings - Work Hour Logger</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h1>Settings</h1>
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
                       value="<?= h($_POST['weekly_target_hours'] ?? $settings['weekly_target_hours']) ?>" required>
            </div>
            <div>
                <label for="surplus_rate_eur">Surplus pay rate (&euro; per hour)</label>
                <input type="number" id="surplus_rate_eur" name="surplus_rate_eur" step="0.01" min="0"
                       value="<?= h($_POST['surplus_rate_eur'] ?? $settings['surplus_rate_eur']) ?>" required>
            </div>
            <div>
                <label for="goal_label">Savings goal name (optional)</label>
                <input type="text" id="goal_label" name="goal_label" maxlength="255"
                       placeholder="e.g. New headphones"
                       value="<?= h($_POST['goal_label'] ?? $settings['goal_label']) ?>">
            </div>
            <div>
                <label for="goal_amount_eur">Goal price (&euro;)</label>
                <input type="number" id="goal_amount_eur" name="goal_amount_eur" step="0.01" min="0"
                       value="<?= h($_POST['goal_amount_eur'] ?? $settings['goal_amount_eur']) ?>" required>
                <p class="field-hint">
                    Your accumulated surplus pay counts toward this price. Set to 0 to disable the goal.
                </p>
            </div>
            <button type="submit">Save settings</button>
        </form>
    </div>
</div>
</body>
</html>
