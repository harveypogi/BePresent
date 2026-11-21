
<?php

session_start();

if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'admin'
) {
    header("Location: ../../login.php");
    exit;
}

require_once '../../connection/connect.php';

date_default_timezone_set('Asia/Manila');

// Fetch correction history
$stmt = $conn->prepare("
    SELECT
        attendance_logs.*,
        attendance.attendance_date,
        employees.employee_code,
        employees.first_name,
        employees.last_name,
        users.username AS admin_username
    FROM attendance_logs
    INNER JOIN attendance
        ON attendance_logs.attendance_id = attendance.id
    INNER JOIN employees
        ON attendance.employee_id = employees.id
    INNER JOIN users
        ON attendance_logs.admin_user_id = users.id
    ORDER BY attendance_logs.created_at DESC
");

$stmt->execute();
$historyRecords = $stmt->fetchAll();

function displayTime($time)
{
    return $time
        ? date('h:i A', strtotime($time))
        : '--';
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Correction History | BePresent</title>

    <link rel="stylesheet" href="../../assets/css/dashboard.css">

    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>

        .history-card {
            background: white;
            border-radius: 10px;
            padding: 22px;
            margin-top: 25px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.04);
        }

        .history-table-wrapper {
            overflow-x: auto;
        }

        .history-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1050px;
        }

        .history-table th,
        .history-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eeeeee;
            font-size: 12px;
            vertical-align: top;
        }

        .history-table th {
            background: #fafafa;
            color: #777;
            font-weight: 600;
        }

        .history-table td {
            color: #444;
        }

        .history-employee {
            font-weight: 600;
            color: #243b64;
        }

        .history-code {
            color: #999;
            font-size: 11px;
            margin-top: 4px;
        }

        .history-reason {
            max-width: 240px;
            white-space: normal;
            line-height: 1.5;
        }

        .history-empty {
            text-align: center !important;
            padding: 40px !important;
            color: #888 !important;
        }

        .back-button {
            display: inline-block;
            padding: 10px 15px;
            background: #243b64;
            color: white;
            text-decoration: none;
            border-radius: 7px;
            font-size: 12px;
            font-weight: 600;
        }

        .back-button:hover {
            background: #192c4d;
            color: white;
        }

        .history-subtitle {
            color: #777;
            font-size: 12px;
            margin-top: 6px;
        }

        .history-count {
            color: #777;
            font-size: 12px;
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

        <header class="topbar">

            <div>
                <h1>Correction History</h1>

                <p>
                    View the history of corrected attendance records.
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


        <section class="history-card">

            <div class="card-header">

                <div>
                    <h3>Attendance Correction Logs</h3>

                    <p class="history-subtitle">
                        Records of changes made by administrators.
                    </p>
                </div>

                <span class="history-count">
                    <?= count($historyRecords) ?> corrections
                </span>

            </div>


            <div class="history-table-wrapper">

                <table class="history-table">

                    <thead>

                        <tr>
                            <th>EMPLOYEE</th>
                            <th>ATTENDANCE DATE</th>
                            <th>OLD TIME IN</th>
                            <th>NEW TIME IN</th>
                            <th>OLD TIME OUT</th>
                            <th>NEW TIME OUT</th>
                            <th>OLD STATUS</th>
                            <th>NEW STATUS</th>
                            <th>REASON</th>
                            <th>ADMIN</th>
                            <th>DATE CORRECTED</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php if (count($historyRecords) > 0): ?>

                            <?php foreach ($historyRecords as $record): ?>

                                <tr>

                                    <td>
                                        <div class="history-employee">
                                            <?= htmlspecialchars(
                                                $record['first_name'] . ' ' .
                                                $record['last_name']
                                            ) ?>
                                        </div>

                                        <div class="history-code">
                                            <?= htmlspecialchars($record['employee_code']) ?>
                                        </div>
                                    </td>

                                    <td>
                                        <?= date(
                                            'M d, Y',
                                            strtotime($record['attendance_date'])
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= displayTime($record['old_time_in']) ?>
                                    </td>

                                    <td>
                                        <?= displayTime($record['new_time_in']) ?>
                                    </td>

                                    <td>
                                        <?= displayTime($record['old_time_out']) ?>
                                    </td>

                                    <td>
                                        <?= displayTime($record['new_time_out']) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($record['old_status'] ?? '--') ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($record['new_status'] ?? '--') ?>
                                    </td>

                                    <td class="history-reason">
                                        <?= htmlspecialchars($record['correction_reason']) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($record['admin_username']) ?>
                                    </td>

                                    <td>
                                        <?= date(
                                            'M d, Y h:i A',
                                            strtotime($record['created_at'])
                                        ) ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="11" class="history-empty">
                                    <i class="bi bi-clock-history"></i>
                                    <p>No attendance corrections have been recorded yet.</p>
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

            <div style="margin-top: 20px;">
                <a href="index.php" class="back-button">
                    <i class="bi bi-arrow-left"></i>
                    Back to Attendance
                </a>
            </div>

        </section>

    </main>

</body>
</html>
