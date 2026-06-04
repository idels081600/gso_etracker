<?php
$user = current_user();
$area = role_area();
$nav = [
    'employee' => [
        ['employee', 'My Pass Slip'],
        ['request-new', 'New Request'],
    ],
    'desk' => [
        ['scanner', 'Scanner'],
        ['tracking', 'Tracking'],
        ['dashboard', 'Approvals'],
        ['reports', 'Reports'],
    ],
    'approver' => [
        ['dashboard', 'Approvals'],
        ['tracking', 'Tracking'],
        ['scanner', 'Scanner'],
        ['reports', 'Reports'],
    ],
    'super_admin' => [
        ['dashboard', 'Approvals'],
        ['tracking', 'Tracking'],
        ['scanner', 'Scanner'],
        ['reports', 'Reports'],
        ['users', 'Users'],
        ['audit', 'Audit'],
    ],
    'guest' => [],
][$area] ?? [];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= h(csrf_token()) ?>">
    <title><?= h($title ?? 'E-Pass Slip') ?></title>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<div class="app-shell">
    <header class="topbar">
        <a class="brand" href="<?= h(app_url()) ?>">
            <img src="../logo.png" alt="" class="brand-mark">
            <span>
                <strong>E-Pass Slip</strong>
                <small><?= h($area === 'guest' ? 'Modern workspace' : ucwords(str_replace('_', ' ', $area))) ?></small>
            </span>
        </a>
        <?php if ($nav): ?>
            <button class="nav-toggle" type="button" data-nav-toggle aria-label="Toggle navigation">Menu</button>
            <nav class="nav" data-nav>
                <?php foreach ($nav as [$key, $label]): ?>
                    <a class="<?= ($active ?? '') === $key ? 'is-active' : '' ?>" href="<?= h(app_url(['page' => $key])) ?>"><?= h($label) ?></a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>
        <div class="user-chip">
            <span><?= h($user['name']) ?></span>
            <small><?= h($user['role'] ?? 'Not signed in') ?></small>
        </div>
    </header>

    <main class="page">
        <?php foreach (consume_flash() as $message): ?>
            <div class="notice notice-<?= h($message['type']) ?>" role="status"><?= h($message['message']) ?></div>
        <?php endforeach; ?>
        <?= $content ?>
    </main>
</div>
<div class="toast-stack" data-toast-stack></div>
<script src="assets/app.js"></script>
</body>
</html>
