<?php
require_once __DIR__ . "/_init.php";

// ---------- Date filter ----------
$from = $_GET["from"] ?? date("Y-m-01");
$to   = $_GET["to"] ?? date("Y-m-d");

if (!valid_date($from) || !valid_date($to) || $from > $to) {
    $from = date("Y-m-01");
    $to   = date("Y-m-d");
}

function rows($conn, $sql, $from, $to) {
    $stmt = $conn->prepare($sql);
    $stmt->execute([$from, $to]);
    return $stmt->fetchAll();
}

// ---------- Bills ----------
$summary = rows($conn, "
    SELECT COUNT(*) AS bill_count,
           COALESCE(SUM(grand_total),0) AS billed,
           COALESCE(SUM(paid_amount),0) AS paid,
           COALESCE(SUM(balance),0) AS balance
    FROM bills
    WHERE DATE(created_at) BETWEEN ? AND ?
", $from, $to)[0];

$totalBills   = (int)$summary["bill_count"];
$billedAmount = (float)$summary["billed"];
$revenue      = (float)$summary["paid"];
$receivable   = (float)$summary["balance"];

$bills = rows($conn, "
    SELECT bill_no, patient_name, DATE(created_at) AS record_date,
           grand_total, paid_amount, balance
    FROM bills
    WHERE DATE(created_at) BETWEEN ? AND ?
    ORDER BY id DESC LIMIT 200
", $from, $to);

// ---------- Expenses ----------
$expenseRecords = rows($conn, "
    SELECT id, expense_title AS description, amount, expense_date AS record_date
    FROM expenses
    WHERE expense_date BETWEEN ? AND ?
    ORDER BY id DESC LIMIT 200
", $from, $to);

$expenses = (float)rows($conn, "
    SELECT COALESCE(SUM(amount),0) AS t FROM expenses WHERE expense_date BETWEEN ? AND ?
", $from, $to)[0]["t"];

// ---------- Commissions ----------
$commissionRecords = rows($conn, "
    SELECT id, referral_name AS description, amount, commission_date AS record_date
    FROM commissions
    WHERE commission_date BETWEEN ? AND ?
    ORDER BY id DESC LIMIT 200
", $from, $to);

$commissions = (float)rows($conn, "
    SELECT COALESCE(SUM(amount),0) AS t FROM commissions WHERE commission_date BETWEEN ? AND ?
", $from, $to)[0]["t"];

$netIncome = $revenue - $expenses - $commissions;

function simple_table($headers, $records, $emptyText) {
    ?>
    <div class="table-wrap">
        <table class="data">
            <thead><tr>
                <?php foreach ($headers as $hd): ?><th><?= h($hd) ?></th><?php endforeach; ?>
            </tr></thead>
            <tbody>
            <?php if ($records): ?>
                <?php foreach ($records as $r): ?>
                    <tr>
                        <td><?= (int)$r["id"] ?></td>
                        <td><?= h($r["description"]) ?></td>
                        <td><?= h($r["record_date"]) ?></td>
                        <td>PKR <?= money($r["amount"]) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="<?= count($headers) ?>" class="empty-row"><?= h($emptyText) ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

page_start("Reports", "reports", "Financial Reports", "Laboratory financial performance and records");
?>

    <div class="card no-print">
        <h5 class="mb">Select Report Period</h5>

        <form method="GET">
            <div class="form-grid three" style="align-items:end;">
                <div class="field">
                    <label>From Date</label>
                    <input type="date" name="from" value="<?= h($from) ?>" required>
                </div>

                <div class="field">
                    <label>To Date</label>
                    <input type="date" name="to" value="<?= h($to) ?>" required>
                </div>

                <div class="form-actions" style="margin-top:0;">
                    <button type="submit" class="btn btn-primary">Generate Report</button>
                    <button type="button" onclick="window.print()" class="btn btn-outline">Print Report</button>
                </div>
            </div>
        </form>
    </div>

    <h5 class="section-heading">Report Period: <?= h($from) ?> to <?= h($to) ?></h5>

    <div class="grid cols-3">
        <div class="stat-card"><div class="text-muted">Total Bills</div><div class="stat-value blue"><?= number_format($totalBills) ?></div></div>
        <div class="stat-card"><div class="text-muted">Total Billed Amount</div><div class="stat-value blue">PKR <?= money($billedAmount) ?></div></div>
        <div class="stat-card"><div class="text-muted">Collected Revenue</div><div class="stat-value blue">PKR <?= money($revenue) ?></div></div>
        <div class="stat-card"><div class="text-muted">Remaining Receivable</div><div class="stat-value blue">PKR <?= money($receivable) ?></div></div>
        <div class="stat-card"><div class="text-muted">Total Expenses</div><div class="stat-value blue">PKR <?= money($expenses) ?></div></div>
        <div class="stat-card"><div class="text-muted">Doctor Commissions</div><div class="stat-value blue">PKR <?= money($commissions) ?></div></div>
    </div>

    <div class="card">
        <h4>Net Income Summary</h4>
        <p class="text-muted mb">Collected revenue minus recorded expenses and commissions.</p>
        <h2 class="<?= $netIncome >= 0 ? "text-success" : "text-danger" ?>">PKR <?= money($netIncome) ?></h2>
    </div>

    <div class="card">
        <h4 class="mb">Bill Records</h4>

        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr><th>Bill No.</th><th>Patient</th><th>Date</th><th>Total</th><th>Paid</th><th>Balance</th></tr>
                </thead>
                <tbody>
                <?php if ($bills): ?>
                    <?php foreach ($bills as $r): ?>
                        <tr>
                            <td><?= h($r["bill_no"]) ?></td>
                            <td><?= h($r["patient_name"]) ?></td>
                            <td><?= h($r["record_date"]) ?></td>
                            <td>PKR <?= money($r["grand_total"]) ?></td>
                            <td>PKR <?= money($r["paid_amount"]) ?></td>
                            <td>PKR <?= money($r["balance"]) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="empty-row">No bill records available.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h4 class="mb">Expense Records</h4>
        <?php simple_table(["ID", "Expense", "Date", "Amount"], $expenseRecords, "No expense records available."); ?>
    </div>

    <div class="card">
        <h4 class="mb">Commission Records</h4>
        <?php simple_table(["ID", "Referral / Doctor", "Date", "Amount"], $commissionRecords, "No commission records available."); ?>
    </div>

<?php page_end(); ?>
