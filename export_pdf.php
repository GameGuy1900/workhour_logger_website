<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/pdf.php';

$entries = get_db()
    ->query('SELECT entry_date, hours, description FROM entries ORDER BY entry_date ASC, id ASC')
    ->fetchAll();

$columns = [
    ['label' => 'Date', 'width' => 80, 'wrap' => false],
    ['label' => 'Hours', 'width' => 60, 'wrap' => false],
    ['label' => 'Description', 'width' => 375, 'wrap' => true],
];

$rows = [];
$totalHours = 0.0;
foreach ($entries as $entry) {
    $hours = (float) $entry['hours'];
    $totalHours += $hours;
    $rows[] = [$entry['entry_date'], number_format($hours, 2), $entry['description']];
}

$today = (new DateTime())->format('Y-m-d');
$footer = 'Total hours logged: ' . number_format($totalHours, 2);

$pdf = build_table_pdf(
    'Work Hour Log',
    'Generated on ' . $today,
    $columns,
    $rows,
    $footer
);

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="work-hours-' . $today . '.pdf"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
