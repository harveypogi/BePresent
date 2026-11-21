
<?php

session_start();

date_default_timezone_set('Asia/Manila');

// Admin-only access
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'admin'
) {
    header("Location: ../../login.php");
    exit;
}

require_once '../../connection/connect.php';

// Get and validate date
$selectedDate = $_GET['date'] ?? date('Y-m-d');

if (!is_string($selectedDate)) {
    $selectedDate = date('Y-m-d');
}

$dateObject = DateTime::createFromFormat('!Y-m-d', $selectedDate);

if (
    !$dateObject ||
    $dateObject->format('Y-m-d') !== $selectedDate
) {
    $selectedDate = date('Y-m-d');
}

// Get search input safely
$search = $_GET['search'] ?? '';

if (!is_string($search)) {
    $search = '';
}

$search = trim($search);

// Limit search length
$search = substr($search, 0, 100);

// Validate attendance status
$selectedStatus = $_GET['status'] ?? '';

$allowedStatuses = ['Present', 'Late', 'Half Day'];

if (
    !is_string($selectedStatus) ||
    !in_array($selectedStatus, $allowedStatuses, true)
) {
    $selectedStatus = '';
}

// Build attendance query
$sql = "
    SELECT
        attendance.id,
        attendance.attendance_date,
        attendance.time_in,
        attendance.time_out,
        attendance.status,
        attendance.remarks,
        employees.employee_code,
        employees.first_name,
        employees.last_name,
        employees.position
    FROM attendance
    INNER JOIN employees
        ON attendance.employee_id = employees.id
    WHERE attendance.attendance_date = :attendance_date
";

$params = [
    'attendance_date' => $selectedDate
];

// Search employee information
if ($search !== '') {

    $sql .= "
        AND (
            employees.employee_code LIKE :search_code
            OR employees.first_name LIKE :search_first
            OR employees.last_name LIKE :search_last
            OR employees.position LIKE :search_position
        )
    ";

    $searchValue = "%{$search}%";

    $params['search_code'] = $searchValue;
    $params['search_first'] = $searchValue;
    $params['search_last'] = $searchValue;
    $params['search_position'] = $searchValue;
}

// Filter attendance status
if ($selectedStatus !== '') {

    $sql .= " AND attendance.status = :status";

    $params['status'] = $selectedStatus;
}

$sql .= "
    ORDER BY employees.first_name ASC,
             employees.last_name ASC
";

$stmt = $conn->prepare($sql);
$stmt->execute($params);

$attendanceRecords = $stmt->fetchAll();

// Get attendance statistics for selected date
$statsStmt = $conn->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) AS present,
        SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) AS late,
        SUM(CASE WHEN status = 'Half Day' THEN 1 ELSE 0 END) AS half_day
    FROM attendance
    WHERE attendance_date = :attendance_date
");

$statsStmt->execute([
    'attendance_date' => $selectedDate
]);

$stats = $statsStmt->fetch();

$totalRecords = (int) $stats['total'];
$presentCount = (int) $stats['present'];
$lateCount = (int) $stats['late'];
$halfDayCount = (int) $stats['half_day'];

?>


<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Attendance | BePresent</title>

    <link rel="stylesheet" href="../../assets/css/dashboard.css">

    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>

        .attendance-filters {
            display: flex;
            align-items: end;
            flex-wrap: wrap;
            gap: 15px;
            padding: 20px 24px;
            border-bottom: 1px solid #eeeeee;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .filter-group label {
            font-size: 12px;
            font-weight: 600;
            color: #555;
        }

        .filter-group input,
        .filter-group select {
            min-width: 170px;
            padding: 10px 12px;
            border: 1px solid #dddddd;
            border-radius: 7px;
            font-size: 13px;
            background: white;
        }

        .filter-button,
        .reset-button {
            padding: 11px 16px;
            border-radius: 7px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
        }

        .filter-button {
            border: none;
            background: #243b64;
            color: white;
        }

        .reset-button {
            background: #eeeeee;
            color: #444;
        }

        .attendance-summary {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 25px;
        }

        .attendance-summary-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.04);
        }

        .attendance-summary-card p {
            color: #777;
            font-size: 12px;
            margin: 0 0 10px;
        }

        .attendance-summary-card h2 {
            font-size: 25px;
            margin: 0;
            color: #243b64;
        }

        .attendance-date-label {
            color: #777;
            font-size: 12px;
            margin-top: 5px;
        }

        .attendance-table {
            width: 100%;
            border-collapse: collapse;
        }

        .attendance-table th,
        .attendance-table td {
            padding: 15px 18px;
            text-align: left;
            border-bottom: 1px solid #eeeeee;
            font-size: 12px;
        }

        .attendance-table th {
            color: #777;
            font-weight: 600;
            background: #fafafa;
        }

        .attendance-table td {
            color: #444;
        }

        .attendance-table tr:last-child td {
            border-bottom: none;
        }

        .attendance-table .employee-name {
            font-weight: 600;
            color: #243b64;
        }

        .attendance-table .employee-code {
            color: #999;
            font-size: 11px;
            margin-top: 4px;
        }

        .attendance-empty {
            text-align: center !important;
            padding: 35px !important;
            color: #888 !important;
        }

        .edit-attendance-button {
            display: inline-block;
            padding: 7px 12px;
            background: #243b64;
            color: white;
            border-radius: 6px;
            text-decoration: none;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
        }

        .edit-attendance-button:hover {
            background: #192c4d;
            color: white;
        }

        @media (max-width: 900px) {
            .attendance-summary {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .table-wrapper {
                overflow-x: auto;
            }

            .attendance-filters {
                align-items: stretch;
            }
        }

        @media (max-width: 500px) {
            .attendance-summary {
                grid-template-columns: 1fr;
            }

            .filter-group,
            .filter-group input,
            .filter-group select {
                width: 100%;
            }
        }

    </style>

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

            <a href="../employees/index.php">
                <span class="menu-icon">
                    <i class="bi bi-people"></i>
                </span>
                <span class="menu-text">Employees</span>
            </a>

            <a href="index.php" class="active">
                <span class="menu-icon">
                    <i class="bi bi-calendar-check"></i>
                </span>
                <span class="menu-text">Attendance</span>
            </a>

            <a href="../reports/index.php">
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

        <!-- TOPBAR -->

        <header class="topbar">

            <div>
                <h1>Attendance</h1>

                <p>
                    Monitor employee attendance and daily time records.
                </p>
            </div>

            <div class="admin-profile">

                <div class="profile-avatar">
                    <i class="bi bi-person-fill"></i>
                </div>

                <div class="profile-info">

                    <strong>
                        <?= htmlspecialchars($_SESSION['username'] ?? 'Administrator') ?>
                    </strong>

                    <span>Administrator</span>

                </div>

            </div>

        </header>


        <!-- ATTENDANCE SUMMARY -->

        <h2 class="section-heading">Attendance Overview</h2>

        <section class="attendance-summary">

            <div class="attendance-summary-card">
                <p>Total Records</p>
                <h2><?= number_format($totalRecords) ?></h2>
            </div>

            <div class="attendance-summary-card">
                <p>Present</p>
                <h2><?= number_format($presentCount) ?></h2>
            </div>

            <div class="attendance-summary-card">
                <p>Late</p>
                <h2><?= number_format($lateCount) ?></h2>
            </div>

            <div class="attendance-summary-card">
                <p>Half Day</p>
                <h2><?= number_format($halfDayCount) ?></h2>
            </div>

        </section>


        <!-- ATTENDANCE TABLE -->

        <section class="content-card">

            <div class="card-header">

                <div>
                    <h3>Attendance Records</h3>

                    <p class="attendance-date-label">
                        <?= date('F d, Y', strtotime($selectedDate)) ?>
                    </p>
                </div>

                <span>
                    <?= count($attendanceRecords) ?> records found
                </span>

            </div>

           <div style="display: flex; gap: 10px; flex-wrap: wrap; padding: 15px 24px 0;">

    <a href="create.php" class="edit-attendance-button">
        <i class="bi bi-plus-circle"></i> Add Manual Attendance
    </a>

    <a href="history.php" class="edit-attendance-button">
        <i class="bi bi-clock-history"></i> Correction History
    </a>

</div>


            <!-- FILTERS -->

            <form method="GET" class="attendance-filters">

                <div class="filter-group">

                    <label for="date">Attendance Date</label>

                    <input
                        type="date"
                        id="date"
                        name="date"
                        value="<?= htmlspecialchars($selectedDate) ?>"
                    >

                </div>


                <div class="filter-group">

                    <label for="search">Search Employee</label>

                    <input
                        type="text"
                        id="search"
                        name="search"
                        placeholder="Name or employee ID"
                        value="<?= htmlspecialchars($search) ?>"
                    >

                </div>


                <div class="filter-group">

                    <label for="status">Attendance Status</label>

                    <select name="status" id="status">

                        <option value="">All Statuses</option>

                        <option value="Present"
                            <?= $selectedStatus === 'Present' ? 'selected' : '' ?>>
                            Present
                        </option>

                        <option value="Late"
                            <?= $selectedStatus === 'Late' ? 'selected' : '' ?>>
                            Late
                        </option>

                        <option value="Half Day"
                            <?= $selectedStatus === 'Half Day' ? 'selected' : '' ?>>
                            Half Day
                        </option>

                    </select>

                </div>


                <button type="submit" class="filter-button">
                    <i class="bi bi-funnel"></i> Filter
                </button>

                <a href="index.php" class="reset-button">
                    Reset
                </a>

            </form>


            <!-- TABLE -->

            <div class="table-wrapper">

                <table class="attendance-table">

                    <thead>

                        <tr>
                            <th>EMPLOYEE</th>
                            <th>POSITION</th>
                            <th>TIME IN</th>
                            <th>TIME OUT</th>
                            <th>STATUS</th>
                            <th>REMARKS</th>
                            <th>ACTIONS</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php if (count($attendanceRecords) > 0): ?>

                            <?php foreach ($attendanceRecords as $record): ?>

                                <?php

                                $statusClass = 'status-present';

                                if ($record['status'] === 'Late') {
                                    $statusClass = 'status-late';
                                } elseif ($record['status'] === 'Half Day') {
                                    $statusClass = 'status-half';
                                }

                                ?>

                                <tr>

                                    <td>
                                        <div class="employee-name">
                                            <?= htmlspecialchars(
                                                $record['first_name'] . ' ' .
                                                $record['last_name']
                                            ) ?>
                                        </div>

                                        <div class="employee-code">
                                            <?= htmlspecialchars($record['employee_code']) ?>
                                        </div>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($record['position'] ?? 'N/A') ?>
                                    </td>

                                    <td>
                                        <?= $record['time_in']
                                            ? date('h:i A', strtotime($record['time_in']))
                                            : '--' ?>
                                    </td>

                                    <td>
                                        <?= $record['time_out']
                                            ? date('h:i A', strtotime($record['time_out']))
                                            : '--' ?>
                                    </td>

                                    <td>
                                        <span class="status <?= $statusClass ?>">
                                            <?= htmlspecialchars($record['status']) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($record['remarks'] ?? '--') ?>
                                    </td>

                                    <td>
                                        <a
                                            href="edit.php?id=<?= (int) $record['id'] ?>"
                                            class="edit-attendance-button"
                                        >
                                            <i class="bi bi-pencil-square"></i> Edit
                                        </a>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="7" class="attendance-empty">
                                    <i class="bi bi-calendar-x"></i>
                                    <p>No attendance records found for this date.</p>
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

    </main>

</body>
</html>