<?php
$payablesActivePage = $payablesActivePage ?? '';
$payablesFullName = $full_name ?? '';
$payablesNav = [
    'monitoring' => ['href' => 'bac_monitoring.php', 'icon' => 'fas fa-th-large', 'label' => 'Dashboard'],
    'bac' => ['href' => 'transmittal_bac.php', 'icon' => 'fas fa-gavel', 'label' => 'Bidding & RFQ'],
    'po' => ['href' => 'Po_sap.php', 'icon' => 'fas fa-shopping-cart', 'label' => 'Purchase Order'],
];
?>
<button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar" aria-expanded="true">
    <i class="fas fa-bars"></i>
</button>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<div class="sidebar" id="payablesSidebar">
    <div class="logo">
        <img src="logo.png" alt="Logo">
        <div class="sidebar-user">
            <span class="role">Admin</span>
            <span class="user-name"><?php echo htmlspecialchars($payablesFullName, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
    </div>
    <hr class="divider">
    <ul>
        <?php foreach ($payablesNav as $key => $item): ?>
            <li>
                <a href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $payablesActivePage === $key ? 'active' : ''; ?>">
                    <i class="<?php echo htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8'); ?> icon-size"></i><span class="nav-label"><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
    <a href="../logout.php" class="logout-item"><i class="fas fa-sign-out-alt icon-size"></i><span class="nav-label">Logout</span></a>
</div>
<script src="payables_sidebar.js" defer></script>
