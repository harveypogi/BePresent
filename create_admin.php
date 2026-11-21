
<?php
require_once 'connection/connect.php';

$message = "";

$adminExists = $conn->query(
    "SELECT COUNT(*) FROM users WHERE role = 'admin'"
)->fetchColumn();

if ($_SERVER["REQUEST_METHOD"] === "POST" && $adminExists == 0) {

    $username = trim($_POST["username"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    if (strlen($password) < 8) {
        $message = "Password must be at least 8 characters.";
    } else {
        try {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare(
                "INSERT INTO users (username, email, password, role)
                 VALUES (?, ?, ?, 'admin')"
            );

            $stmt->execute([
                $username,
                $email,
                $hashedPassword
            ]);

            $message = "Administrator account created successfully!";

            $adminExists = 1;

        } catch (PDOException $e) {
            $message = "Unable to create account. Username or email may already exist.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Admin | BePresent</title>
</head>
<body>

    <h2>BePresent - Create Administrator</h2>

    <p><?= htmlspecialchars($message) ?></p>

    <?php if ($adminExists == 0): ?>

        <form method="POST">
            <input type="text" name="username"
                   placeholder="Username" required>

            <br><br>

            <input type="email" name="email"
                   placeholder="Email" required>

            <br><br>

            <input type="password" name="password"
                   placeholder="Password (minimum 8 characters)"
                   minlength="8" required>

            <br><br>

            <button type="submit">Create Admin Account</button>
        </form>

    <?php else: ?>

        <p>An administrator account already exists.</p>

    <?php endif; ?>

    <p><a href="login.php">Go to Login</a></p>

</body>
</html>
