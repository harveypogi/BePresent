
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

// Generate CSRF token
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));

// Search employees
$search = trim($_GET['search'] ?? '');
$searchPattern = "%{$search}%";

$sql = "
    SELECT
        employees.*,
        users.username
    FROM employees
    LEFT JOIN users
        ON employees.user_id = users.id
    WHERE
        employees.employee_code LIKE :code
        OR employees.first_name LIKE :first_name
        OR employees.last_name LIKE :last_name
        OR employees.email LIKE :email
        OR employees.position LIKE :position
    ORDER BY employees.created_at DESC
";

$stmt = $conn->prepare($sql);

$stmt->execute([
    'code' => $searchPattern,
    'first_name' => $searchPattern,
    'last_name' => $searchPattern,
    'email' => $searchPattern,
    'position' => $searchPattern
]);

$employees = $stmt->fetchAll();

// Employee statistics
$totalEmployees = $conn->query("
    SELECT COUNT(*) FROM employees
")->fetchColumn();

$activeEmployees = $conn->query("
    SELECT COUNT(*) FROM employees
    WHERE employment_status = 'Active'
")->fetchColumn();

$inactiveEmployees = $conn->query("
    SELECT COUNT(*) FROM employees
    WHERE employment_status = 'Inactive'
")->fetchColumn();

// Escape output safely
function e($value) {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Employees | BePresent</title>

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
                <span class="menu-icon">
                    <i class="bi bi-grid"></i>
                </span>
                <span class="menu-text">Dashboard</span>
            </a>

            <a href="index.php" class="active">
                <span class="menu-icon">
                    <i class="bi bi-people"></i>
                </span>
                <span class="menu-text">Employees</span>
            </a>

            <a href="#">
                <span class="menu-icon">
                    <i class="bi bi-calendar-check"></i>
                </span>
                <span class="menu-text">Attendance</span>
            </a>

            <a href="#">
                <span class="menu-icon">
                    <i class="bi bi-file-earmark-bar-graph"></i>
                </span>
                <span class="menu-text">Reports</span>
            </a>

        </nav>

        <div class="sidebar-bottom">

            <nav class="sidebar-menu">

                <a href="../../logout.php">
                    <span class="menu-icon">
                        <i class="bi bi-box-arrow-right"></i>
                    </span>
                    <span class="menu-text">Logout</span>
                </a>

            </nav>

        </div>

    </aside>


    <!-- MAIN CONTENT -->

    <main class="main-content">

        <header class="topbar">

            <div>
                <h1>Employees</h1>
                <p>Manage your company's employee information.</p>
            </div>

            <div class="admin-profile">

                <div class="profile-avatar">
                    <i class="bi bi-person-fill"></i>
                </div>

                <div class="profile-info">

                    <strong>
                        <?= e($_SESSION['username']) ?>
                    </strong>

                    <span>Administrator</span>

                </div>

            </div>

        </header>


        <!-- EMPLOYEE STATISTICS -->

        <section class="employee-stats">

            <div class="employee-stat-card">
                <span>Total Employees</span>
                <h2><?= number_format($totalEmployees) ?></h2>
            </div>

            <div class="employee-stat-card">
                <span>Active Employees</span>
                <h2><?= number_format($activeEmployees) ?></h2>
            </div>

            <div class="employee-stat-card">
                <span>Inactive Employees</span>
                <h2><?= number_format($inactiveEmployees) ?></h2>
            </div>

        </section>


        <!-- EMPLOYEE DIRECTORY -->

        <section class="content-card">

            <div class="employee-header">

                <div>
                    <h3>Employee Directory</h3>
                    <p>View and manage all registered employees.</p>
                </div>

                <a href="create.php" class="add-button">
                    <i class="bi bi-plus-lg"></i>
                    Add Employee
                </a>

            </div>


            <!-- SUCCESS NOTIFICATIONS -->

            <?php if (isset($_GET['success'])): ?>

                <?php if ($_GET['success'] === 'created'): ?>

                    <div class="success-message">
                        <i class="bi bi-check-circle"></i>
                        Employee successfully added!
                    </div>

                <?php elseif ($_GET['success'] === 'updated'): ?>

                    <div class="success-message">
                        <i class="bi bi-check-circle"></i>
                        Employee information successfully updated!
                    </div>

                <?php elseif ($_GET['success'] === 'deactivated'): ?>

                    <div class="success-message">
                        <i class="bi bi-check-circle"></i>
                        Employee successfully deactivated!
                    </div>

                <?php elseif ($_GET['success'] === 'activated'): ?>

                    <div class="success-message">
                        <i class="bi bi-check-circle"></i>
                        Employee successfully reactivated!
                    </div>

                <?php endif; ?>

            <?php endif; ?>


            <!-- SEARCH -->

            <form method="GET" class="search-form">

                <div class="search-box">

                    <i class="bi bi-search"></i>

                    <input
                        type="text"
                        name="search"
                        placeholder="Search employee..."
                        value="<?= e($search) ?>"
                    >

                </div>

                <button type="submit" class="search-button">
                    Search
                </button>

                <?php if ($search !== ''): ?>

                    <a href="index.php" class="clear-button">
                        Clear
                    </a>

                <?php endif; ?>

            </form>


            <!-- EMPLOYEE TABLE -->

            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>
                            <th>EMPLOYEE</th>
                            <th>EMPLOYEE ID</th>
                            <th>POSITION</th>
                            <th>EMAIL</th>
                            <th>STATUS</th>
                            <th>ACTIONS</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php if (count($employees) > 0): ?>

                            <?php foreach ($employees as $employee): ?>

                                <tr>

                                    <!-- EMPLOYEE NAME -->

                                    <td>

                                        <div class="employee-name">

                                            <div class="employee-avatar">
                                                <?= e(strtoupper(substr($employee['first_name'], 0, 1))) ?>
                                            </div>

                                            <div>

                                                <strong>
                                                    <?= e(
                                                        $employee['first_name'] . ' ' .
                                                        $employee['last_name']
                                                    ) ?>
                                                </strong>

                                                <span>
                                                    <?= e($employee['username'] ?? 'No account') ?>
                                                </span>

                                            </div>

                                        </div>

                                    </td>


                                    <!-- EMPLOYEE ID -->

                                    <td>
                                        <?= e($employee['employee_code']) ?>
                                    </td>


                                    <!-- POSITION -->

                                    <td>
                                        <?= e($employee['position'] ?: 'Not assigned') ?>
                                    </td>


                                    <!-- EMAIL -->

                                    <td>
                                        <?= e($employee['email']) ?>
                                    </td>


                                    <!-- STATUS -->

                                    <td>

                                        <span class="employee-status <?= $employee['employment_status'] === 'Active' ? 'active' : 'inactive' ?>">

                                            <?= e($employee['employment_status']) ?>

                                        </span>

                                    </td>


                                    <!-- ACTIONS -->

                                    <td>

                                        <div class="employee-actions">

                                            <!-- EDIT BUTTON -->

                                            <a
                                                href="edit.php?id=<?= (int)$employee['id'] ?>"
                                                class="edit-button"
                                            >
                                                <i class="bi bi-pencil-square"></i>
                                                Edit
                                            </a>


                                            <!-- ACTIVATE / DEACTIVATE BUTTON -->

                                            <form
                                                action="toggle_status.php"
                                                method="POST"
                                                onsubmit="return confirm('Are you sure you want to <?= $employee['employment_status'] === 'Active' ? 'deactivate' : 'reactivate' ?> this employee?');"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= e($_SESSION['csrf_token']) ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="employee_id"
                                                    value="<?= (int)$employee['id'] ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="new_status"
                                                    value="<?= $employee['employment_status'] === 'Active' ? 'Inactive' : 'Active' ?>"
                                                >

                                                <?php if ($employee['employment_status'] === 'Active'): ?>

                                                    <button
                                                        type="submit"
                                                        class="deactivate-button"
                                                    >
                                                        <i class="bi bi-person-x"></i>
                                                        Deactivate
                                                    </button>

                                                <?php else: ?>

                                                    <button
                                                        type="submit"
                                                        class="activate-button"
                                                    >
                                                        <i class="bi bi-person-check"></i>
                                                        Activate
                                                    </button>

                                                <?php endif; ?>

                                            </form>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="6" class="empty-state">
                                    No employees found.
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>


            <!-- TABLE FOOTER -->

            <div class="table-footer">
                Showing <?= count($employees) ?> employee(s)
            </div>

        </section>

    </main>

</body>

</html>
