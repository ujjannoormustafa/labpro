<?php
require_once __DIR__ . "/_init.php";

$message = "";
$error = "";

$patient = [
    "name" => "", "father_name" => "", "gender" => "",
    "age" => "", "phone" => "", "email" => "", "address" => ""
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    foreach ($patient as $key => $value) {
        $patient[$key] = trim($_POST[$key] ?? "");
    }

    try {
        csrf_verify();

        if ($patient["name"] === "")  throw new Exception("Patient name is required.");
        if ($patient["phone"] === "") throw new Exception("Phone number is required.");

        if ($patient["email"] !== "" && !filter_var($patient["email"], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Please enter a valid email address.");
        }

        if ($patient["age"] !== "" && (!ctype_digit($patient["age"]) || (int)$patient["age"] > 150)) {
            throw new Exception("Please enter a valid age.");
        }

        if ($patient["gender"] !== "" && !in_array($patient["gender"], ["Male", "Female", "Other"], true)) {
            throw new Exception("Please select a valid gender.");
        }

        $code = "PT-" . date("Ymd") . "-" . strtoupper(bin2hex(random_bytes(3)));

        $stmt = $conn->prepare("
            INSERT INTO patients
            (patient_code, name, father_name, gender, age, phone, email, address, registration_date, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, date('now','localtime'), 'active')
        ");

        $stmt->execute([
            $code,
            $patient["name"],
            $patient["father_name"],
            $patient["gender"],
            $patient["age"] === "" ? null : (int)$patient["age"],
            $patient["phone"],
            $patient["email"],
            $patient["address"]
        ]);

        $message = "Patient registered successfully! Patient Code: " . $code;

        foreach ($patient as $key => $value) {
            $patient[$key] = "";
        }

        csrf_rotate();

    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

page_start("Add Patient", "add-patient", "Add New Patient", "Register a new laboratory patient");
?>

    <div class="page-heading">
        <div>
            <h5>➕ Patient Registration Form</h5>
            <p>Enter the patient's information to create a new record.</p>
        </div>
    </div>

    <?php if ($message !== ""): ?>
        <div class="alert alert-success">
            ✅ <?= h($message) ?>
            <a href="patients.php">View Patients</a>
        </div>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
        <div class="alert alert-danger">⚠️ <?= h($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="POST" id="patientForm">

            <?= csrf_field() ?>

            <h6 class="card-title">👤 Personal Information</h6>

            <div class="form-grid">

                <div class="field">
                    <label>Patient Name <span class="required">*</span></label>
                    <input type="text" name="name" placeholder="Enter patient full name"
                           value="<?= h($patient["name"]) ?>" maxlength="100" required>
                </div>

                <div class="field">
                    <label>Father Name</label>
                    <input type="text" name="father_name" placeholder="Enter father name"
                           value="<?= h($patient["father_name"]) ?>" maxlength="100">
                </div>

                <div class="field">
                    <label>Gender</label>
                    <select name="gender">
                        <option value="">Select Gender</option>
                        <?php foreach (["Male", "Female", "Other"] as $g): ?>
                            <option value="<?= $g ?>" <?= $patient["gender"] === $g ? "selected" : "" ?>><?= $g ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label>Age</label>
                    <input type="number" name="age" placeholder="Enter age" min="0" max="150"
                           value="<?= h($patient["age"]) ?>">
                </div>

            </div>

            <h6 class="card-title mt" style="margin-top:30px;">📞 Contact Information</h6>

            <div class="form-grid">

                <div class="field">
                    <label>Phone Number <span class="required">*</span></label>
                    <input type="tel" name="phone" placeholder="03XXXXXXXXX"
                           value="<?= h($patient["phone"]) ?>" maxlength="20" required>
                </div>

                <div class="field">
                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="example@gmail.com"
                           value="<?= h($patient["email"]) ?>" maxlength="150">
                </div>

                <div class="field span-all" style="grid-column:1/-1;">
                    <label>Patient Address</label>
                    <textarea name="address" placeholder="Enter complete patient address"
                              maxlength="500"><?= h($patient["address"]) ?></textarea>
                </div>

            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">💾 Save Patient</button>
                <button type="reset" class="btn btn-secondary">↺ Reset Form</button>
                <a href="patients.php" class="btn btn-outline">← Back</a>
            </div>

        </form>
    </div>

<?php page_end(); ?>
