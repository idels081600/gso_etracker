<?php
declare(strict_types=1);

function fuelReportDateFromFormat(string $format, string $value): ?DateTimeImmutable
{
    $date = DateTimeImmutable::createFromFormat('!' . $format, $value);
    $errors = DateTimeImmutable::getLastErrors();
    if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        return null;
    }

    return $date;
}

function fuelReportResolvePeriod(array $input): array
{
    $type = strtolower(trim((string) ($input['period_type'] ?? 'monthly')));
    if (!in_array($type, ['weekly', 'monthly'], true)) {
        throw new InvalidArgumentException('Select a valid report period.');
    }

    if ($type === 'weekly') {
        $selected = trim((string) ($input['report_date'] ?? ''));
        $date = fuelReportDateFromFormat('Y-m-d', $selected);
        if ($date === null) {
            throw new InvalidArgumentException('Select a valid date for the weekly report.');
        }
        $start = $date->modify('monday this week');
        $end = $start->modify('+6 days');
        if ($start->format('Y') !== $end->format('Y')) {
            $label = 'Week of ' . $start->format('M j, Y') . '-' . $end->format('M j, Y');
        } elseif ($start->format('m') !== $end->format('m')) {
            $label = 'Week of ' . $start->format('M j') . '-' . $end->format('M j, Y');
        } else {
            $label = 'Week of ' . $start->format('F j') . '-' . $end->format('j, Y');
        }
    } else {
        $selected = trim((string) ($input['report_month'] ?? ''));
        $date = fuelReportDateFromFormat('Y-m', $selected);
        if ($date === null) {
            throw new InvalidArgumentException('Select a valid month for the monthly report.');
        }
        $start = $date->modify('first day of this month');
        $end = $date->modify('last day of this month');
        $label = $start->format('F Y');
    }

    return [
        'type' => $type,
        'start_date' => $start->format('Y-m-d'),
        'end_date' => $end->format('Y-m-d'),
        'label' => $label,
    ];
}

function fuelReportFetchVehicleConsumption(mysqli $conn, array $period, bool $includePrivate): array
{
    $scopeFilter = $includePrivate
        ? ''
        : " AND LOWER(TRIM(COALESCE(gi.issuance_scope, v.vehicle_scope, 'government'))) <> 'private'";

    $sql = "
        SELECT
            CASE
                WHEN LOWER(TRIM(COALESCE(gi.issuance_scope, v.vehicle_scope, 'government'))) = 'private'
                    THEN 'Other Cars'
                ELSE COALESCE(NULLIF(TRIM(v.office), ''), NULLIF(TRIM(gi.office), ''), 'Unassigned')
            END AS office,
            CASE
                WHEN LOWER(TRIM(COALESCE(gi.issuance_scope, v.vehicle_scope, 'government'))) = 'private'
                    THEN 'Other Cars'
                ELSE 'Government'
            END AS scope_label,
            v.id AS vehicle_db_id,
            COALESCE(NULLIF(TRIM(v.plate_no), ''), NULLIF(TRIM(v.vehicle_id), ''), CONCAT('Vehicle ', v.id)) AS plate_no,
            COALESCE(NULLIF(TRIM(v.type_of_vehicle), ''), 'Not specified') AS vehicle_type,
            COUNT(*) AS fueling_events,
            CAST(SUBSTRING_INDEX(
                GROUP_CONCAT(vol.current_odometer ORDER BY vol.recorded_at DESC, vol.id DESC SEPARATOR ','),
                ',',
                1
            ) AS DECIMAL(12,1)) AS latest_odometer,
            ROUND(SUM(
                CASE WHEN LOWER(COALESCE(gi.fuel_type, v.fuel_type, '')) LIKE '%diesel%'
                    THEN gi.authorized_liters ELSE 0 END
            ), 2) AS diesel_issued_liters,
            ROUND(SUM(
                CASE WHEN LOWER(COALESCE(gi.fuel_type, v.fuel_type, '')) LIKE '%diesel%'
                    THEN 0 ELSE gi.authorized_liters END
            ), 2) AS unleaded_issued_liters,
            ROUND(SUM(gi.authorized_liters), 2) AS total_issued_liters
        FROM gas_issuances gi
        INNER JOIN vehicles v ON v.id = gi.vehicle_id
        INNER JOIN vehicle_odometer_logs vol ON vol.gas_issuance_id = gi.id
        WHERE LOWER(gi.status) = 'used'
            AND DATE(vol.recorded_at) BETWEEN ? AND ?
            {$scopeFilter}
        GROUP BY office, scope_label, v.id, plate_no, vehicle_type
        ORDER BY office, plate_no
    ";

    $statement = $conn->prepare($sql);
    if (!$statement) {
        throw new RuntimeException('Unable to prepare the report query.');
    }

    $startDate = (string) $period['start_date'];
    $endDate = (string) $period['end_date'];
    $statement->bind_param('ss', $startDate, $endDate);
    if (!$statement->execute()) {
        $statement->close();
        throw new RuntimeException('Unable to generate the fuel report.');
    }

    $result = $statement->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $row['vehicle_db_id'] = (int) $row['vehicle_db_id'];
        $row['fueling_events'] = (int) $row['fueling_events'];
        $row['latest_odometer'] = isset($row['latest_odometer']) ? (float) $row['latest_odometer'] : null;
        $row['diesel_issued_liters'] = (float) $row['diesel_issued_liters'];
        $row['unleaded_issued_liters'] = (float) $row['unleaded_issued_liters'];
        $row['total_issued_liters'] = (float) $row['total_issued_liters'];
        $rows[] = $row;
    }
    $statement->close();

    return $rows;
}

function fuelReportSummarize(array $rows): array
{
    $offices = [];
    $totals = [
        'vehicles' => count($rows),
        'fuelings' => 0,
        'diesel_liters' => 0.0,
        'unleaded_liters' => 0.0,
        'total_liters' => 0.0,
    ];

    foreach ($rows as $row) {
        $office = (string) $row['office'];
        if (!isset($offices[$office])) {
            $offices[$office] = [
                'office' => $office,
                'vehicles' => 0,
                'fuelings' => 0,
                'diesel_liters' => 0.0,
                'unleaded_liters' => 0.0,
                'total_liters' => 0.0,
                'rows' => [],
            ];
        }

        $offices[$office]['vehicles']++;
        $offices[$office]['fuelings'] += (int) $row['fueling_events'];
        $offices[$office]['diesel_liters'] += (float) $row['diesel_issued_liters'];
        $offices[$office]['unleaded_liters'] += (float) $row['unleaded_issued_liters'];
        $offices[$office]['total_liters'] += (float) $row['total_issued_liters'];
        $offices[$office]['rows'][] = $row;

        $totals['fuelings'] += (int) $row['fueling_events'];
        $totals['diesel_liters'] += (float) $row['diesel_issued_liters'];
        $totals['unleaded_liters'] += (float) $row['unleaded_issued_liters'];
        $totals['total_liters'] += (float) $row['total_issued_liters'];
    }

    ksort($offices, SORT_NATURAL | SORT_FLAG_CASE);
    $totals['offices'] = count($offices);

    return ['offices' => $offices, 'totals' => $totals];
}
