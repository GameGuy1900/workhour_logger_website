<?php
require_once __DIR__ . '/includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$date = $_POST['date'] ?? '';
$hours = $_POST['hours'] ?? '';
$description = trim($_POST['description'] ?? '');

$errors = [];
if ($date === '' || !DateTime::createFromFormat('Y-m-d', $date)) {
    $errors[] = 'A valid date is required.';
}
if ($hours === '' || !is_numeric($hours) || (float) $hours <= 0 || (float) $hours > 24) {
    $errors[] = 'Hours must be a number between 0 and 24.';
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
    'INSERT INTO entries (entry_date, hours, description) VALUES (:date, :hours, :description)'
);
$stmt->execute([
    ':date' => $date,
    ':hours' => (float) $hours,
    ':description' => $description,
]);

header('Location: index.php');
exit;
