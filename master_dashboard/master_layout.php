<?php

function master_require_admin(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $allowedRoles = ['master_admin', 'Admin', 'super_admin'];
    $role = $_SESSION['role'] ?? '';

    if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['username']) || !in_array($role, $allowedRoles, true)) {
        header('Location: ../login_v2.php');
        exit();
    }
}

function master_csrf_token(): string
{
    if (empty($_SESSION['_master_csrf'])) {
        $_SESSION['_master_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_master_csrf'];
}

function master_csrf_input(): string
{
    return '<input type="hidden" name="csrf_token" value="' . master_h(master_csrf_token()) . '">';
}

function master_verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || empty($_SESSION['_master_csrf']) || !hash_equals($_SESSION['_master_csrf'], $token)) {
        http_response_code(403);
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode(['success' => false, 'message' => 'Security token expired. Refresh and try again.']);
        exit();
    }
}

function master_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function master_n($value): string
{
    return number_format((float)$value);
}

function master_rows_from_result($result, int $limit = 500): array
{
    $rows = [];
    if (!$result) {
        return $rows;
    }

    while (($row = mysqli_fetch_assoc($result)) && count($rows) < $limit) {
        $rows[] = $row;
    }

    return $rows;
}

function master_short_date($value): string
{
    if (!$value) {
        return 'No date';
    }

    $time = strtotime((string)$value);
    return $time ? date('M d, Y', $time) : (string)$value;
}

function master_page_start(string $activePage, string $title, string $subtitle = ''): void
{
    $userName = $_SESSION['username'] ?? 'Admin';
    $nav = [
        'dashboard' => ['href' => 'dashboard.php', 'icon' => 'fas fa-th-large', 'label' => 'Master Dashboard'],
        'assets' => ['href' => 'assets.php', 'icon' => 'fas fa-campground', 'label' => 'Assets / Tents'],
        'transportation' => ['href' => 'transportation.php', 'icon' => 'fas fa-truck', 'label' => 'Transportation'],
        'motorpool' => ['href' => 'motorpool.php', 'icon' => 'fas fa-wrench', 'label' => 'Motorpool'],
        'payables' => ['href' => 'payables.php', 'icon' => 'fas fa-file-invoice-dollar', 'label' => 'BAC Payables'],
    ];
    ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="master-csrf-token" content="<?php echo master_h(master_csrf_token()); ?>">
    <title><?php echo master_h($title); ?></title>
    <link rel="stylesheet" href="master_dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>

<body>
    <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar" aria-expanded="true">
        <i class="fas fa-bars"></i>
    </button>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <aside class="sidebar" id="masterSidebar">
        <div class="logo">
            <img src="../logo.png" alt="Tagbilaran seal">
            <div class="sidebar-user">
                <span class="role">Admin</span>
                <span class="user-name"><?php echo master_h($userName); ?></span>
            </div>
        </div>
        <hr class="divider">
        <nav aria-label="Master dashboard navigation">
            <ul>
                <?php foreach ($nav as $key => $item): ?>
                    <li>
                        <a class="<?php echo $activePage === $key ? 'active' : ''; ?>" href="<?php echo master_h($item['href']); ?>">
                            <i class="<?php echo master_h($item['icon']); ?> icon-size"></i>
                            <span class="nav-label"><?php echo master_h($item['label']); ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <a href="../logout.php" class="logout-item"><i class="fas fa-sign-out-alt icon-size"></i><span class="nav-label">Logout</span></a>
    </aside>

    <main class="dashboard-content">
        <header class="page-header reveal-on-load">
            <div>
                <p class="eyebrow">Master portal</p>
                <h1><?php echo master_h($title); ?></h1>
                <?php if ($subtitle !== ''): ?>
                    <span class="page-subtitle"><?php echo master_h($subtitle); ?></span>
                <?php endif; ?>
            </div>
            <div class="header-actions">
                <span class="last-updated"><i class="fas fa-clock"></i> Updated <?php echo date('M d, Y g:i A'); ?></span>
            </div>
        </header>
    <?php
}

function master_page_end(array $extraScripts = []): void
{
    ?>
    </main>
    <script src="master_dashboard.js"></script>
    <?php foreach ($extraScripts as $script): ?>
        <script src="<?php echo master_h($script); ?>"></script>
    <?php endforeach; ?>
</body>

</html>
    <?php
}
