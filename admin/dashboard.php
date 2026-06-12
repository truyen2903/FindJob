<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/app/models/AdminReport.php';
require_once dirname(__DIR__) . '/app/models/Application.php';

if (empty($_SESSION['user_id']) || (int)($_SESSION['role_id'] ?? 0) !== 1) {
    header('Location: ' . BASE_URL . '/403.php');
    exit;
}

$reportModel = new AdminReport();
$summary = $reportModel->getSummary();
$pipeline = $reportModel->getPipelineBreakdown();
$timeline = $reportModel->getMonthlyTimeline(6);
$topEmployers = $reportModel->getTopEmployers(5);

$applicationModel = new Application();
$statusLabels = $applicationModel->getStatusLabels();
$statusColors = [
    'applied' => 'primary',
    'viewed' => 'info',
    'shortlisted' => 'warning',
    'rejected' => 'danger',
    'hired' => 'success',
];

$statusChartPalette = [
    'applied' => ['bg' => 'rgba(13, 110, 253, 0.25)', 'border' => '#0d6efd'],
    'viewed' => ['bg' => 'rgba(13, 202, 240, 0.25)', 'border' => '#0dcaf0'],
    'shortlisted' => ['bg' => 'rgba(255, 193, 7, 0.25)', 'border' => '#ffc107'],
    'rejected' => ['bg' => 'rgba(220, 53, 69, 0.25)', 'border' => '#dc3545'],
    'hired' => ['bg' => 'rgba(25, 135, 84, 0.25)', 'border' => '#198754'],
];

$pipelineTotal = array_sum($pipeline);
$pipelinePercentages = [];
if ($pipelineTotal > 0) {
    foreach ($pipeline as $statusKey => $count) {
        $pipelinePercentages[$statusKey] = round(($count / $pipelineTotal) * 100, 1);
    }
}

$chartData = [
    'pipeline' => [
        'labels' => [],
        'values' => [],
        'background' => [],
        'border' => [],
    ],
    'timeline' => [
        'labels' => [],
        'series' => [
            'jobs' => [],
            'applications' => [],
            'shortlisted' => [],
            'hired' => [],
        ],
    ],
];

foreach ($pipeline as $statusKey => $count) {
    $chartData['pipeline']['labels'][] = $statusLabels[$statusKey] ?? ucfirst($statusKey);
    $chartData['pipeline']['values'][] = (int)$count;
    $palette = $statusChartPalette[$statusKey] ?? ['bg' => 'rgba(108, 117, 125, 0.3)', 'border' => '#6c757d'];
    $chartData['pipeline']['background'][] = $palette['bg'];
    $chartData['pipeline']['border'][] = $palette['border'];
}

foreach ($timeline as $row) {
    $chartData['timeline']['labels'][] = $row['label'] ?? '';
    $chartData['timeline']['series']['jobs'][] = (int)($row['jobs'] ?? 0);
    $chartData['timeline']['series']['applications'][] = (int)($row['applications'] ?? 0);
    $chartData['timeline']['series']['shortlisted'][] = (int)($row['shortlisted'] ?? 0);
    $chartData['timeline']['series']['hired'][] = (int)($row['hired'] ?? 0);
}

ob_start();
include dirname(__DIR__) . '/app/views/admin/dashboard.php';
$content = ob_get_clean();
include __DIR__ . '/layout.php';
