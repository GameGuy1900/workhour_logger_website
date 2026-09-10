<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/weeks.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/pdf.php';

$settingsHistory = get_settings_history();

$entries = get_db()
    ->query('SELECT id, entry_date, hours, description FROM entries ORDER BY entry_date ASC, id ASC')
    ->fetchAll();

$today = (new DateTime())->format('Y-m-d');
$weeklySummaries = array_reverse(compute_weekly_summaries($entries, $settingsHistory, $today));
$currentWeek = end($weeklySummaries);

$totalSurplusHours = array_sum(array_column($weeklySummaries, 'surplus'));
$totalSurplusPay = array_sum(array_column($weeklySummaries, 'surplus_pay'));

$totalHours = 0.0;
$entryRows = [];
foreach ($entries as $entry) {
    $hours = (float) $entry['hours'];
    $totalHours += $hours;
    $entryRows[] = [$entry['entry_date'], number_format($hours, 2), $entry['description']];
}

$weeklyColumns = [
    ['label' => 'Week', 'width' => 130, 'wrap' => false],
    ['label' => 'Logged', 'width' => 55, 'wrap' => false],
    ['label' => 'Required', 'width' => 60, 'wrap' => false],
    ['label' => 'Status', 'width' => 85, 'wrap' => false],
    ['label' => 'Surplus', 'width' => 55, 'wrap' => false],
    ['label' => 'Rate', 'width' => 65, 'wrap' => false],
    ['label' => 'Pay', 'width' => 65, 'wrap' => false],
];
$weeklyRows = [];
foreach ($weeklySummaries as $week) {
    $status = $week['met']
        ? 'Met'
        : number_format(-$week['difference'], 2) . ' short';
    $weeklyRows[] = [
        $week['week_start'] . ' - ' . $week['week_end'],
        number_format($week['logged'], 2),
        number_format($week['required'], 2),
        $status,
        number_format($week['surplus'], 2),
        '€' . number_format($week['surplus_rate_eur'], 2) . '/hr',
        '€' . number_format($week['surplus_pay'], 2),
    ];
}

$entryColumns = [
    ['label' => 'Date', 'width' => 80, 'wrap' => false],
    ['label' => 'Hours', 'width' => 60, 'wrap' => false],
    ['label' => 'Description', 'width' => 375, 'wrap' => true],
];

$report = new PdfReport('Work Hour Log', 'Generated on ' . $today);

$report->addHeading('Summary');
$report->addLine(sprintf(
    'This week (%s - %s): %s / %s hours - %s',
    $currentWeek['week_start'],
    $currentWeek['week_end'],
    number_format($currentWeek['logged'], 2),
    number_format($currentWeek['required'], 2),
    $currentWeek['met']
        ? 'Target met (+' . number_format($currentWeek['difference'], 2) . ')'
        : number_format(-$currentWeek['difference'], 2) . ' hours short'
));
$report->addLine(sprintf(
    'Surplus hours total: %s hours - €%s',
    number_format($totalSurplusHours, 2),
    number_format($totalSurplusPay, 2)
), true);
$report->addSpacer(14);

$report->addHeading('Weekly History');
$report->addTable($weeklyColumns, $weeklyRows);
$report->addSpacer(14);

$report->addHeading('Logged Entries');
$report->addTable($entryColumns, $entryRows, 'Total hours logged: ' . number_format($totalHours, 2));

$pdf = $report->render();

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="work-hours-' . $today . '.pdf"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
