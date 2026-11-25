<?php
require_once __DIR__ . "/_init.php";

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        csrf_verify();

        if (isset($_POST["add_commission"])) {

            $name    = trim($_POST["referral_name"] ?? "");
            $patient = trim($_POST["patient_name"] ?? "");
            $billNo  = trim($_POST["bill_no"] ?? "");
            $amount  = (float)($_POST["amount"] ?? 0);
            $date    = $_POST["commission_date"] ?? "";
            $notes   = trim($_POST["notes"] ?? "");

            if ($name === "" || $date === "" || $amount <= 0) {
                throw new Exception("Please enter referral name, valid amount and date.");
            }

            if (!valid_date($date)) {
                throw new Exception("Invalid commission date.");
            }

            $stmt = $conn->prepare("
                INSERT INTO commissions
                (referral_name, patient_name, bill_no, amount, commission_date, notes)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $patient, $billNo, $amount, $date, $notes]);

            $success = "Commission record added successfully!";
        }

        if (isset($_POST["delete_commission"])) {
            $stmt = $conn->prepare("DELETE FROM commissions WHERE id = ?");
            $stmt->execute([(int)($_POST["commission_id"] ?? 0)]);

            $success = "Commission record deleted.";
        }

        csrf_rotate();

    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$total = (float)$conn->query("SELECT COALESCE(SUM(amount),0) FROM commissions")->fetchColumn();
$today = (float)$conn->query("SELECT COALESCE(SUM(amount),0) FROM commissions WHERE commission_date = date('now','localtime')")->fetchColumn();
$count = (int)$conn->query("SELECT COUNT(*) FROM commissions")->fetchColumn();

$records = $conn->query("SELECT * FROM commissions ORDER BY id DESC LIMIT 100")->fetchAll();

page_start("Commission", "commission", "Commission Management", "Manage doctor and referral commissions");
alerts($success, $error);
?>

    <div class="grid cols-3">
        <div class="stat-card">
            <h6 class="text-muted">Total Commission</h6>
            <div class="stat-value blue">PKR <?= money($total) ?></div>
        </div>
        <div class="stat-card">
            <h6 class="text-muted">Today's Commission</h6>
            <div class="stat-value blue">PKR <?= money($today) ?></div>
        </div>
        <div class="stat-card">
            <h6 class="text-muted">Total Records</h6>
            <div class="stat-value blue"><?= number_format($count) ?></div>
        </div>
    </div>

    <div class="card">
        <h4 class="mb">Add Commission</h4>

        <form method="POST">
            <?= csrf_field() ?>

            <div class="form-grid">
                <div class="field">
                    <label>Doctor / Referral Name *</label>
                    <input type="text" name="referral_name" required maxlength="150">
                </div>

                <div class="field">
                    <label>Patient Name</label>
                    <input type="text" name="patient_name">
                </div>
            </div>

            <div class="form-grid three mt">
                <div class="field">
                    <label>Bill Number</label>
                    <input type="text" name="bill_no">
                </div>

                <div class="field">
                    <label>Commission Amount (PKR) *</label>
                    <input type="number" name="amount" min="0.01" step="0.01" required>
                </div>

                <div class="field">
                    <label>Commission Date *</label>
                    <input type="date" name="commission_date" value="<?= date("Y-m-d") ?>" required>
                </div>
            </div>

            <div class="field mt">
                <label>Notes</label>
                <textarea name="notes" rows="2" placeholder="Optional details"></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" name="add_commission" class="btn btn-primary">Save Commission</button>
            </div>
        </form>
    </div>

    <div class="card">
        <h4 class="mb">Commission Records</h4>

        <div class="field mb">
            <input type="text" id="search" placeholder="Search referral, patient or bill...">
        </div>

        <div class="table-wrap">
            <table class="data" id="recordsTable">
                <thead>
                    <tr>
                        <th>ID</th><th>Referral</th><th>Patient</th><th>Bill No.</th>
                        <th>Amount</th><th>Date</th><th>Notes</th><th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($records): ?>
                    <?php foreach ($records as $r): ?>
                        <tr>
                            <td><?= (int)$r["id"] ?></td>
                            <td><?= h($r["referral_name"]) ?></td>
                            <td><?= h($r["patient_name"]) ?></td>
                            <td><?= h($r["bill_no"]) ?></td>
                            <td>PKR <?= money($r["amount"]) ?></td>
                            <td><?= h($r["commission_date"]) ?></td>
                            <td><?= h($r["notes"]) ?></td>
                            <td>
                                <form method="POST" onsubmit="return confirm('Delete this commission record?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="commission_id" value="<?= (int)$r["id"] ?>">
                                    <button name="delete_commission" class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="empty-row">No commission records yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<script>
document.getElementById("search").addEventListener("input", function () {
    const q = this.value.toLowerCase();
    document.querySelectorAll("#recordsTable tbody tr").forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(q) ? "" : "none";
    });
});
</script>

<?php page_end(); ?>
