<?php
require_once dirname(__DIR__) . '/app_security.php';
require_once dirname(__DIR__) . '/api_helpers.php';
require_once dirname(__DIR__) . '/validators.php';
require_once dirname(__DIR__) . '/db_asset.php';
require_once __DIR__ . '/equipment_helpers.php';

asset_require_auth();
asset_require_post();

function posted_equipment_items(): array
{
    $typeIds = $_POST['equipment_type_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    if (!is_array($typeIds) || !is_array($quantities) || count($typeIds) !== count($quantities)) {
        throw new InvalidArgumentException('Invalid equipment rows.');
    }
    $items = [];
    foreach ($typeIds as $index => $typeId) {
        $items[] = [
            'equipment_type_id' => (int) $typeId,
            'quantity' => (int) ($quantities[$index] ?? 0),
        ];
    }
    return $items;
}

function validate_deployment_dates(string $date, string $retrievalDate): void
{
    if ($retrievalDate < $date) {
        throw new InvalidArgumentException('Retrieval date cannot be earlier than the installation date.');
    }
}

try {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'get_deployment') {
        $id = input_int($_POST, 'id');
        $deployment = db_fetch_one($conn, 'SELECT * FROM deployments WHERE id = ?', 'i', [$id]);
        if (!$deployment) {
            api_response(false, 'Deployment not found.', [], 404);
        }
        $deployment['items'] = get_deployment_items($id);
        api_response(true, '', $deployment);
    }

    if ($action === 'get_deployments') {
        api_response(true, '', get_deployments_with_items());
    }

    if ($action === 'update_deployment') {
        $id = input_int($_POST, 'id');
        $name = input_string($_POST, 'name', 255);
        $contact = input_string($_POST, 'contact', 30);
        if (!preg_match('/^\+639\d{9}$/', $contact)) {
            throw new InvalidArgumentException('Contact must be +639 followed by 9 digits.');
        }
        $purpose = input_string($_POST, 'purpose', 255);
        $location = input_string($_POST, 'location', 255);
        $address = input_string($_POST, 'address', 500);
        $date = input_date($_POST, 'date');
        $retrievalDate = input_date($_POST, 'retrieval_date');
        validate_deployment_dates($date, $retrievalDate);
        $status = input_enum($_POST, 'status', EQUIPMENT_DEPLOYMENT_STATUSES);

        update_deployment(
            $id,
            $name,
            $contact,
            $purpose,
            $location,
            $address,
            $date,
            $retrievalDate,
            $status,
            posted_equipment_items()
        );
        api_response(true, 'Deployment updated successfully.');
    }

    if ($action === 'update_status') {
        update_deployment_status(
            input_int($_POST, 'id'),
            input_enum($_POST, 'status', EQUIPMENT_DEPLOYMENT_STATUSES)
        );
        api_response(true, 'Status updated successfully.');
    }

    if ($action === 'bulk_update_status') {
        $updates = json_decode((string) ($_POST['updates'] ?? ''), true);
        if (!is_array($updates)) {
            throw new InvalidArgumentException('Invalid bulk update request.');
        }
        $updated = bulk_update_deployment_statuses($updates);
        api_response(true, sprintf('%d deployment%s updated successfully.', $updated, $updated === 1 ? '' : 's'));
    }

    if ($action === 'delete_deployment') {
        delete_deployment(input_int($_POST, 'id'));
        api_response(true, 'Deployment archived successfully.');
    }

    if ($action === 'get_equipment_types') {
        $category = isset($_POST['category']) && $_POST['category'] !== '' ? (string) $_POST['category'] : null;
        api_response(true, '', get_equipment_types($category));
    }

    if ($action === 'update_inventory') {
        $updates = json_decode((string) ($_POST['updates'] ?? ''), true);
        if (!is_array($updates)) {
            throw new InvalidArgumentException('Invalid inventory update request.');
        }
        $updated = update_inventory_totals($updates);
        api_response(true, sprintf('%d inventory item%s updated successfully.', $updated, $updated === 1 ? '' : 's'));
    }

    if ($action === 'create_equipment_type') {
        $id = create_equipment_type(
            input_enum($_POST, 'category', ['Chair', 'Table']),
            input_string($_POST, 'subtype_name', 50),
            input_string($_POST, 'display_name', 100),
            input_int($_POST, 'available_qty', 0)
        );
        api_response(true, 'Equipment type added successfully.', ['id' => $id]);
    }

    if ($action === 'update_equipment_type') {
        update_equipment_type(
            input_int($_POST, 'id'),
            input_enum($_POST, 'category', ['Chair', 'Table']),
            input_string($_POST, 'subtype_name', 50),
            input_string($_POST, 'display_name', 100),
            input_int($_POST, 'available_qty', 0)
        );
        api_response(true, 'Equipment type and balance updated successfully.');
    }

    if ($action === 'delete_equipment_type') {
        delete_equipment_type(input_int($_POST, 'id'));
        api_response(true, 'Equipment type deleted successfully.');
    }

    if ($action === 'get_metrics') {
        api_response(true, '', get_deployment_metrics());
    }

    if ($action === 'get_retrieved') {
        api_response(true, '', get_deployments_with_items('Retrieved'));
    }

    if ($action === 'undo_retrieved') {
        update_deployment_status(input_int($_POST, 'id'), 'For Retrieval');
        api_response(true, 'Status reverted to For Retrieval.');
    }

    api_response(false, 'Unknown action.', [], 400);
} catch (InvalidArgumentException $error) {
    api_response(false, $error->getMessage(), [], 422);
} catch (Throwable $error) {
    api_database_error($error);
}
