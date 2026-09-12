<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/weeks.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/pdf.php';

$settingsHistory = get_settings_history();
$goalSettings = get_goal_settings();
$goalLabel = $goalSettings['goal_label'];
$goalAmountEur = (float) $goalSettings['goal_amount_eur'];

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
    ['label' => 'Gelogd', 'width' => 55, 'wrap' => false],
    ['label' => 'Vereist', 'width' => 60, 'wrap' => false],
    ['label' => 'Status', 'width' => 85, 'wrap' => false],
    ['label' => 'Overschot', 'width' => 55, 'wrap' => false],
    ['label' => 'Tarief', 'width' => 65, 'wrap' => false],
    ['label' => 'Betaling', 'width' => 65, 'wrap' => false],
];
$weeklyRows = [];
foreach ($weeklySummaries as $week) {
    $status = $week['met']
        ? 'Behaald'
        : number_format(-$week['difference'], 2) . ' tekort';
    $weeklyRows[] = [
        $week['week_start'] . ' - ' . $week['week_end'],
        number_format($week['logged'], 2),
        number_format($week['required'], 2),
        $status,
        number_format($week['surplus'], 2),
        '€' . number_format($week['surplus_rate_eur'], 2) . '/uur',
        '€' . number_format($week['surplus_pay'], 2),
    ];
}

$entryColumns = [
    ['label' => 'Datum', 'width' => 80, 'wrap' => false],
    ['label' => 'Uren', 'width' => 60, 'wrap' => false],
    ['label' => 'Omschrijving', 'width' => 375, 'wrap' => true],
];

$report = new PdfReport('Urenoverzicht', 'Gegenereerd op ' . $today);
$report->setPageLabelFormat('Pagina %d van %d');

$report->addHeading('Samenvatting');
$report->addLine(sprintf(
    'Deze week (%s - %s): %s / %s uur - %s',
    $currentWeek['week_start'],
    $currentWeek['week_end'],
    number_format($currentWeek['logged'], 2),
    number_format($currentWeek['required'], 2),
    $currentWeek['met']
        ? 'Doel behaald (+' . number_format($currentWeek['difference'], 2) . ')'
        : number_format(-$currentWeek['difference'], 2) . ' uur tekort'
));
$report->addLine(sprintf(
    'Totaal overschoturen: %s uur - €%s',
    number_format($totalSurplusHours, 2),
    number_format($totalSurplusPay, 2)
), true);

if ($goalAmountEur > 0) {
    $goalPaid = min($totalSurplusPay, $goalAmountEur);
    $goalPercent = min(100, $totalSurplusPay / $goalAmountEur * 100);
    $goalRemaining = max(0, $goalAmountEur - $totalSurplusPay);
    $goalExtra = max(0, $totalSurplusPay - $goalAmountEur);
    $goalTitle = $goalLabel !== '' ? $goalLabel : 'Spaardoel';

    $report->addLine(sprintf(
        '%s: €%s / €%s afbetaald (%s)',
        $goalTitle,
        number_format($goalPaid, 2),
        number_format($goalAmountEur, 2),
        $goalPercent >= 100 ? 'Afbetaald!' : number_format($goalPercent, 0) . '%'
    ), true);
    if ($goalPercent < 100) {
        $report->addLine(sprintf('Nog €%s te gaan.', number_format($goalRemaining, 2)));
    } elseif ($goalExtra > 0) {
        $report->addLine(sprintf('€%s overschot over na afbetaling.', number_format($goalExtra, 2)));
    }
}

$report->addSpacer(14);

$report->addHeading('Weekoverzicht');
$report->addTable($weeklyColumns, $weeklyRows);
$report->addSpacer(14);

$report->addHeading('Geregistreerde uren');
$report->addTable($entryColumns, $entryRows, 'Totaal aantal geregistreerde uren: ' . number_format($totalHours, 2));

$pdf = $report->render();

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="urenoverzicht-' . $today . '.pdf"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
