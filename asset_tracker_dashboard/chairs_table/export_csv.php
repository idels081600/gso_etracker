<?php
require_once dirname(__DIR__) . '/app_security.php';
require_once dirname(__DIR__) . '/db_asset.php';
require_once __DIR__ . '/equipment_helpers.php';

asset_require_auth();

$deployments = get_deployments_with_items();
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="chairs-table-deployments-' . date('Y-m-d') . '.csv"');
header('Cache-Control: no-store, no-cache, must-revalidate');

$output = fopen('php://output', 'wb');
fputcsv($output, [
    'Deployment ID', 'Requestor', 'Contact', 'Purpose', 'Location', 'Address',
    'Installation Date', 'Retrieval Date', 'Status', 'Category', 'Subtype', 'Quantity',
]);

foreach ($deployments as $deployment) {
    foreach ($deployment['items'] as $item) {
        fputcsv($output, [
            $deployment['id'],
            $deployment['name'],
            $deployment['contact_no'],
            $deployment['purpose'],
            $deployment['location'],
            $deployment['address'],
            $deployment['date'],
            $deployment['retrieval_date'],
            $deployment['status'],
            $item['category'],
            $item['display_name'],
            $item['quantity'],
        ]);
    }
}
fclose($output);
exit;
