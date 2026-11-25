<?php
require_once __DIR__ . "/_init.php";

$search = trim($_GET["search"] ?? "");
$patients = [];
$totalPatients = 0;
$error = "";

try {
    $where = "";
    $params = [];

    if ($search !== "") {
        $where = "WHERE patient_code LIKE ? OR name LIKE ? OR phone LIKE ? OR email LIKE ?";
        $params = array_fill(0, 4, "%" . $search . "%");
    }

    $stmt = $conn->prepare("SELECT COUNT(*) FROM patients $where");
    $stmt->execute($params);
    $totalPatients = (int)$stmt->fetchColumn();

    $stmt = $conn->prepare("SELECT * FROM patients $where ORDER BY id DESC LIMIT 100");
    $stmt->execute($params);
    $patients = $stmt->fetchAll();

} catch (Throwable $e) {
    $error = $e->getMessage();
}

page_start("Patients", "patients", "Patients Management", "Manage all laboratory patients");
?>

    <div class="page-heading">
        <div>
            <h5>👥 All Patients</h5>
            <p>View and manage registered patient records.</p>
        </div>
        <a href="add-patient.php" class="btn btn-primary">+ Add New Patient</a>
    </div>

    <div class="grid cols-3">
        <div class="stat-card">
            <div class="stat-top">
                <h6>Total Registered Patients</h6>
                <div class="stat-icon ic-blue">👥</div>
            </div>
            <div class="stat-value"><?= number_format($totalPatients) ?></div>
        </div>
    </div>

    <div class="card">

        <div class="table-heading">
            <h5>📋 Patient Records</h5>

            <form method="GET" class="search-form">
                <input type="search" name="search" placeholder="Search patient..."
                       value="<?= h($search) ?>">
                <button type="submit" class="btn btn-dark">Search</button>
            </form>
        </div>

        <?php if ($error !== ""): ?>
            <div class="alert alert-danger">Database Error: <?= h($error) ?></div>
        <?php else: ?>

        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>Patient Code</th>
                        <th>Patient Name</th>
                        <th>Phone</th>
                        <th>Gender</th>
                        <th>Registration Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($patients): ?>
                    <?php foreach ($patients as $p): ?>
                        <tr>
                            <td><span class="badge badge-blue"><?= h($p["patient_code"]) ?></span></td>
                            <td><strong><?= h($p["name"]) ?></strong></td>
                            <td><?= h($p["phone"]) ?></td>
                            <td>
                                <?php if ($p["gender"] !== "" && $p["gender"] !== null): ?>
                                    <span class="badge badge-green"><?= h($p["gender"]) ?></span>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td><?= h(date("d M Y", strtotime($p["registration_date"] ?: $p["created_at"]))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="empty-row">
                            <strong>No Patients Found</strong><br>
                            <?= $search !== "" ? "Try another search." : "No patient records have been registered yet." ?>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php endif; ?>

        <p class="text-muted small mt">
            Showing <?= count($patients) ?> of <?= number_format($totalPatients) ?> patients
            (maximum 100 displayed).
        </p>
    </div>

<?php page_end(); ?>
