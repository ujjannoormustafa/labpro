<?php
require_once __DIR__ . "/_init.php";

$message = "";
$error = "";
$tests = [];
$totalTests = 0;
$search = trim($_GET["search"] ?? "");

try {

    if ($_SERVER["REQUEST_METHOD"] === "POST") {

        csrf_verify();

        $testName    = trim($_POST["test_name"] ?? "");
        $testCode    = trim($_POST["test_code"] ?? "");
        $category    = trim($_POST["category"] ?? "");
        $price       = trim($_POST["price"] ?? "");
        $description = trim($_POST["description"] ?? "");

        if ($testName === "") throw new Exception("Test name is required.");
        if (strlen($testName) > 150) throw new Exception("Test name is too long.");

        if ($price === "" || !is_numeric($price) || (float)$price < 0) {
            throw new Exception("Enter a valid test price.");
        }

        $stmt = $conn->prepare("
            INSERT INTO tests (test_code, test_name, category, price, description, status)
            VALUES (?, ?, ?, ?, ?, 'active')
        ");
        $stmt->execute([$testCode, $testName, $category, (float)$price, $description]);

        $message = "New laboratory test added successfully!";
        csrf_rotate();
    }

    $where = "";
    $params = [];

    if ($search !== "") {
        $where = "WHERE test_name LIKE ? OR test_code LIKE ? OR category LIKE ?";
        $params = array_fill(0, 3, "%" . $search . "%");
    }

    $stmt = $conn->prepare("SELECT COUNT(*) FROM tests $where");
    $stmt->execute($params);
    $totalTests = (int)$stmt->fetchColumn();

    $stmt = $conn->prepare("SELECT * FROM tests $where ORDER BY id DESC LIMIT 100");
    $stmt->execute($params);
    $tests = $stmt->fetchAll();

} catch (Throwable $e) {
    $error = $e->getMessage();
}

page_start("Tests", "tests", "Tests Management", "Manage laboratory tests and prices");
?>

    <div class="page-heading">
        <div>
            <h5>🧪 Laboratory Tests</h5>
            <p>Add and manage all available laboratory tests.</p>
        </div>
    </div>

    <?php alerts($message, $error); ?>

    <div class="grid cols-3">
        <div class="stat-card">
            <div class="stat-top">
                <h6>Total Laboratory Tests</h6>
                <div class="stat-icon ic-blue">🧪</div>
            </div>
            <div class="stat-value"><?= number_format($totalTests) ?></div>
        </div>
    </div>

    <div class="card">
        <h6 class="card-title">➕ Add New Laboratory Test</h6>

        <form method="POST" id="testForm">
            <?= csrf_field() ?>

            <div class="form-grid">

                <div class="field">
                    <label>Test Name <span class="required">*</span></label>
                    <input type="text" name="test_name" placeholder="e.g. Complete Blood Count (CBC)"
                           maxlength="150" required>
                </div>

                <div class="field">
                    <label>Test Code</label>
                    <input type="text" name="test_code" placeholder="e.g. CBC001" maxlength="50">
                </div>

                <div class="field">
                    <label>Test Category</label>
                    <select name="category">
                        <option value="">Select Category</option>
                        <?php foreach (["Blood Tests","Urine Tests","Biochemistry","Hematology","Microbiology","Serology","Radiology","Other"] as $c): ?>
                            <option value="<?= h($c) ?>"><?= h($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label>Test Price (Rs.) <span class="required">*</span></label>
                    <input type="number" name="price" placeholder="Enter test price"
                           min="0" step="0.01" required>
                </div>

                <div class="field" style="grid-column:1/-1;">
                    <label>Description</label>
                    <textarea name="description" placeholder="Enter test details or instructions"
                              maxlength="1000"></textarea>
                </div>

            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">💾 Save Test</button>
                <button type="reset" class="btn btn-secondary">↺ Reset</button>
            </div>
        </form>
    </div>

    <div class="card">

        <div class="table-heading">
            <h5>📋 All Laboratory Tests</h5>

            <form method="GET" class="search-form">
                <input type="search" name="search" placeholder="Search test..." value="<?= h($search) ?>">
                <button type="submit" class="btn btn-dark">Search</button>
            </form>
        </div>

        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>Test Code</th>
                        <th>Test Name</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($tests): ?>
                    <?php foreach ($tests as $t): ?>
                        <tr>
                            <td><span class="badge badge-blue"><?= h($t["test_code"] !== "" ? $t["test_code"] : $t["id"]) ?></span></td>
                            <td><strong><?= h($t["test_name"]) ?></strong></td>
                            <td><?= h($t["category"] !== "" ? $t["category"] : "N/A") ?></td>
                            <td class="price">Rs. <?= money($t["price"]) ?></td>
                            <td>
                                <?php if (strtolower((string)$t["status"]) === "inactive"): ?>
                                    <span class="badge badge-gray">Inactive</span>
                                <?php else: ?>
                                    <span class="badge badge-green">Active</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="empty-row">
                            <strong>No Tests Found</strong><br>
                            Add a new laboratory test using the form above.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <p class="text-muted small mt">
            Showing <?= count($tests) ?> of <?= number_format($totalTests) ?> tests (maximum 100 displayed).
        </p>
    </div>

<?php page_end(); ?>
