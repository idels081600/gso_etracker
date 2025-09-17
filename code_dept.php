<?php

/**
 * Code Department Scanner Handler
 *
 * This script handles QR code scanning for department pass-slips.
 * It processes requests in two scenarios:
 * 1. Initial check-in: Updates status from 'Partially Approved' to 'Approved'
 * 2. Return check-out: Calculates time differences and marks as 'Done'
 *
 * Expected input: POST data with 'scannedData' containing the scanned name/identifier
 * Output: JSON response with status and relevant data
 *
 * Debug tips:
 * - Check database connection in dbh.php
 * - Verify postData availability in $_POST['scannedData']
 * - Monitor MySQL query results with mysqli_error($conn) if queries fail
 * - Timezone is set to Asia/Manila for accurate time calculations
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    include 'dbh.php';

    // Sanitize input to prevent SQL injection
    // The scannedData typically contains a name or unique identifier from QR code
    $scannedData = mysqli_real_escape_string($conn, $_POST['scannedData']);
    // Set timezone for consistent time handling across the system
    date_default_timezone_set('Asia/Manila');

    // STEP 1: Handle Initial Entry (Check-in)
    // Check if there's a pending request that needs approval activation
    // Looks for the most recent (DESC by id) partially approved request matching the scanned name
    $query_in = "SELECT * FROM request WHERE name = '$scannedData' AND Status = 'Partially Approved' ORDER BY id DESC LIMIT 1";
    $result_in = mysqli_query($conn, $query_in);
    // Debug: Check query success and row count
    // If query failed: check mysqli_error($conn)
    // If no rows: scanned name may not have pending approval or name mismatch
    if ($result_in && mysqli_num_rows($result_in) > 0) {
        $row_in = mysqli_fetch_assoc($result_in);

        // Calculate time difference between estimated time and current time
        $estimatedTime = new DateTime($row_in['esttime']);  // Expected approval time
        $currentTime = new DateTime();                      // Current time (check-in)

        // Calculate the time difference interval
        $interval = $currentTime->diff($estimatedTime);

        // Calculate total hours and minutes for formatted display
        $totalHours = $interval->days * 24 + $interval->h;  // Convert days to hours
        $totalMinutes = $totalHours * 60 + $interval->i;     // Total minutes

        // Format time allotted as HH:MM (e.g., "02:30" for 2 hours 30 minutes)
        $timeAllotted = sprintf('%02d:%02d', $totalHours, $interval->i);

        // Determine whether to display as hours or minutes based on magnitude
        if ($totalHours > 0) {
            // Show as hours (including minutes if substantial)
            $timeDifferenceText = $totalHours . " hour" . ($totalHours > 1 ? "s" : "");
            if ($interval->i > 0) {
                $timeDifferenceText .= " " . $interval->i . " minute" . ($interval->i > 1 ? "s" : "");
            }
        } else {
            // Show as minutes (for differences under 1 hour)
            $timeDifferenceText = $interval->i . " minute" . ($interval->i > 1 ? "s" : "");
        }

        // Debug notes:
        // - totalHours: Shows if the difference is primarily in hours
        // - totalMinutes: Total minutes for any calculations needed
        // - timeAllotted: HH:MM format for database storage (MySQL compatible)
        // - timeDifferenceText: Human-readable format for display

        // Activate the partially approved request by setting:
        // - timedept: timestamp of department check-in
        // - time_allotted: calculated difference between estimated and actual time
        // - Status: 'Approved' - now officially approved
        // - status1: 'Pass-Slip' - indicates pass-slip issued
        // - ImageName: visual indicator for UI
        $update_query = "UPDATE request
                         SET `timedept` = NOW(),
                             `time_allotted` = '$timeDifferenceText',
                             Status = 'Approved',
                             `status1` = 'Pass-Slip',
                             `ImageName` = 'Check-Approved.png'
                         WHERE id = " . intval($row_in['id']);

        if (mysqli_query($conn, $update_query)) {
            echo json_encode([
                'status' => 'exists',
                'name' => $row_in['name']
            ]);
        } else {
            echo json_encode(['status' => 'update_error']);
        }
        exit;
    }

    // STEP 2: Handle Return Scan (Check-out)
    // Look for the most recent approved request matching the scanned name for check-out
    // This handles the case where someone is returning from their approved absence
    $query_out = "SELECT * FROM request WHERE name = '$scannedData' AND Status = 'Approved' ORDER BY id DESC LIMIT 1";
    $result_out = mysqli_query($conn, $query_out);

    // Debug: Query and row validation (same as STEP 1)
    // If fails: database issue or no approved request for the scanned name
    if ($result_out && mysqli_num_rows($result_out) > 0) {
        $row = mysqli_fetch_assoc($result_out);

        // TIME CALCULATION LOGIC:
        // Compare actual return time (now) with estimated return time from database
        // $row['esttime'] contains the expected return time in database format

        // Create DateTime objects for comparison
        $estimatedTime = new DateTime($row['esttime']);  // Expected return time
        $actualTime = new DateTime();                    // Current time (Asia/Manila timezone)

        // DETERMINE IF EARLY OR LATE:
        if ($actualTime < $estimatedTime) {
            // Person arrived BEFORE expected time (early arrival)
            // Calculate how early: estimatedTime - actualTime
            $interval = $estimatedTime->diff($actualTime);  // Note: inverted calculation for early
            $hours = $interval->h;                           // Hours difference
            $minutes = $interval->i;                        // Minutes difference

            // Build human-readable time string (e.g., "2 hours 30 minutes")
            // Trim removes any trailing whitespace if no minutes
            $timeDifference = trim(($hours > 0 ? $hours . " hour" . ($hours > 1 ? "s " : " ") : "") .
                ($minutes > 0 ? $minutes . " minute" . ($minutes > 1 ? "s" : "") : ""));
            $remarks = "Arrived $timeDifference early";     // e.g., "Arrived 1 hour 15 minutes early"

            // Debug: Check $interval->h and $interval->i values if time calculation seems wrong
        } else {
            // Person arrived AFTER expected time (late arrival)
            // Calculate how late: actualTime - estimatedTime
            $interval = $actualTime->diff($estimatedTime);
            $hours = $interval->h;
            $minutes = $interval->i;

            // Same time string formatting as early case
            $timeDifference = trim(($hours > 0 ? $hours . " hour" . ($hours > 1 ? "s " : " ") : "") .
                ($minutes > 0 ? $minutes . " minute" . ($minutes > 1 ? "s" : "") : ""));
            $remarks = "Arrived $timeDifference late";      // e.g., "Arrived 30 minutes late"

            // Edge case: If $hours + $minutes = 0, shows as "Arrived  late" (no time)
            // Consider adding logic to handle "on time" scenario if needed
        }

        // UPDATE DATABASE FOR CHECK-OUT:
        // Mark request as completed with return information
        // Update fields:
        // - time_returned: Formatted HH:MM of actual return time
        // - Status: 'Done' - Request fully completed
        // - status1: 'Present' - Person has returned
        // - remarks: Time difference commentary
        $update_query = "UPDATE request
                         SET `time_returned` = DATE_FORMAT(CURRENT_TIMESTAMP, '%H:%i'),
                             Status = 'Done',
                             `status1` = 'Present',
                             `remarks` = '$remarks'
                         WHERE id = " . intval($row['id']);

        if (mysqli_query($conn, $update_query)) {
            // Success: Return time difference and name for UI display
            // Status format: "Arrived 2 hours 15 minutes early" or "Arrived 30 minutes late"
            echo json_encode([
                'status' => "Arrived $timeDifference " . ($actualTime < $estimatedTime ? "early" : "late"),
                'name' => $row['name']
            ]);
        } else {
            // Update failed: database error or connection issue
            // Debug: Check mysqli_error($conn) for specific error details
            echo json_encode(['status' => 'update_error']);
        }
        exit;  // Stop processing after handling check-out
    }

    // FALLBACK: No matching request found in database
    // Possible reasons:
    // - QR code scanned but no approved request exists for that name
    // - Name mismatch between scan and database record
    // - Request already processed/completed
    echo json_encode(['status' => 'not_exists']);
} else {
    // REQUEST METHOD ERROR: Script expects POST data
    // This happens if someone tries to access the file directly via URL
    // or sends a GET request instead of POST
    echo json_encode(['status' => 'invalid_request']);
}
