<?php
// Sidebar menu. $active is set by page_start().
$menu = [
    "MAIN MENU" => [
        "dashboard"   => ["Dashboard", "📊"],
        "patients"    => ["Patients", "👥"],
        "add-patient" => ["Add Patient", "➕"],
        "tests"       => ["Tests", "🧪"],
    ],
    "FINANCIAL MANAGEMENT" => [
        "billing"     => ["Billing", "🧾"],
        "commission"  => ["Commission", "🤝"],
        "expenses"    => ["Expenses", "💸"],
    ],
    "MANAGEMENT" => [
        "reports"     => ["Reports", "📈"],
        "settings"    => ["Settings", "⚙️"],
    ],
];
?>
<aside class="sidebar">

    <div class="logo">Lab<span>Pro</span></div>

    <?php foreach ($menu as $group => $items): ?>
        <div class="menu-title"><?= h($group) ?></div>

        <?php foreach ($items as $file => $item): ?>
            <a href="<?= h($file) ?>.php"
               class="<?= $active === $file ? "active" : "" ?>">
                <span class="ico"><?= $item[1] ?></span>
                <span class="lbl"><?= h($item[0]) ?></span>
            </a>
        <?php endforeach; ?>
    <?php endforeach; ?>

    <a href="logout.php" class="logout"
       onclick="return confirm('Are you sure you want to logout?');">
        <span class="ico">🚪</span>
        <span class="lbl">Logout</span>
    </a>

</aside>
