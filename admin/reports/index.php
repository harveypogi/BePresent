
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

// Default report period: first day of current month until today
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$search = trim($_GET['search'] ?? '');

function validDate($date) {
    $dateObject = DateTime::createFromFormat('Y-m-d', $date);

    return $dateObject && $dateObject->format('Y-m-d') === $date;
}

if (!validDate($startDate) || !validDate($endDate) || $startDate > $endDate) {
    $startDate = date('Y-m-01');
    $endDate = date('Y-m-d');
}

// Build report query
$sql = "
    SELECT
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
    WHERE attendance.attendance_date BETWEEN :start_date AND :end_date
";

$params = [
    'start_date' => $startDate,
    'end_date' => $endDate
];

if ($search !== '') {
    $sql .= "
        AND (
            employees.employee_code LIKE :code
            OR employees.first_name LIKE :first_name
            OR employees.last_name LIKE :last_name
            OR employees.position LIKE :position
        )
    ";

    $searchValue = "%{$search}%";

    $params['code'] = $searchValue;
    $params['first_name'] = $searchValue;
    $params['last_name'] = $searchValue;
    $params['position'] = $searchValue;
}

$sql .= "
    ORDER BY attendance.attendance_date DESC,
             employees.first_name ASC
";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

// Calculate report statistics using the same filters
$summarySql = "
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN attendance.status = 'Present' THEN 1 ELSE 0 END) AS present,
        SUM(CASE WHEN attendance.status = 'Late' THEN 1 ELSE 0 END) AS late,
        SUM(CASE WHEN attendance.status = 'Half Day' THEN 1 ELSE 0 END) AS half_day
    FROM attendance
    INNER JOIN employees
        ON attendance.employee_id = employees.id
    WHERE attendance.attendance_date BETWEEN :start_date AND :end_date
";

$summaryParams = [
    'start_date' => $startDate,
    'end_date' => $endDate
];

if ($search !== '') {
    $summarySql .= "
        AND (
            employees.employee_code LIKE :code
            OR employees.first_name LIKE :first_name
            OR employees.last_name LIKE :last_name
            OR employees.position LIKE :position
        )
    ";

    $summaryParams['code'] = $searchValue;
    $summaryParams['first_name'] = $searchValue;
    $summaryParams['last_name'] = $searchValue;
    $summaryParams['position'] = $searchValue;
}

$summaryStmt = $conn->prepare($summarySql);
$summaryStmt->execute($summaryParams);
$summary = $summaryStmt->fetch();

$totalRecords = (int) $summary['total'];
$presentCount = (int) $summary['present'];
$lateCount = (int) $summary['late'];
$halfDayCount = (int) $summary['half_day'];

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Reports | BePresent</title>

    <link rel="stylesheet" href="../../assets/css/dashboard.css">

    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        .report-summary {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 25px;
        }

        .report-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.04);
        }

        .report-card p {
            color: #777;
            font-size: 12px;
            margin: 0 0 10px;
        }

        .report-card h2 {
            color: #243b64;
            font-size: 25px;
            margin: 0;
        }

        .report-filters {
            display: flex;
            align-items: end;
            flex-wrap: wrap;
            gap: 15px;
            padding: 20px 24px;
            border-bottom: 1px solid #eee;
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

        .filter-group input {
            min-width: 170px;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 7px;
            font-size: 13px;
        }

        .filter-button,
        .reset-button {
            padding: 11px 16px;
            border-radius: 7px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
        }

        .filter-button {
            border: none;
            background: #243b64;
            color: white;
            cursor: pointer;
        }

        .reset-button {
            background: #eee;
            color: #444;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
        }

        .report-table th,
        .report-table td {
            padding: 15px 18px;
            text-align: left;
            border-bottom: 1px solid #eee;
            font-size: 12px;
        }

        .report-table th {
            background: #fafafa;
            color: #777;
            font-weight: 600;
        }

        .report-table .employee-name {
            color: #243b64;
            font-weight: 600;
        }

        .report-table .employee-code {
            color: #999;
            font-size: 11px;
            margin-top: 4px;
        }

        .report-empty {
            text-align: center !important;
            padding: 35px !important;
            color: #888;
        }

        @media (max-width: 900px) {
            .report-summary {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .table-wrapper {
                overflow-x: auto;
            }
        }

        @media (max-width: 500px) {
            .report-summary {
                grid-template-columns: 1fr;
            }

            .filter-group,
            .filter-group input {
                width: 100%;
            }
        }
        .export-button {
    padding: 11px 16px;
    border: none;
    border-radius: 7px;
    background: #198754;
    color: white;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
}

.export-button:hover {
    background: #146c43;
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

            <a href="../attendance/index.php">
                <span class="menu-icon">
                    <i class="bi bi-calendar-check"></i>
                </span>
                <span class="menu-text">Attendance</span>
            </a>

            <a href="index.php" class="active">
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
                <h1>Attendance Reports</h1>

                <p>
                    Review employee attendance within a selected period.
                </p>
            </div>

            <div class="admin-profile">

                <div class="profile-avatar">
                    <i class="bi bi-person-fill"></i>
                </div>

                <div class="profile-info">

                    <strong>
                        <?= htmlspecialchars($_SESSION['username']) ?>
                    </strong>

                    <span>Administrator</span>

                </div>

            </div>

        </header>


        <!-- REPORT SUMMARY -->

        <h2 class="section-heading">Report Overview</h2>

        <section class="report-summary">

            <div class="report-card">
                <p>Total Records</p>
                <h2><?= number_format($totalRecords) ?></h2>
            </div>

            <div class="report-card">
                <p>Present</p>
                <h2><?= number_format($presentCount) ?></h2>
            </div>

            <div class="report-card">
                <p>Late</p>
                <h2><?= number_format($lateCount) ?></h2>
            </div>

            <div class="report-card">
                <p>Half Day</p>
                <h2><?= number_format($halfDayCount) ?></h2>
            </div>

        </section>


        <!-- REPORT TABLE -->

        <section class="content-card">

            <div class="card-header">

                <h3>Attendance History</h3>

                <span>
                    <?= count($records) ?> records found
                </span>

            </div>


            <!-- FILTERS -->

            <form method="GET" class="report-filters">

                <div class="filter-group">

                    <label for="start_date">Start Date</label>

                    <input
                        type="date"
                        id="start_date"
                        name="start_date"
                        value="<?= htmlspecialchars($startDate) ?>"
                        required
                    >

                </div>

                <div class="filter-group">

                    <label for="end_date">End Date</label>

                    <input
                        type="date"
                        id="end_date"
                        name="end_date"
                        value="<?= htmlspecialchars($endDate) ?>"
                        required
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

                <button type="submit" class="filter-button">
    <i class="bi bi-funnel"></i> Generate Report
</button>

<button type="submit"
        formaction="export.php"
        class="export-button">
    <i class="bi bi-download"></i> Export CSV
</button>

                <a href="index.php" class="reset-button">
                    Reset
                </a>

            </form>


            <!-- TABLE -->

            <div class="table-wrapper">

                <table class="report-table">

                    <thead>

                        <tr>
                            <th>EMPLOYEE</th>
                            <th>POSITION</th>
                            <th>DATE</th>
                            <th>TIME IN</th>
                            <th>TIME OUT</th>
                            <th>STATUS</th>
                            <th>REMARKS</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php if (count($records) > 0): ?>

                            <?php foreach ($records as $record): ?>

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
                                        <?= date(
                                            'M d, Y',
                                            strtotime($record['attendance_date'])
                                        ) ?>
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

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="7" class="report-empty">
                                    No attendance records found for this period.
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
