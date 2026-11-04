<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/duration.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$date = $_POST['date'] ?? '';
$startTime = ($_POST['start_hour'] ?? '') . ':' . ($_POST['start_minute'] ?? '');
$endTime = ($_POST['end_hour'] ?? '') . ':' . ($_POST['end_minute'] ?? '');
$description = trim($_POST['description'] ?? '');

$errors = [];
if ($date === '' || !DateTime::createFromFormat('Y-m-d', $date)) {
    $errors[] = 'A valid date is required.';
}

$hours = null;
try {
    $hours = compute_duration_hours($startTime, $endTime);
} catch (InvalidArgumentException $e) {
    $errors[] = $e->getMessage();
}

if ($description === '') {
    $errors[] = 'A description is required.';
}

if (!empty($errors)) {
    http_response_code(400);
    echo implode(' ', array_map('htmlspecialchars', $errors));
    echo ' <a href="index.php">Go back</a>';
    exit;
}

$stmt = get_db()->prepare(
    'INSERT INTO entries (entry_date, start_time, end_time, hours, description) VALUES (:date, :start_time, :end_time, :hours, :description)'
);
$stmt->execute([
    ':date' => $date,
    ':start_time' => $startTime,
    ':end_time' => $endTime,
    ':hours' => $hours,
    ':description' => $description,
]);

header('Location: index.php');
exit;
