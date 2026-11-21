
<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../login.php");
    exit();
}

require_once "../../connection/connect.php";

if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    header("Location: index.php");
    exit();
}

$employee_id = (int) $_GET['id'];

$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));

$error = "";

// Get employee information and linked login account
$stmt = $conn->prepare("
    SELECT employees.*, users.username
    FROM employees
    LEFT JOIN users ON employees.user_id = users.id
    WHERE employees.id = :id
");

$stmt->execute(['id' => $employee_id]);
$employee = $stmt->fetch();

if (!$employee) {
    header("Location: index.php");
    exit();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $error = "Invalid request. Please refresh the page and try again.";
    } else {

        $employee_code = trim($_POST['employee_code'] ?? '');
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $position = trim($_POST['position'] ?? '');
        $hire_date = $_POST['hire_date'] ?? '';
        $username = trim($_POST['username'] ?? '');
        $status = $_POST['employment_status'] ?? '';

        if (
            $employee_code === '' ||
            $first_name === '' ||
            $last_name === '' ||
            $email === '' ||
            $username === ''
        ) {
            $error = "Please complete all required fields.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } elseif (!in_array($status, ['Active', 'Inactive'], true)) {
            $error = "Invalid employment status.";
        } else {

            try {
                $conn->beginTransaction();

                // Update employee information
                $updateEmployee = $conn->prepare("
                    UPDATE employees
                    SET employee_code = :employee_code,
                        first_name = :first_name,
                        last_name = :last_name,
                        email = :email,
                        phone = :phone,
                        position = :position,
                        hire_date = :hire_date,
                        employment_status = :status
                    WHERE id = :id
                ");

                $updateEmployee->execute([
                    'employee_code' => $employee_code,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'email' => $email,
                    'phone' => $phone,
                    'position' => $position,
                    'hire_date' => $hire_date !== '' ? $hire_date : null,
                    'status' => $status,
                    'id' => $employee_id
                ]);

                // Update linked login account
                if (!empty($employee['user_id'])) {

                    $updateUser = $conn->prepare("
                        UPDATE users
                        SET username = :username,
                            email = :email,
                            is_active = :is_active
                        WHERE id = :user_id
                    ");

                    $updateUser->execute([
                        'username' => $username,
                        'email' => $email,
                        'is_active' => $status === 'Active' ? 1 : 0,
                        'user_id' => $employee['user_id']
                    ]);
                }

                $conn->commit();

                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                header("Location: index.php?success=updated");
                exit();

            } catch (PDOException $e) {

                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }

                error_log($e->getMessage());

                if ($e->getCode() === '23000') {
                    $error = "Employee ID, email, or username already exists.";
                } else {
                    $error = "Unable to update employee information. Please try again.";
                }
            }
        }
    }

    // Preserve submitted values if validation fails
    $employee['employee_code'] = $employee_code;
    $employee['first_name'] = $first_name;
    $employee['last_name'] = $last_name;
    $employee['email'] = $email;
    $employee['phone'] = $phone;
    $employee['position'] = $position;
    $employee['hire_date'] = $hire_date;
    $employee['username'] = $username;
    $employee['employment_status'] = $status;
}

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Employee | BePresent</title>

    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/employees.css">

    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>

<body>

<div class="dashboard-container">

    <aside class="sidebar">
        <div class="brand">
            <div class="brand-icon">B</div>
            <h2>BePresent</h2>
        </div>

        <p class="menu-label">MAIN MENU</p>

        <nav class="sidebar-menu">
            <a href="../dashboard.php">
                <i class="bi bi-grid"></i>
                <span>Dashboard</span>
            </a>

            <a href="index.php" class="active">
                <i class="bi bi-people"></i>
                <span>Employees</span>
            </a>

            <a href="#">
                <i class="bi bi-calendar-check"></i>
                <span>Attendance</span>
            </a>

            <a href="#">
                <i class="bi bi-file-earmark-bar-graph"></i>
                <span>Reports</span>
            </a>
        </nav>

        <a href="../../logout.php" class="logout-link">
            <i class="bi bi-box-arrow-right"></i>
            <span>Logout</span>
        </a>
    </aside>

    <main class="main-content">

        <header class="topbar">
            <div>
                <h2>Edit Employee</h2>
                <p>Update employee information and account details.</p>
            </div>

            <div class="admin-profile">
                <div class="profile-icon">
                    <i class="bi bi-person-fill"></i>
                </div>
                <div>
                    <strong>Admin</strong>
                    <span>Administrator</span>
                </div>
            </div>
        </header>

        <section class="content-area">

            <div class="directory-card">

                <div class="employee-header">
                    <div>
                        <h3>Edit Employee Information</h3>
                        <p>Modify the employee's registered details.</p>
                    </div>
                </div>

                <?php if ($error !== ''): ?>
                    <div class="form-error">
                        <?= e($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST">

                    <input type="hidden" name="csrf_token"
                        value="<?= e($_SESSION['csrf_token']) ?>">

                    <div class="employee-form-container">

                        <h3 class="form-section-title">
                            Personal Information
                        </h3>

                        <div class="employee-form-grid">

                            <div class="form-field">
                                <label>Employee ID *</label>
                                <input type="text" name="employee_code"
                                    value="<?= e($employee['employee_code']) ?>" required>
                            </div>

                            <div class="form-field">
                                <label>First Name *</label>
                                <input type="text" name="first_name"
                                    value="<?= e($employee['first_name']) ?>" required>
                            </div>

                            <div class="form-field">
                                <label>Last Name *</label>
                                <input type="text" name="last_name"
                                    value="<?= e($employee['last_name']) ?>" required>
                            </div>

                            <div class="form-field">
                                <label>Email Address *</label>
                                <input type="email" name="email"
                                    value="<?= e($employee['email']) ?>" required>
                            </div>

                            <div class="form-field">
                                <label>Phone Number</label>
                                <input type="text" name="phone"
                                    value="<?= e($employee['phone']) ?>">
                            </div>

                        </div>

                        <h3 class="form-section-title">
                            Employment Information
                        </h3>

                        <div class="employee-form-grid">

                            <div class="form-field">
                                <label>Position</label>
                                <input type="text" name="position"
                                    value="<?= e($employee['position']) ?>">
                            </div>

                            <div class="form-field">
                                <label>Hire Date</label>
                                <input type="date" name="hire_date"
                                    value="<?= e($employee['hire_date']) ?>">
                            </div>

                            <div class="form-field">
                                <label>Employment Status *</label>
                                <select name="employment_status" required>
                                    <option value="Active"
                                        <?= $employee['employment_status'] === 'Active' ? 'selected' : '' ?>>
                                        Active
                                    </option>

                                    <option value="Inactive"
                                        <?= $employee['employment_status'] === 'Inactive' ? 'selected' : '' ?>>
                                        Inactive
                                    </option>
                                </select>
                            </div>

                        </div>

                        <h3 class="form-section-title">
                            Login Account
                        </h3>

                        <div class="employee-form-grid">

                            <div class="form-field">
                                <label>Username *</label>
                                <input type="text" name="username"
                                    value="<?= e($employee['username']) ?>" required>
                            </div>

                            <div class="form-field">
                                <label>Email Address *</label>
                                <input type="email" value="<?= e($employee['email']) ?>"
                                    disabled>
                                <small>Email is shared with the employee profile.</small>
                            </div>

                        </div>

                        <p class="form-help">
                            The employee's current password will remain unchanged.
                        </p>

                        <div class="form-actions">

                            <a href="index.php" class="cancel-button">
                                Cancel
                            </a>

                            <button type="submit" class="add-button">
                                <i class="bi bi-check-circle"></i>
                                Save Changes
                            </button>

                        </div>

                    </div>

                </form>

            </div>

        </section>

    </main>

</div>

</body>
</html>
