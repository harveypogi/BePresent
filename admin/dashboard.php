
<?php

session_start();

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {
    header("Location: ../login.php");
    exit;
}

require_once '../connection/connect.php';

date_default_timezone_set('Asia/Manila');

// Today's date and working-day check
$today = date('Y-m-d');
$isWorkingDay = date('N') != 7; // Monday-Saturday

// Total active employees
$totalEmployees = (int) $conn->query("
    SELECT COUNT(*)
    FROM employees
    WHERE employment_status = 'Active'
")->fetchColumn();

// Today's attendance statistics for active employees
$stmt = $conn->prepare("
    SELECT
        COUNT(attendance.id) AS checked_in,
        SUM(CASE WHEN attendance.status = 'Late' THEN 1 ELSE 0 END) AS late,
        SUM(CASE WHEN attendance.status = 'Present' THEN 1 ELSE 0 END) AS present,
        SUM(CASE WHEN attendance.status = 'Half Day' THEN 1 ELSE 0 END) AS half_day
    FROM employees
    LEFT JOIN attendance
        ON attendance.employee_id = employees.id
        AND attendance.attendance_date = ?
    WHERE employees.employment_status = 'Active'
");

$stmt->execute([$today]);
$attendanceStats = $stmt->fetch();

$checkedIn = (int) ($attendanceStats['checked_in'] ?? 0);
$lateEmployees = (int) ($attendanceStats['late'] ?? 0);
$presentEmployees = (int) ($attendanceStats['present'] ?? 0);
$halfDayEmployees = (int) ($attendanceStats['half_day'] ?? 0);

// Absences are calculated only on working days
$absentEmployees = $isWorkingDay
    ? max(0, $totalEmployees - $checkedIn)
    : 0;

// Attendance rate
$attendanceRate = ($isWorkingDay && $totalEmployees > 0)
    ? round(($checkedIn / $totalEmployees) * 100, 1)
    : null;

// Breakdown percentages
$presentPercentage = $totalEmployees > 0
    ? round(($presentEmployees / $totalEmployees) * 100, 1)
    : 0;

$latePercentage = $totalEmployees > 0
    ? round(($lateEmployees / $totalEmployees) * 100, 1)
    : 0;

$halfDayPercentage = $totalEmployees > 0
    ? round(($halfDayEmployees / $totalEmployees) * 100, 1)
    : 0;

$absentPercentage = ($isWorkingDay && $totalEmployees > 0)
    ? round(($absentEmployees / $totalEmployees) * 100, 1)
    : 0;

// Recent attendance records
$stmt = $conn->prepare("
    SELECT
        employees.employee_code,
        employees.first_name,
        employees.last_name,
        employees.position,
        attendance.attendance_date,
        attendance.time_in,
        attendance.time_out,
        attendance.status
    FROM attendance
    INNER JOIN employees
        ON attendance.employee_id = employees.id
    ORDER BY attendance.attendance_date DESC,
             attendance.created_at DESC
    LIMIT 5
");

$stmt->execute();
$recentAttendance = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Dashboard | BePresent</title>

    <link rel="stylesheet" href="../assets/css/dashboard.css">

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

            <a href="dashboard.php" class="active">
                <span class="menu-icon">
                    <i class="bi bi-grid"></i>
                </span>
                <span class="menu-text">Dashboard</span>
            </a>

            <a href="employees/index.php">
                <span class="menu-icon">
                    <i class="bi bi-people"></i>
                </span>
                <span class="menu-text">Employees</span>
            </a>

            <a href="attendance/index.php">
                <span class="menu-icon">
                    <i class="bi bi-calendar-check"></i>
                </span>
                <span class="menu-text">Attendance</span>
            </a>

            <a href="reports/index.php">
                <span class="menu-icon">
                    <i class="bi bi-file-earmark-bar-graph"></i>
                </span>
                <span class="menu-text">Reports</span>
            </a>

        </nav>

        <div class="sidebar-bottom">

            <nav class="sidebar-menu">
                <a href="../logout.php">
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
                <h1>Dashboard</h1>
                <p>Here's what's happening with your workplace today.</p>
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


        <!-- DATE -->

        <div class="dashboard-date">
            <i class="bi bi-calendar3"></i>
            <?= date('l, F d, Y') ?>

            <?php if (!$isWorkingDay): ?>
                <span class="rest-day-label">Sunday · Rest Day</span>
            <?php else: ?>
                <span class="workday-label">Working Day</span>
            <?php endif; ?>
        </div>


        <!-- OVERVIEW -->

        <h2 class="section-heading">Overview</h2>

        <section class="stats-grid">

            <div class="stat-card">
                <div class="stat-top">
                    <p>Total Employees</p>
                    <div class="stat-icon icon-blue">
                        <i class="bi bi-people"></i>
                    </div>
                </div>
                <h2><?= number_format($totalEmployees) ?></h2>
                <span class="stat-description">Active employees</span>
            </div>


            <div class="stat-card">
                <div class="stat-top">
                    <p>Checked In Today</p>
                    <div class="stat-icon icon-green">
                        <i class="bi bi-person-check"></i>
                    </div>
                </div>
                <h2><?= number_format($checkedIn) ?></h2>
                <span class="stat-description">Employees with attendance</span>
            </div>


            <div class="stat-card">
                <div class="stat-top">
                    <p>Late Today</p>
                    <div class="stat-icon icon-orange">
                        <i class="bi bi-clock-history"></i>
                    </div>
                </div>
                <h2><?= number_format($lateEmployees) ?></h2>
                <span class="stat-description">Late arrivals</span>
            </div>


            <div class="stat-card">
                <div class="stat-top">
                    <p>Absent Today</p>
                    <div class="stat-icon icon-red">
                        <i class="bi bi-person-x"></i>
                    </div>
                </div>
                <h2><?= number_format($absentEmployees) ?></h2>
                <span class="stat-description">
                    <?= $isWorkingDay ? 'No attendance record' : 'Rest day' ?>
                </span>
            </div>


            <div class="stat-card">
                <div class="stat-top">
                    <p>Attendance Rate</p>
                    <div class="stat-icon icon-purple">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                </div>
                <h2>
                    <?= $attendanceRate !== null
                        ? number_format($attendanceRate, 1) . '%'
                        : '--' ?>
                </h2>
                <span class="stat-description">
                    <?= $isWorkingDay ? 'Of active employees' : 'Not applicable on rest day' ?>
                </span>
            </div>

        </section>


        <!-- ATTENDANCE BREAKDOWN -->

        <section class="content-card breakdown-card">

            <div class="card-header">
                <h3>Attendance Breakdown</h3>
                <span>Today's attendance overview</span>
            </div>

            <?php if (!$isWorkingDay): ?>

                <div class="breakdown-rest-day">
                    <i class="bi bi-calendar-heart"></i>
                    <strong>It's a rest day!</strong>
                    <span>Attendance breakdown is not applicable on Sundays.</span>
                </div>

            <?php else: ?>

                <div class="breakdown-content">

                    <div class="breakdown-item">
                        <div class="breakdown-label">
                            <span><i class="bi bi-circle-fill breakdown-dot present-dot"></i> Present</span>
                            <strong><?= $presentEmployees ?></strong>
                        </div>
                        <div class="progress-track">
                            <div class="progress-fill present-fill"
                                style="width: <?= $presentPercentage ?>%"></div>
                        </div>
                        <small><?= $presentPercentage ?>% of active employees</small>
                    </div>


                    <div class="breakdown-item">
                        <div class="breakdown-label">
                            <span><i class="bi bi-circle-fill breakdown-dot late-dot"></i> Late</span>
                            <strong><?= $lateEmployees ?></strong>
                        </div>
                        <div class="progress-track">
                            <div class="progress-fill late-fill"
                                style="width: <?= $latePercentage ?>%"></div>
                        </div>
                        <small><?= $latePercentage ?>% of active employees</small>
                    </div>


                    <div class="breakdown-item">
                        <div class="breakdown-label">
                            <span><i class="bi bi-circle-fill breakdown-dot half-dot"></i> Half Day</span>
                            <strong><?= $halfDayEmployees ?></strong>
                        </div>
                        <div class="progress-track">
                            <div class="progress-fill half-fill"
                                style="width: <?= $halfDayPercentage ?>%"></div>
                        </div>
                        <small><?= $halfDayPercentage ?>% of active employees</small>
                    </div>


                    <div class="breakdown-item">
                        <div class="breakdown-label">
                            <span><i class="bi bi-circle-fill breakdown-dot absent-dot"></i> Absent</span>
                            <strong><?= $absentEmployees ?></strong>
                        </div>
                        <div class="progress-track">
                            <div class="progress-fill absent-fill"
                                style="width: <?= $absentPercentage ?>%"></div>
                        </div>
                        <small><?= $absentPercentage ?>% of active employees</small>
                    </div>

                </div>

            <?php endif; ?>

        </section>


        <!-- RECENT ATTENDANCE ACTIVITY -->

        <section class="content-card">

            <div class="card-header">
                <h3>Recent Attendance Activity</h3>
                <span>Latest 5 records</span>
            </div>

            <div class="table-wrapper">

                <table>

                    <thead>
                        <tr>
                            <th>EMPLOYEE</th>
                            <th>POSITION</th>
                            <th>DATE</th>
                            <th>TIME IN</th>
                            <th>TIME OUT</th>
                            <th>STATUS</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if (count($recentAttendance) > 0): ?>

                            <?php foreach ($recentAttendance as $record): ?>

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
                                        <strong>
                                            <?= htmlspecialchars(
                                                $record['first_name'] . ' ' .
                                                $record['last_name']
                                            ) ?>
                                        </strong>
                                        <br>
                                        <?= htmlspecialchars($record['employee_code']) ?>
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

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="6" class="empty-state">
                                    No attendance records available yet.
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
