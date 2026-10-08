<?php

/**
 * Resolve the authenticated IB administrator from the Version 1 users table.
 *
 * Version 1 sessions can outlive role changes or be replaced by a session from
 * the adjacent v2 application.  The database record is therefore the source of
 * truth for this high-privilege workflow instead of the cached session role.
 */
function logi_ib_admin_actor(mysqli $conn): ?string
{
    if (empty($_SESSION['logged_in']) || empty($_SESSION['username'])) {
        return null;
    }

    $username = trim((string) $_SESSION['username']);
    if ($username === '') {
        return null;
    }

    $stmt = $conn->prepare(
        "SELECT username, role, status
         FROM users
         WHERE username = ?
         LIMIT 1"
    );
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $username);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        return null;
    }

    $role = strtoupper(trim((string) ($user['role'] ?? '')));
    $status = strtolower(trim((string) ($user['status'] ?? '')));
    if ($role !== 'ADMIN_SAP' || $status !== 'active') {
        return null;
    }

    // Refresh values that may be stale in an existing Version 1 session.
    $_SESSION['username'] = (string) $user['username'];
    $_SESSION['role'] = $role;
    $_SESSION['logged_in'] = true;

    return (string) $user['username'];
}

