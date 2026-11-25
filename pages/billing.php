<?php
require_once __DIR__ . "/_init.php";

$error = "";
$success = "";
$createdBillId = 0;

// ---------------- Print bill ----------------
if (isset($_GET["print"])) {

    $printId = (int)$_GET["print"];

    $stmt = $conn->prepare("SELECT * FROM bills WHERE id = ?");
    $stmt->execute([$printId]);
    $bill = $stmt->fetch();

    if ($bill) {
        $stmt = $conn->prepare("SELECT test_name, price FROM bill_items WHERE bill_id = ?");
        $stmt->execute([$printId]);
        $items = $stmt->fetchAll();

        $labName = $conn->query(
            "SELECT setting_value FROM labpro_app_settings WHERE setting_key = 'lab_name'"
        )->fetchColumn() ?: "LabPro Laboratory";
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>LabPro Bill</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f3f5f9; padding: 25px; }
        .receipt { max-width: 650px; margin: auto; background: #fff; padding: 30px; border: 1px solid #ddd; }
        h1, .center { text-align: center; }
        .muted { color: #777; text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        td, th { padding: 12px; border-bottom: 1px solid #ddd; text-align: left; }
        .right { text-align: right; }
        .actions { text-align: center; margin: 20px; }
        button { padding: 12px 22px; background: #2563eb; color: #fff; border: 0; border-radius: 6px; cursor: pointer; }
        @media print { body { background: #fff; padding: 0; } .receipt { border: 0; } .actions { display: none; } }
    </style>
</head>
<body>
<div class="receipt">
    <h1><?= h($labName) ?></h1>
    <p class="muted">Laboratory Bill / Receipt</p>
    <hr>

    <p><strong>Bill No:</strong> <?= h($bill["bill_no"]) ?></p>
    <p><strong>Patient:</strong> <?= h($bill["patient_name"]) ?></p>
    <p><strong>Date:</strong> <?= h($bill["created_at"]) ?></p>

    <table>
        <thead><tr><th>Test Name</th><th class="right">Price (PKR)</th></tr></thead>
        <tbody>
        <?php foreach ($items as $item): ?>
            <tr>
                <td><?= h($item["test_name"]) ?></td>
                <td class="right"><?= money($item["price"]) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <table>
        <tr><td>Total</td><td class="right">PKR <?= money($bill["total_amount"]) ?></td></tr>
        <tr><td>Discount</td><td class="right">PKR <?= money($bill["discount"]) ?></td></tr>
        <tr><th>Grand Total</th><th class="right">PKR <?= money($bill["grand_total"]) ?></th></tr>
        <tr><td>Paid</td><td class="right">PKR <?= money($bill["paid_amount"]) ?></td></tr>
        <tr><th>Remaining Balance</th><th class="right">PKR <?= money($bill["balance"]) ?></th></tr>
    </table>

    <p class="center">Thank you for choosing <?= h($labName) ?>!</p>
</div>

<div class="actions">
    <button onclick="window.print()">Print Bill</button>
    <button onclick="location.href='billing.php'">Back</button>
</div>
</body>
</html>
        <?php
        exit;
    }
}

// ---------------- Save bill ----------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["create_bill"])) {

    try {
        csrf_verify();

        $patientId = (int)($_POST["patient_id"] ?? 0);

        $testIds = array_values(array_unique(array_filter(
            array_map("intval", $_POST["test_ids"] ?? []),
            fn($id) => $id > 0
        )));

        $discount = (float)($_POST["discount"] ?? 0);
        $paid     = (float)($_POST["paid_amount"] ?? 0);

        if ($patientId <= 0 || empty($testIds)) {
            throw new Exception("Please select a patient and at least one test.");
        }

        if ($discount < 0 || $paid < 0) {
            throw new Exception("Amounts cannot be negative.");
        }

        // Patient
        $stmt = $conn->prepare("SELECT id, name FROM patients WHERE id = ?");
        $stmt->execute([$patientId]);
        $patient = $stmt->fetch();

        if (!$patient) {
            throw new Exception("Patient not found.");
        }

        // Tests (prices are read from the database, never from the browser)
        $placeholders = implode(",", array_fill(0, count($testIds), "?"));

        $stmt = $conn->prepare("SELECT id, test_name, price FROM tests WHERE id IN ($placeholders)");
        $stmt->execute($testIds);
        $selectedTests = $stmt->fetchAll();

        if (count($selectedTests) !== count($testIds)) {
            throw new Exception("One or more tests were not found.");
        }

        $total = 0;
        foreach ($selectedTests as $t) {
            $total += (float)$t["price"];
        }

        if ($discount > $total) {
            throw new Exception("Discount cannot exceed total amount.");
        }

        $grandTotal = $total - $discount;

        if ($paid > $grandTotal) {
            throw new Exception("Paid amount cannot exceed grand total.");
        }

        $balance = $grandTotal - $paid;
        $billNo = "LP-" . date("Ymd") . "-" . strtoupper(bin2hex(random_bytes(3)));

        $conn->beginTransaction();

        try {
            $stmt = $conn->prepare("
                INSERT INTO bills
                (bill_no, patient_id, patient_name, total_amount, discount, grand_total, paid_amount, balance)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $billNo, $patientId, $patient["name"],
                $total, $discount, $grandTotal, $paid, $balance
            ]);

            $billId = (int)$conn->lastInsertId();

            $itemStmt = $conn->prepare(
                "INSERT INTO bill_items (bill_id, test_id, test_name, price) VALUES (?, ?, ?, ?)"
            );

            foreach ($selectedTests as $t) {
                $itemStmt->execute([$billId, (int)$t["id"], $t["test_name"], (float)$t["price"]]);
            }

            $conn->commit();

        } catch (Throwable $e) {
            $conn->rollBack();
            throw $e;
        }

        $createdBillId = $billId;
        $success = "Bill created successfully!";
        csrf_rotate();

    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

// ---------------- Load data ----------------
$patients = $conn->query("SELECT id, name FROM patients ORDER BY id DESC")->fetchAll();
$tests = $conn->query("SELECT id, test_name, price FROM tests WHERE status = 'active' ORDER BY test_name ASC")->fetchAll();
$recentBills = $conn->query("SELECT * FROM bills ORDER BY id DESC LIMIT 50")->fetchAll();

$totalBills   = (int)$conn->query("SELECT COUNT(*) FROM bills")->fetchColumn();
$totalRevenue = (float)$conn->query("SELECT COALESCE(SUM(paid_amount),0) FROM bills")->fetchColumn();
$totalBalance = (float)$conn->query("SELECT COALESCE(SUM(balance),0) FROM bills")->fetchColumn();

page_start("Billing", "billing", "Billing Management", "Create and manage patient bills");
?>

    <?php if ($error !== ""): ?>
        <div class="alert alert-danger"><?= h($error) ?></div>
    <?php endif; ?>

    <?php if ($success !== ""): ?>
        <div class="alert alert-success">
            <?= h($success) ?>
            <a href="billing.php?print=<?= (int)$createdBillId ?>">View / Print Bill</a>
        </div>
    <?php endif; ?>

    <div class="grid cols-3">
        <div class="stat-card">
            <h6 class="text-muted">Total Bills</h6>
            <div class="stat-value blue"><?= number_format($totalBills) ?></div>
        </div>
        <div class="stat-card">
            <h6 class="text-muted">Total Collected</h6>
            <div class="stat-value blue">PKR <?= money($totalRevenue) ?></div>
        </div>
        <div class="stat-card">
            <h6 class="text-muted">Remaining Balance</h6>
            <div class="stat-value blue">PKR <?= money($totalBalance) ?></div>
        </div>
    </div>

    <div class="card">
        <h4 class="mb">Create New Bill</h4>

        <form method="POST" id="billingForm">
            <?= csrf_field() ?>

            <div class="form-grid">

                <div class="field">
                    <label>Select Patient *</label>
                    <select name="patient_id" required>
                        <option value="">Choose Patient</option>
                        <?php foreach ($patients as $p): ?>
                            <option value="<?= (int)$p["id"] ?>"><?= h($p["name"]) ?> (ID: <?= (int)$p["id"] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label>Search Test</label>
                    <input type="text" id="testSearch" placeholder="Search test name...">
                </div>

                <div class="field" style="grid-column:1/-1;">
                    <label>Select Laboratory Tests *</label>

                    <div class="test-list">
                        <?php if ($tests): ?>
                            <?php foreach ($tests as $t): ?>
                                <div class="test-option" data-test-name="<?= h(strtolower($t["test_name"])) ?>">
                                    <label>
                                        <input type="checkbox" class="test-checkbox" name="test_ids[]"
                                               value="<?= (int)$t["id"] ?>"
                                               data-price="<?= h($t["price"]) ?>">
                                        <?= h($t["test_name"]) ?>
                                    </label>
                                    <strong>PKR <?= money($t["price"]) ?></strong>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-danger">No tests found. Add tests first from the Tests page.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="field">
                    <label>Discount (PKR)</label>
                    <input type="number" name="discount" id="discount" min="0" step="0.01" value="0">
                </div>

                <div class="field">
                    <label>Paid Amount (PKR) *</label>
                    <input type="number" name="paid_amount" id="paid" min="0" step="0.01" value="0" required>
                </div>

            </div>

            <div class="summary mt">
                <div class="flex-between"><span>Subtotal</span><strong id="subtotal">PKR 0.00</strong></div>
                <div class="flex-between"><span>Discount</span><strong id="discountDisplay">PKR 0.00</strong></div>
                <hr>
                <div class="flex-between"><strong>Grand Total</strong><strong id="grandTotal">PKR 0.00</strong></div>
                <div class="flex-between"><span>Remaining Balance</span><strong id="balance">PKR 0.00</strong></div>
            </div>

            <div class="form-actions">
                <button type="submit" name="create_bill" class="btn btn-primary">Save Bill</button>
                <button type="reset" class="btn btn-outline" id="resetBtn">Reset</button>
            </div>
        </form>
    </div>

    <div class="card">
        <h4 class="mb">Recent Bills</h4>

        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>Bill No.</th><th>Patient</th><th>Date</th>
                        <th>Total</th><th>Paid</th><th>Balance</th><th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($recentBills): ?>
                    <?php foreach ($recentBills as $b): ?>
                        <tr>
                            <td><?= h($b["bill_no"]) ?></td>
                            <td><?= h($b["patient_name"]) ?></td>
                            <td><?= h($b["created_at"]) ?></td>
                            <td>PKR <?= money($b["grand_total"]) ?></td>
                            <td>PKR <?= money($b["paid_amount"]) ?></td>
                            <td>
                                <span class="<?= (float)$b["balance"] > 0 ? "text-danger" : "text-success" ?>">
                                    PKR <?= money($b["balance"]) ?>
                                </span>
                            </td>
                            <td>
                                <a href="billing.php?print=<?= (int)$b["id"] ?>" class="btn btn-sm btn-outline-primary">Print</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="empty-row">No bills created yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<script>
const checkboxes = document.querySelectorAll(".test-checkbox");
const discountInput = document.getElementById("discount");
const paidInput = document.getElementById("paid");

function calculateBill() {
    let subtotal = 0;
    checkboxes.forEach(box => { if (box.checked) subtotal += Number(box.dataset.price); });

    const discount = Math.max(0, Number(discountInput.value) || 0);
    const paid = Math.max(0, Number(paidInput.value) || 0);
    const grandTotal = Math.max(0, subtotal - discount);
    const balance = Math.max(0, grandTotal - paid);

    document.getElementById("subtotal").textContent = "PKR " + subtotal.toFixed(2);
    document.getElementById("discountDisplay").textContent = "PKR " + discount.toFixed(2);
    document.getElementById("grandTotal").textContent = "PKR " + grandTotal.toFixed(2);
    document.getElementById("balance").textContent = "PKR " + balance.toFixed(2);
}

checkboxes.forEach(box => box.addEventListener("change", calculateBill));
discountInput.addEventListener("input", calculateBill);
paidInput.addEventListener("input", calculateBill);

document.getElementById("testSearch").addEventListener("input", function () {
    const search = this.value.toLowerCase();
    document.querySelectorAll(".test-option").forEach(option => {
        option.style.display = option.dataset.testName.includes(search) ? "flex" : "none";
    });
});

document.getElementById("resetBtn").addEventListener("click", function () {
    setTimeout(calculateBill, 0);
});

document.getElementById("billingForm").addEventListener("submit", function (event) {
    const selected = document.querySelectorAll(".test-checkbox:checked");
    const subtotal = [...selected].reduce((sum, box) => sum + Number(box.dataset.price), 0);
    const discount = Number(discountInput.value) || 0;
    const paid = Number(paidInput.value) || 0;

    if (selected.length === 0) {
        event.preventDefault();
        alert("Please select at least one laboratory test.");
    } else if (discount > subtotal) {
        event.preventDefault();
        alert("Discount cannot be greater than subtotal.");
    } else if (paid > subtotal - discount) {
        event.preventDefault();
        alert("Paid amount cannot be greater than grand total.");
    }
});

calculateBill();
</script>

<?php page_end(); ?>
