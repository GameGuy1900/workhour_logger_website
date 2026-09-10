<?php
require_once __DIR__ . '/includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$id = $_POST['id'] ?? '';
if ($id !== '' && ctype_digit((string) $id)) {
    $stmt = get_db()->prepare('DELETE FROM entries WHERE id = :id');
    $stmt->execute([':id' => (int) $id]);
}

header('Location: index.php');
exit;
