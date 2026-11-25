<?php
// Shared setup for every inner page: session, database, login check, helpers, layout.

session_start();
require_once __DIR__ . "/../db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../index.php");
    exit;
}

$adminName = $_SESSION["name"] ?? "LabPro Admin";

// ---------- Helpers ----------

function h($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

function money($value) {
    return number_format((float)$value, 2);
}

function valid_date($date) {
    $d = DateTime::createFromFormat("Y-m-d", (string)$date);
    return $d && $d->format("Y-m-d") === $date;
}

// ---------- CSRF ----------

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . h($_SESSION["csrf_token"]) . '">';
}

function csrf_verify() {
    if (!hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"] ?? "")) {
        throw new Exception("Invalid request. Please refresh the page.");
    }
}

function csrf_rotate() {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// ---------- Layout ----------

function page_start($title, $active, $heading, $subheading) {
    global $adminName;
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LabPro | <?= h($title) ?></title>
<link rel="stylesheet" href="../assets/app.css">
</head>
<body>

<?php include __DIR__ . "/_sidebar.php"; ?>

<main class="main-content">

    <div class="topbar">
        <div>
            <h4><?= h($heading) ?></h4>
            <small class="text-muted"><?= h($subheading) ?></small>
        </div>
        <div class="user-info">
            <span class="avatar">👤</span>
            <div>
                <strong><?= h($adminName) ?></strong><br>
                <small class="text-muted">Administrator</small>
            </div>
        </div>
    </div>
<?php
}

function page_end() {
    ?>
    <div class="footer">
        © <?= date("Y") ?> LabPro - Laboratory Management System. All Rights Reserved.
    </div>

</main>
</body>
</html>
<?php
}

function alerts($success = "", $error = "") {
    if ($success !== "") echo '<div class="alert alert-success">' . h($success) . '</div>';
    if ($error !== "")   echo '<div class="alert alert-danger">' . h($error) . '</div>';
}
