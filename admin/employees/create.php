
<?php

session_start();

if (
    !isset($_SESSION['user_id']) ||
    $_SESSION['role'] !== 'admin'
) {
    header("Location: ../../login.php");
    exit;
}

require_once '../../connection/connect.php';

$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        die("Invalid request. Please refresh the page.");
    }

    $employeeCode = trim($_POST['employee_code']);
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $position = trim($_POST['position']);
    $hireDate = $_POST['hire_date'];
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (
        $employeeCode === '' ||
        $firstName === '' ||
        $lastName === '' ||
        $email === '' ||
        $username === '' ||
        $password === ''
    ) {
        $error = "Please complete all required fields.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";

    } elseif (strlen($password) < 8) {
        $error = "Password must contain at least 8 characters.";

    } else {

        try {

            $conn->beginTransaction();

            // Create employee login account
            $hashedPassword = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $userStmt = $conn->prepare("
                INSERT INTO users (
                    username,
                    email,
                    password,
                    role
                )
                VALUES (?, ?, ?, 'employee')
            ");

            $userStmt->execute([
                $username,
                $email,
                $hashedPassword
            ]);

            $userId = $conn->lastInsertId();

            // Create employee profile
            $employeeStmt = $conn->prepare("
                INSERT INTO employees (
                    user_id,
                    employee_code,
                    first_name,
                    last_name,
                    email,
                    phone,
                    position,
                    hire_date,
                    employment_status
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Active')
            ");

            $employeeStmt->execute([
                $userId,
                $employeeCode,
                $firstName,
                $lastName,
                $email,
                $phone ?: null,
                $position ?: null,
                $hireDate ?: null
            ]);

            $conn->commit();

            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            header("Location: index.php?success=created");
            exit;

        } catch (PDOException $e) {

            if ($conn->inTransaction()) {
                $conn->rollBack();
            }

            if ($e->getCode() === '23000') {
                $error = "Employee ID, username, or email already exists.";
            } else {
                error_log($e->getMessage());
                $error = "Unable to create employee. Please try again.";
            }

        }

    }

}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Add Employee | BePresent</title>

    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/employees.css">

    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

</head>

<body>

    <!-- SIDEBAR -->

    <aside class="sidebar">

        <div class="sidebar-brand">
            <div class="sidebar-logo">B</div>
            <h2>BePresent</h2>
        </div>

        <p class="sidebar-label">MAIN MENU</p>

        <nav class="sidebar-menu">

            <a href="../dashboard.php">
                <span class="menu-icon"><i class="bi bi-grid"></i></span>
                <span class="menu-text">Dashboard</span>
            </a>

            <a href="index.php" class="active">
                <span class="menu-icon"><i class="bi bi-people"></i></span>
                <span class="menu-text">Employees</span>
            </a>

            <a href="#">
                <span class="menu-icon"><i class="bi bi-calendar-check"></i></span>
                <span class="menu-text">Attendance</span>
            </a>

            <a href="#">
                <span class="menu-icon"><i class="bi bi-file-earmark-bar-graph"></i></span>
                <span class="menu-text">Reports</span>
            </a>

        </nav>

        <div class="sidebar-bottom">
            <nav class="sidebar-menu">
                <a href="../../logout.php">
                    <span class="menu-icon"><i class="bi bi-box-arrow-right"></i></span>
                    <span class="menu-text">Logout</span>
                </a>
            </nav>
        </div>

    </aside>


    <!-- MAIN CONTENT -->

    <main class="main-content">

        <header class="topbar">

            <div>
                <h1>Add Employee</h1>
                <p>Register a new employee and create their login account.</p>
            </div>

        </header>


        <section class="content-card">

            <div class="employee-header">
                <div>
                    <h3>Employee Information</h3>
                    <p>Fields marked with * are required.</p>
                </div>
            </div>

            <div class="employee-form-container">

                <?php if ($error !== ''): ?>

                    <div class="form-error">
                        <?= htmlspecialchars($error) ?>
                    </div>

                <?php endif; ?>


                <form method="POST">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
                    >

                    <h4 class="form-section-title">
                        Personal Information
                    </h4>

                    <div class="employee-form-grid">

                        <div class="form-field">
                            <label>First Name *</label>
                            <input
                                type="text"
                                name="first_name"
                                value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>"
                                placeholder="Enter first name"
                                required
                            >
                        </div>

                        <div class="form-field">
                            <label>Last Name *</label>
                            <input
                                type="text"
                                name="last_name"
                                value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>"
                                placeholder="Enter last name"
                                required
                            >
                        </div>

                        <div class="form-field">
                            <label>Email Address *</label>
                            <input
                                type="email"
                                name="email"
                                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                placeholder="example@email.com"
                                required
                            >
                        </div>

                        <div class="form-field">
                            <label>Phone Number</label>
                            <input
                                type="tel"
                                name="phone"
                                value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                                placeholder="Enter phone number"
                            >
                        </div>

                    </div>


                    <h4 class="form-section-title">
                        Employment Information
                    </h4>

                    <div class="employee-form-grid">

                        <div class="form-field">
                            <label>Employee ID *</label>
                            <input
                                type="text"
                                name="employee_code"
                                value="<?= htmlspecialchars($_POST['employee_code'] ?? '') ?>"
                                placeholder="Example: EMP-001"
                                required
                            >
                        </div>

                        <div class="form-field">
                            <label>Position</label>
                            <input
                                type="text"
                                name="position"
                                value="<?= htmlspecialchars($_POST['position'] ?? '') ?>"
                                placeholder="Example: IT Staff"
                            >
                        </div>

                        <div class="form-field">
                            <label>Hire Date</label>
                            <input
                                type="date"
                                name="hire_date"
                                value="<?= htmlspecialchars($_POST['hire_date'] ?? '') ?>"
                            >
                        </div>

                    </div>


                    <h4 class="form-section-title">
                        Login Account
                    </h4>

                    <div class="employee-form-grid">

                        <div class="form-field">
                            <label>Username *</label>
                            <input
                                type="text"
                                name="username"
                                value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                                placeholder="Create username"
                                required
                            >
                        </div>

                        <div class="form-field">
                            <label>Initial Password *</label>
                            <input
                                type="password"
                                name="password"
                                minlength="8"
                                placeholder="Minimum 8 characters"
                                required
                            >
                        </div>

                    </div>

                    <p class="form-help">
                        The employee will use this username and initial password to log in.
                    </p>


                    <div class="form-actions">

                        <a href="index.php" class="cancel-button">
                            Cancel
                        </a>

                        <button type="submit" class="add-button">
                            <i class="bi bi-person-plus"></i>
                            Create Employee
                        </button>

                    </div>

                </form>

            </div>

        </section>

    </main>

</body>
</html>
