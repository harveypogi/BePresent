
<?php
session_start();

require_once 'connection/connect.php';

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: employee/dashboard.php");
    }
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"]);
    $password = $_POST["password"];

    $stmt = $conn->prepare(
        "SELECT id, username, password, role, is_active
         FROM users
         WHERE username = ?
         LIMIT 1"
    );

    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {

        if (!$user['is_active']) {
            $error = "Your account is inactive.";
        } else {

            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            if ($user['role'] === 'admin') {
                header("Location: admin/dashboard.php");
            } else {
                header("Location: employee/dashboard.php");
            }

            exit;
        }

    } else {
        $error = "Invalid username or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login | BePresent</title>

    <link rel="stylesheet" href="assets/css/login.css">
</head>

<body>

    <div class="login-container">

        <div class="login-left">

            <div class="brand">
                <div class="brand-icon">B</div>
                <h2>BePresent</h2>
            </div>

            <div class="welcome-content">
                <span class="welcome-tag">ATTENDANCE MANAGEMENT</span>

                <h1>Every day<br>counts.</h1>

                <p>
                    Manage your workday with ease.
                    Track attendance, monitor schedules,
                    and stay connected with your workplace.
                </p>
            </div>

            <div class="login-footer">
                <p>BePresent Attendance Monitoring System</p>
            </div>

        </div>

        <div class="login-right">

            <div class="login-form-container">

                <div class="mobile-brand">
                    <div class="brand-icon">B</div>
                    <h2>BePresent</h2>
                </div>

                <span class="form-subtitle">WELCOME BACK</span>

                <h1>Sign in to your account</h1>

                <p class="form-description">
                    Enter your credentials to access your account.
                </p>

                <?php if (!empty($error)): ?>
                    <div class="error-message">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST">

                    <div class="form-group">
                        <label for="username">Username</label>

                        <input
                            type="text"
                            id="username"
                            name="username"
                            placeholder="Enter your username"
                            autocomplete="username"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>

                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                        >
                    </div>

                    <button type="submit" class="login-button">
                        Sign In
                    </button>

                </form>

                <p class="security-note">
                    Authorized personnel only.
                    Please use your registered account.
                </p>

            </div>

        </div>

    </div>

</body>
</html>
