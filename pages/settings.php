<?php
require_once __DIR__ . "/_init.php";

// ---------- Database backup (download) ----------
if (isset($_GET["backup"])) {

    $fileName = "labpro-backup-" . date("Ymd-His") . ".db";
    $tmpFile  = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $fileName;

    // VACUUM INTO makes a clean, complete copy even while the app is running
    $conn->exec("VACUUM INTO " . $conn->quote($tmpFile));

    header("Content-Type: application/octet-stream");
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header("Content-Length: " . filesize($tmpFile));
    readfile($tmpFile);
    unlink($tmpFile);
    exit;
}

$error = "";
$success = "";

$defaults = [
    "lab_name"   => "LabPro Laboratory",
    "owner_name" => "",
    "phone"      => "",
    "email"      => "",
    "address"    => "",
    "currency"   => "PKR",
    "website"    => ""
];

// ---------- Save settings ----------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["save_settings"])) {

    try {
        csrf_verify();

        $values = [
            "lab_name"   => trim($_POST["lab_name"] ?? ""),
            "owner_name" => trim($_POST["owner_name"] ?? ""),
            "phone"      => trim($_POST["phone"] ?? ""),
            "email"      => trim($_POST["email"] ?? ""),
            "address"    => trim($_POST["address"] ?? ""),
            "currency"   => $_POST["currency"] ?? "PKR",
            "website"    => trim($_POST["website"] ?? "")
        ];

        if ($values["lab_name"] === "") {
            throw new Exception("Laboratory name is required.");
        }

        if ($values["email"] !== "" && !filter_var($values["email"], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email address.");
        }

        if (!in_array($values["currency"], ["PKR", "USD", "AED", "SAR"], true)) {
            throw new Exception("Invalid currency.");
        }

        if ($values["website"] !== "" && !filter_var($values["website"], FILTER_VALIDATE_URL)) {
            throw new Exception("Enter a valid website URL.");
        }

        $conn->beginTransaction();

        try {
            $stmt = $conn->prepare("
                INSERT INTO labpro_app_settings (setting_key, setting_value)
                VALUES (?, ?)
                ON CONFLICT(setting_key) DO UPDATE SET
                    setting_value = excluded.setting_value,
                    updated_at = datetime('now','localtime')
            ");

            foreach ($values as $key => $value) {
                $stmt->execute([$key, $value]);
            }

            $conn->commit();

        } catch (Throwable $e) {
            $conn->rollBack();
            throw $e;
        }

        $success = "Settings saved successfully!";
        csrf_rotate();

    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

// ---------- Load settings ----------
$settings = $defaults;

foreach ($conn->query("SELECT setting_key, setting_value FROM labpro_app_settings") as $row) {
    if (array_key_exists($row["setting_key"], $settings)) {
        $settings[$row["setting_key"]] = $row["setting_value"];
    }
}

page_start("Settings", "settings", "Laboratory Settings", "Manage your laboratory information and preferences");
alerts($success, $error);
?>

    <div class="card">
        <h4 class="card-title">General Information</h4>

        <form method="POST">
            <?= csrf_field() ?>

            <div class="form-grid">

                <div class="field">
                    <label>Laboratory Name *</label>
                    <input type="text" name="lab_name" value="<?= h($settings["lab_name"]) ?>" required>
                </div>

                <div class="field">
                    <label>Owner / Admin Name</label>
                    <input type="text" name="owner_name" value="<?= h($settings["owner_name"]) ?>">
                </div>

                <div class="field">
                    <label>Contact Number</label>
                    <input type="tel" name="phone" value="<?= h($settings["phone"]) ?>" placeholder="03XXXXXXXXX">
                </div>

                <div class="field">
                    <label>Email Address</label>
                    <input type="email" name="email" value="<?= h($settings["email"]) ?>" placeholder="laboratory@example.com">
                </div>

                <div class="field" style="grid-column:1/-1;">
                    <label>Laboratory Address</label>
                    <textarea name="address" rows="3" placeholder="Enter laboratory address"><?= h($settings["address"]) ?></textarea>
                </div>

                <div class="field">
                    <label>Currency</label>
                    <select name="currency">
                        <?php
                        $currencies = ["PKR" => "PKR - Pakistani Rupee", "USD" => "USD - US Dollar",
                                       "AED" => "AED - UAE Dirham", "SAR" => "SAR - Saudi Riyal"];
                        foreach ($currencies as $code => $label): ?>
                            <option value="<?= $code ?>" <?= $settings["currency"] === $code ? "selected" : "" ?>><?= h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label>Website</label>
                    <input type="url" name="website" value="<?= h($settings["website"]) ?>" placeholder="https://example.com">
                </div>

            </div>

            <div class="form-actions">
                <button type="submit" name="save_settings" class="btn btn-primary">Save Settings</button>
                <button type="reset" class="btn btn-outline">Reset Form</button>
            </div>
        </form>
    </div>

    <div class="card">
        <h4 class="card-title">Backup</h4>
        <p class="text-muted mb">
            Download a complete copy of all laboratory data. Save it on a USB drive or another computer
            regularly, so your records are safe if this computer fails.
        </p>
        <a href="settings.php?backup=1" class="btn btn-primary">⬇ Download Database Backup</a>
    </div>

    <div class="card">
        <h4 class="card-title">System Information</h4>

        <div class="grid cols-2">
            <div class="info-box"><strong>Application</strong><p class="text-muted">LabPro Laboratory Management System</p></div>
            <div class="info-box"><strong>Database</strong><p class="text-muted">SQLite (stored on this computer)</p></div>
            <div class="info-box"><strong>PHP Version</strong><p class="text-muted"><?= h(PHP_VERSION) ?></p></div>
            <div class="info-box"><strong>Current Date</strong><p class="text-muted"><?= date("d M Y") ?></p></div>
        </div>
    </div>

<?php page_end(); ?>
