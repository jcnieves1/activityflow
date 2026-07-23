<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_login();

$reportKey = $_GET['report'] ?? '';
if (!array_key_exists($reportKey, REPORT_DEFINITIONS)) {
    if (($_GET['action'] ?? '') === 'export_csv') {
        // Avoid HTTP 404 here: it collides with the ErrorDocument 404 mapping in
        // .htaccess, which can substitute our custom 404 page for this plain-text
        // response. 400 (Bad Request) isn't mapped, so it always passes through.
        http_response_code(400);
        exit('Unknown report.');
    }
    header('Content-Type: application/json');
    json_error('Unknown report.', 404);
}

$filters = [
    'date_from' => $_GET['date_from'] ?? '', 'date_to' => $_GET['date_to'] ?? '',
    'employee_id' => $_GET['employee_id'] ?? '', 'requester_id' => $_GET['requester_id'] ?? '',
    'project_id' => $_GET['project_id'] ?? '', 'department_id' => $_GET['department_id'] ?? '',
    'activity_type' => $_GET['activity_type'] ?? '', 'is_adhoc' => $_GET['is_adhoc'] ?? '',
    'status' => $_GET['status'] ?? '', 'priority' => $_GET['priority'] ?? '',
    'request_channel' => $_GET['request_channel'] ?? '', 'category_id' => $_GET['category_id'] ?? '',
];

// Plain employees see their own activity only; PM/Admin/Viewer get the requested scope.
if (!is_admin() && !is_pm() && !user_has_role(ROLE_VIEWER)) {
    $filters['employee_id'] = current_person_id() ?: -1;
}

$result = run_report($reportKey, $filters);

// PDF export: if a PHP PDF library (e.g. Dompdf) is installed via Composer, render
// $result into an HTML template and pass it to the library here when action=export_pdf.
// Not bundled by default since it requires a Composer dependency; the Reports Center's
// "Print / PDF" button uses the browser's print-to-PDF instead.

if (($_GET['action'] ?? 'run') === 'export_csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $reportKey . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $result['columns']);
    foreach ($result['rows'] as $row) {
        fputcsv($out, array_values($row));
    }
    fclose($out);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
json_response(['ok' => true, 'report' => REPORT_DEFINITIONS[$reportKey], 'columns' => $result['columns'], 'rows' => $result['rows'], 'sample_size' => count($result['rows'])]);
