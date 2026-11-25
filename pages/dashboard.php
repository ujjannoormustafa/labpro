<?php
require_once __DIR__ . "/_init.php";

function scalar($conn, $sql) {
    return $conn->query($sql)->fetchColumn();
}

function stat_card($label, $value, $icon, $color) {
    ?>
    <div class="stat-card">
        <div class="stat-top">
            <h6><?= h($label) ?></h6>
            <div class="stat-icon ic-<?= h($color) ?>"><?= $icon ?></div>
        </div>
        <div class="stat-value"><?= h($value) ?></div>
    </div>
    <?php
}

$totalPatients   = (int)scalar($conn, "SELECT COUNT(*) FROM patients");
$todayPatients   = (int)scalar($conn, "SELECT COUNT(*) FROM patients WHERE DATE(created_at) = date('now','localtime')");
$totalTests      = (int)scalar($conn, "SELECT COUNT(*) FROM tests");
$totalBills      = (int)scalar($conn, "SELECT COUNT(*) FROM bills");

$grossRevenue    = (float)scalar($conn, "SELECT COALESCE(SUM(grand_total),0) FROM bills");
$totalPaid       = (float)scalar($conn, "SELECT COALESCE(SUM(paid_amount),0) FROM bills");
$totalBalance    = (float)scalar($conn, "SELECT COALESCE(SUM(balance),0) FROM bills");
$totalExpenses   = (float)scalar($conn, "SELECT COALESCE(SUM(amount),0) FROM expenses");
$totalCommission = (float)scalar($conn, "SELECT COALESCE(SUM(amount),0) FROM commissions");

$netProfit = $totalPaid - $totalExpenses - $totalCommission;

$recentPatients = $conn->query(
    "SELECT patient_code, name, phone, created_at
     FROM patients ORDER BY id DESC LIMIT 5"
)->fetchAll();

page_start("Dashboard", "dashboard", "Dashboard", "Welcome back, " . $adminName . "!");
?>

    <h5 class="section-heading">📊 Laboratory Overview</h5>

    <div class="grid cols-4">
        <?php
        stat_card("Total Patients", number_format($totalPatients), "👥", "blue");
        stat_card("Today's Patients", number_format($todayPatients), "➕", "green");
        stat_card("Total Tests", number_format($totalTests), "🧪", "purple");
        stat_card("Total Bills", number_format($totalBills), "🧾", "orange");
        stat_card("Total Revenue", "Rs. " . money($grossRevenue), "💵", "green");
        stat_card("Total Received", "Rs. " . money($totalPaid), "👛", "cyan");
        stat_card("Pending Balance", "Rs. " . money($totalBalance), "⏳", "orange");
        stat_card("Net Profit", "Rs. " . money($netProfit), "📈", "purple");
        ?>
    </div>

    <h5 class="section-heading">💰 Financial Overview</h5>

    <div class="grid cols-2">
        <?php
        stat_card("Total Expenses", "Rs. " . money($totalExpenses), "🧾", "red");
        stat_card("Doctor Commissions", "Rs. " . money($totalCommission), "🩺", "purple");
        ?>
    </div>

    <div class="card">
        <h5 class="mb">👥 Recent Patients</h5>

        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>Patient Code</th>
                        <th>Patient Name</th>
                        <th>Phone</th>
                        <th>Registration Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($recentPatients): ?>
                    <?php foreach ($recentPatients as $p): ?>
                        <tr>
                            <td><span class="badge badge-blue"><?= h($p["patient_code"]) ?></span></td>
                            <td><?= h($p["name"]) ?></td>
                            <td><?= h($p["phone"]) ?></td>
                            <td><?= h(date("d M Y", strtotime($p["created_at"]))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" class="empty-row">No patient records found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php page_end(); ?>
