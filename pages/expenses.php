<?php
require_once __DIR__ . "/_init.php";

$error = "";
$success = "";

$allowedMethods = ["Cash", "Bank Transfer", "Card", "Online"];
$categories = ["Electricity","Rent","Staff Salary","Equipment","Maintenance","Medical Supplies","Internet","Transport","Other"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        csrf_verify();

        if (isset($_POST["add_expense"])) {

            $title       = trim($_POST["expense_title"] ?? "");
            $category    = trim($_POST["category"] ?? "");
            $amount      = (float)($_POST["amount"] ?? 0);
            $date        = $_POST["expense_date"] ?? "";
            $method      = $_POST["payment_method"] ?? "Cash";
            $description = trim($_POST["description"] ?? "");

            if ($title === "" || $category === "" || $amount <= 0 || $date === "") {
                throw new Exception("Please complete all required fields with valid values.");
            }

            if (!in_array($method, $allowedMethods, true)) {
                throw new Exception("Invalid payment method.");
            }

            if (!valid_date($date)) {
                throw new Exception("Invalid expense date.");
            }

            $stmt = $conn->prepare("
                INSERT INTO expenses
                (expense_title, category, amount, expense_date, payment_method, description)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$title, $category, $amount, $date, $method, $description]);

            $success = "Expense saved successfully!";
        }

        if (isset($_POST["delete_expense"])) {
            $stmt = $conn->prepare("DELETE FROM expenses WHERE id = ?");
            $stmt->execute([(int)($_POST["expense_id"] ?? 0)]);

            $success = "Expense deleted successfully.";
        }

        csrf_rotate();

    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$totalExpense = (float)$conn->query("SELECT COALESCE(SUM(amount),0) FROM expenses")->fetchColumn();
$todayExpense = (float)$conn->query("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE expense_date = date('now','localtime')")->fetchColumn();
$monthExpense = (float)$conn->query("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE strftime('%Y-%m', expense_date) = strftime('%Y-%m', 'now','localtime')")->fetchColumn();

$records = $conn->query("SELECT * FROM expenses ORDER BY expense_date DESC, id DESC LIMIT 200")->fetchAll();

page_start("Expenses", "expenses", "Expense Management", "Track laboratory operating expenses");
alerts($success, $error);
?>

    <div class="grid cols-3">
        <div class="stat-card">
            <h6 class="text-muted">Total Expenses</h6>
            <div class="stat-value blue">PKR <?= money($totalExpense) ?></div>
        </div>
        <div class="stat-card">
            <h6 class="text-muted">Today's Expenses</h6>
            <div class="stat-value blue">PKR <?= money($todayExpense) ?></div>
        </div>
        <div class="stat-card">
            <h6 class="text-muted">This Month</h6>
            <div class="stat-value blue">PKR <?= money($monthExpense) ?></div>
        </div>
    </div>

    <div class="card">
        <h4 class="mb">Add New Expense</h4>

        <form method="POST">
            <?= csrf_field() ?>

            <div class="form-grid">
                <div class="field">
                    <label>Expense Title *</label>
                    <input type="text" name="expense_title" required maxlength="150" placeholder="e.g. Electricity Bill">
                </div>

                <div class="field">
                    <label>Category *</label>
                    <select name="category" required>
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $c): ?>
                            <option><?= h($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-grid three mt">
                <div class="field">
                    <label>Amount (PKR) *</label>
                    <input type="number" name="amount" min="0.01" step="0.01" required>
                </div>

                <div class="field">
                    <label>Expense Date *</label>
                    <input type="date" name="expense_date" value="<?= date("Y-m-d") ?>" required>
                </div>

                <div class="field">
                    <label>Payment Method</label>
                    <select name="payment_method">
                        <?php foreach ($allowedMethods as $m): ?>
                            <option><?= h($m) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="field mt">
                <label>Description</label>
                <textarea name="description" rows="2" placeholder="Optional details..."></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" name="add_expense" class="btn btn-primary">Save Expense</button>
            </div>
        </form>
    </div>

    <div class="card">
        <h4 class="mb">Expense History</h4>

        <div class="field mb">
            <input type="text" id="search" placeholder="Search expense...">
        </div>

        <div class="table-wrap">
            <table class="data" id="expenseTable">
                <thead>
                    <tr>
                        <th>ID</th><th>Title</th><th>Category</th><th>Amount</th>
                        <th>Date</th><th>Payment</th><th>Description</th><th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($records): ?>
                    <?php foreach ($records as $r): ?>
                        <tr>
                            <td><?= (int)$r["id"] ?></td>
                            <td><?= h($r["expense_title"]) ?></td>
                            <td><?= h($r["category"]) ?></td>
                            <td>PKR <?= money($r["amount"]) ?></td>
                            <td><?= h($r["expense_date"]) ?></td>
                            <td><?= h($r["payment_method"]) ?></td>
                            <td><?= h($r["description"]) ?></td>
                            <td>
                                <form method="POST" onsubmit="return confirm('Delete this expense?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="expense_id" value="<?= (int)$r["id"] ?>">
                                    <button name="delete_expense" class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="empty-row">No expenses recorded yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<script>
document.getElementById("search").addEventListener("input", function () {
    const q = this.value.toLowerCase();
    document.querySelectorAll("#expenseTable tbody tr").forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(q) ? "" : "none";
    });
});
</script>

<?php page_end(); ?>
