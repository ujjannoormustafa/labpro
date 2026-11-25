<?php
session_start();

// Database connection
require_once __DIR__ . "/db.php";

$error = "";

// If already logged in
if (isset($_SESSION["user_id"])) {
    header("Location: pages/dashboard.php");
    exit;
}

// Login form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if (empty($email) || empty($password)) {

        $error = "Please enter your email and password.";

    } else {

        try {

            $stmt = $conn->prepare(
                "SELECT id, name, email, password, role, status
                 FROM users
                 WHERE email = ?
                 LIMIT 1"
            );

            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {

                if (
                    password_verify($password, $user["password"]) &&
                    $user["status"] === "active"
                ) {

                    // Secure session
                    session_regenerate_id(true);

                    $_SESSION["user_id"] = $user["id"];
                    $_SESSION["user_name"] = $user["name"];
                    $_SESSION["user_email"] = $user["email"];
                    $_SESSION["user_role"] = $user["role"];

                    // Dashboard compatibility
                    $_SESSION["name"] = $user["name"];
                    $_SESSION["email"] = $user["email"];

                    header("Location: pages/dashboard.php");
                    exit;

                } else {

                    $error = "Invalid email or password, or account is inactive.";

                }

            } else {

                $error = "Invalid email or password.";

            }

        } catch (PDOException $e) {

            error_log($e->getMessage());
            $error = "Login error. Please check database configuration.";

        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>LabPro | Laboratory Management System</title>

    <!-- Custom CSS -->
    <link rel="stylesheet" href="style.css">

</head>

<body>

<div class="login-wrapper">

    <div class="login-container">

        <!-- LEFT SIDE -->

        <div class="login-info">

            <div class="brand">

                <div class="brand-icon">✚</div>

                <div>
                    <h2>LabPro</h2>
                    <span>Laboratory Management System</span>
                </div>

            </div>

            <div class="welcome-content">

                <span class="tagline">SMART LABORATORY SOLUTIONS</span>

                <h1>
                    Manage Your Laboratory
                    <span>Smarter & Faster.</span>
                </h1>

                <p>
                    A complete laboratory management and accounting
                    solution designed to manage patients, tests,
                    payments, commissions and financial reports.
                </p>

                <div class="features">

                    <div class="feature">
                        <span>✓</span>
                        Patient Management
                    </div>

                    <div class="feature">
                        <span>✓</span>
                        Laboratory Test Records
                    </div>

                    <div class="feature">
                        <span>✓</span>
                        Automatic Commission Calculation
                    </div>

                    <div class="feature">
                        <span>✓</span>
                        Financial Reports & Accounting
                    </div>

                </div>

            </div>

            <div class="left-footer">
                © 2026 LabPro. All Rights Reserved.
            </div>

        </div>


        <!-- RIGHT SIDE -->

        <div class="login-form-section">

            <div class="form-content">

                <div class="mobile-logo">

                    <div class="brand-icon">✚</div>
                    <h2>LabPro</h2>

                </div>

                <div class="form-heading">

                    <span class="welcome-label">WELCOME BACK</span>

                    <h2>Administrator Login</h2>

                    <p>
                        Enter your credentials to access your laboratory dashboard.
                    </p>

                </div>


                <!-- LOGIN FORM -->

                <form method="POST" action="index.php">

                    <!-- Error Message -->

                    <?php if (!empty($error)): ?>

                        <div class="alert alert-danger">
                            <?php echo htmlspecialchars($error); ?>
                        </div>

                    <?php endif; ?>


                    <!-- Email -->

                    <div class="form-group">

                        <label for="email">Email Address</label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="form-control"
                            placeholder="Enter your email"
                            autocomplete="username"
                            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                            required
                        >

                    </div>


                    <!-- Password -->

                    <div class="form-group">

                        <label for="password">Password</label>

                        <div class="password-box">

                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="form-control"
                                placeholder="Enter your password"
                                autocomplete="current-password"
                                required
                            >

                            <button
                                type="button"
                                id="togglePassword"
                                class="password-toggle"
                            >
                                Show
                            </button>

                        </div>

                    </div>


                    <!-- Remember Username -->

                    <div class="remember-row">

                        <label class="remember-label">

                            <input type="checkbox" id="remember">

                            Remember username

                        </label>

                        <span class="secure-text">
                            🔒 Secure Access
                        </span>

                    </div>


                    <!-- Login Button -->

                    <button type="submit" class="login-btn">

                        Sign In

                    </button>

                </form>


                <!-- Security Note -->

                <div class="security-note">

                    <span>🛡️</span>

                    <p>
                        Your laboratory data is private and protected.
                        Only authorized users can access this system.
                    </p>

                </div>


                <div class="form-footer">

                    <span>LabPro v1.0</span>

                    <span>Powered by LabPro</span>

                </div>

            </div>

        </div>

    </div>

</div>


<!-- Show / Hide Password -->

<script>

const togglePassword = document.getElementById("togglePassword");
const passwordInput = document.getElementById("password");

togglePassword.addEventListener("click", function () {

    if (passwordInput.type === "password") {

        passwordInput.type = "text";
        togglePassword.textContent = "Hide";

    } else {

        passwordInput.type = "password";
        togglePassword.textContent = "Show";

    }

});

</script>

</body>
</html>
