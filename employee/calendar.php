
<?php

session_start();

date_default_timezone_set('Asia/Manila');

// Only logged-in employees can access this page.
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'employee'
) {
    header("Location: ../login.php");
    exit;
}

require_once '../connection/connect.php';

// Check whether the account is still active.
$userStmt = $conn->prepare("
    SELECT id, is_active
    FROM users
    WHERE id = :user_id
    LIMIT 1
");

$userStmt->execute([
    ':user_id' => $_SESSION['user_id']
]);

$user = $userStmt->fetch();

if (!$user || (int)$user['is_active'] !== 1) {
    session_unset();
    session_destroy();

    header("Location: ../login.php");
    exit;
}

// Retrieve employee information.
$employeeStmt = $conn->prepare("
    SELECT
        id,
        employee_code,
        first_name,
        last_name,
        position
    FROM employees
    WHERE user_id = :user_id
    LIMIT 1
");

$employeeStmt->execute([
    ':user_id' => $_SESSION['user_id']
]);

$employee = $employeeStmt->fetch();

if (!$employee) {
    die("Employee profile not found. Please contact your administrator.");
}

$employeeId = (int)$employee['id'];

// Get selected month and year.
$currentDate = new DateTime('today');

$selectedMonth = filter_input(
    INPUT_GET,
    'month',
    FILTER_VALIDATE_INT
);

$selectedYear = filter_input(
    INPUT_GET,
    'year',
    FILTER_VALIDATE_INT
);

if (
    !$selectedMonth ||
    !$selectedYear ||
    $selectedMonth < 1 ||
    $selectedMonth > 12 ||
    $selectedYear < 2000 ||
    $selectedYear > 2100
) {
    $selectedMonth = (int)$currentDate->format('n');
    $selectedYear = (int)$currentDate->format('Y');
}

// First and last day of selected month.
$monthStart = new DateTime(
    sprintf('%04d-%02d-01', $selectedYear, $selectedMonth)
);

$monthEnd = clone $monthStart;
$monthEnd->modify('last day of this month');

$startDate = $monthStart->format('Y-m-d');
$endDate = $monthEnd->format('Y-m-d');

// Retrieve attendance records for this employee and month.
$attendanceStmt = $conn->prepare("
    SELECT
        attendance_date,
        time_in,
        time_out,
        status,
        remarks
    FROM attendance
    WHERE employee_id = :employee_id
      AND attendance_date BETWEEN :start_date AND :end_date
");

$attendanceStmt->execute([
    ':employee_id' => $employeeId,
    ':start_date' => $startDate,
    ':end_date' => $endDate
]);

$attendanceRecords = $attendanceStmt->fetchAll();

// Organize attendance records by date.
$attendanceByDate = [];

foreach ($attendanceRecords as $record) {
    $attendanceByDate[$record['attendance_date']] = $record;
}

// Monthly statistics.
$totalRecorded = count($attendanceRecords);
$presentCount = 0;
$lateCount = 0;
$halfDayCount = 0;

foreach ($attendanceRecords as $record) {
    if ($record['status'] === 'Present') {
        $presentCount++;
    } elseif ($record['status'] === 'Late') {
        $lateCount++;
    } elseif ($record['status'] === 'Half Day') {
        $halfDayCount++;
    }
}

// Count weekdays that have passed without an attendance record.
// This is displayed as "No Record", not automatically as absence.
$noRecordCount = 0;

$dayIterator = clone $monthStart;

while ($dayIterator <= $monthEnd) {
    $dateKey = $dayIterator->format('Y-m-d');
    $dayOfWeek = (int)$dayIterator->format('N');

    $isWorkingDay = $dayOfWeek >= 1 && $dayOfWeek <= 6;
    $isPastOrToday = $dateKey <= $currentDate->format('Y-m-d');

    if (
        $isWorkingDay &&
        $isPastOrToday &&
        !isset($attendanceByDate[$dateKey])
    ) {
        $noRecordCount++;
    }

    $dayIterator->modify('+1 day');
}

// Calendar layout: Monday through Sunday.
$firstWeekday = (int)$monthStart->format('N');
$daysInMonth = (int)$monthEnd->format('j');

$previousMonth = clone $monthStart;
$previousMonth->modify('-1 month');

$nextMonth = clone $monthStart;
$nextMonth->modify('+1 month');

$previousMonthUrl = '?month=' .
    $previousMonth->format('n') .
    '&year=' .
    $previousMonth->format('Y');

$nextMonthUrl = '?month=' .
    $nextMonth->format('n') .
    '&year=' .
    $nextMonth->format('Y');

$todayString = $currentDate->format('Y-m-d');

// Selected day details.
$selectedDay = $_GET['day'] ?? $todayString;

$selectedDayObject = DateTime::createFromFormat(
    'Y-m-d',
    $selectedDay
);

if (
    !$selectedDayObject ||
    $selectedDayObject->format('Y-m-d') !== $selectedDay ||
    $selectedDay < $startDate ||
    $selectedDay > $endDate
) {
    $selectedDay = $todayString;

    if ($selectedDay < $startDate || $selectedDay > $endDate) {
        $selectedDay = $startDate;
    }
}

$selectedRecord = $attendanceByDate[$selectedDay] ?? null;

// Month navigation links that preserve the selected day when possible.
$previousMonthDay = min(
    (int)date('j', strtotime($selectedDay)),
    (int)$previousMonth->format('t')
);

$nextMonthDay = min(
    (int)date('j', strtotime($selectedDay)),
    (int)$nextMonth->format('t')
);

$previousDayUrl = $previousMonthUrl .
    '&day=' .
    $previousMonth->format('Y-m-') .
    str_pad($previousMonthDay, 2, '0', STR_PAD_LEFT);

$nextDayUrl = $nextMonthUrl .
    '&day=' .
    $nextMonth->format('Y-m-') .
    str_pad($nextMonthDay, 2, '0', STR_PAD_LEFT);

function escapeCalendar($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function formatAttendanceTime($time) {
    if (!$time) {
        return '—';
    }

    return date('h:i A', strtotime($time));
}

$selectedDateObject = new DateTime($selectedDay);
$selectedDayNumber = (int)$selectedDateObject->format('N');

$selectedDayIsWorkday = $selectedDayNumber >= 1 &&
                         $selectedDayNumber <= 6;

$selectedDayIsFuture = $selectedDay > $todayString;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Attendance Calendar | BePresent</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet"
    >

    <style>
        :root {
            --navy: #17243a;
            --navy-light: #243651;
            --teal: #14b8a6;
            --teal-dark: #0f9488;
            --coral: #f47c72;
            --lavender: #9b8cf4;
            --background: #f4f6fa;
            --text: #263449;
            --muted: #8994a5;
            --border: #e9edf3;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--background);
            color: var(--text);
            font-family: Arial, Helvetica, sans-serif;
        }

        .sidebar {
            width: 245px;
            min-height: 100vh;
            background: var(--navy);
            color: white;
            position: fixed;
            top: 0;
            left: 0;
            padding: 28px 18px;
            z-index: 100;
        }

        .brand {
            font-size: 23px;
            font-weight: 800;
            letter-spacing: -0.7px;
            padding: 0 12px;
            margin-bottom: 42px;
        }

        .brand span {
            color: var(--teal);
        }

        .sidebar-label {
            color: #8d9bb0;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1.4px;
            padding: 0 12px;
            margin-bottom: 12px;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 13px;
            color: #c5cede;
            text-decoration: none;
            padding: 13px 14px;
            border-radius: 10px;
            margin-bottom: 7px;
            font-size: 14px;
            transition: 0.2s;
        }

        .sidebar-link:hover,
        .sidebar-link.active {
            background: var(--navy-light);
            color: white;
        }

        .sidebar-link.active {
            border-left: 3px solid var(--teal);
        }

        .sidebar-link i {
            font-size: 18px;
        }

        .main-content {
            margin-left: 245px;
            padding: 32px;
            min-height: 100vh;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 28px;
        }

        .page-title {
            font-size: 26px;
            font-weight: 800;
            margin: 0;
            color: var(--navy);
        }

        .page-subtitle {
            color: var(--muted);
            font-size: 13px;
            margin-top: 7px;
        }

        .back-button {
            border: 1px solid var(--border);
            background: white;
            color: var(--navy);
            padding: 10px 15px;
            border-radius: 9px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
        }

        .back-button:hover {
            background: var(--navy);
            color: white;
        }

        .welcome-card {
            background: linear-gradient(
                115deg,
                #17243a,
                #263d5b
            );
            border-radius: 17px;
            color: white;
            padding: 25px 28px;
            margin-bottom: 25px;
            position: relative;
            overflow: hidden;
        }

        .welcome-card::after {
            content: "";
            position: absolute;
            width: 190px;
            height: 190px;
            border-radius: 50%;
            background: rgba(20, 184, 166, 0.12);
            right: -35px;
            top: -80px;
        }

        .welcome-card h2 {
            font-size: 21px;
            font-weight: 700;
            margin-bottom: 7px;
        }

        .welcome-card p {
            color: #c2cede;
            margin: 0;
            font-size: 13px;
        }

        .employee-code {
            display: inline-block;
            margin-top: 15px;
            background: rgba(255,255,255,0.1);
            padding: 6px 11px;
            border-radius: 7px;
            color: #dce7f2;
            font-size: 11px;
            letter-spacing: 0.5px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 17px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 13px;
            padding: 19px;
            min-width: 0;
        }

        .stat-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .stat-icon {
            width: 39px;
            height: 39px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .icon-teal {
            background: #e1f8f4;
            color: #0d9b8c;
        }

        .icon-green {
            background: #e7f7ed;
            color: #26965b;
        }

        .icon-coral {
            background: #fff0ed;
            color: #e66c60;
        }

        .icon-lavender {
            background: #f0edff;
            color: #8473e7;
        }

        .stat-label {
            font-size: 12px;
            color: var(--muted);
            margin-bottom: 5px;
        }

        .stat-number {
            font-size: 25px;
            font-weight: 800;
            color: var(--navy);
        }

        .calendar-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 300px;
            gap: 20px;
            align-items: start;
        }

        .panel {
            background: white;
            border: 1px solid var(--border);
            border-radius: 15px;
            padding: 23px;
            min-width: 0;
        }

        .panel-heading {
            font-size: 16px;
            font-weight: 800;
            color: var(--navy);
            margin: 0;
        }

        .month-navigation {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin-bottom: 22px;
        }

        .month-title {
            font-size: 18px;
            font-weight: 800;
            color: var(--navy);
            text-align: center;
        }

        .month-arrow {
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 9px;
            border: 1px solid var(--border);
            color: var(--navy);
            text-decoration: none;
            transition: 0.2s;
        }

        .month-arrow:hover {
            background: var(--navy);
            color: white;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 7px;
        }

        .weekday {
            text-align: center;
            color: var(--muted);
            font-size: 11px;
            font-weight: 700;
            padding: 8px 0;
        }

        .calendar-day {
            min-height: 84px;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 9px 7px;
            background: white;
            text-decoration: none;
            color: var(--text);
            display: flex;
            flex-direction: column;
            gap: 8px;
            transition: 0.15s;
            min-width: 0;
        }

        .calendar-day:hover {
            border-color: var(--teal);
            background: #f5fffd;
        }

        .calendar-day.empty {
            border: none;
            background: transparent;
            pointer-events: none;
        }

        .calendar-day.selected {
            border: 2px solid var(--teal);
            background: #f0fdfa;
            padding: 8px 6px;
        }

        .calendar-day.today .day-number {
            background: var(--navy);
            color: white;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .day-number {
            font-size: 12px;
            font-weight: 700;
            width: 25px;
            height: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .day-status {
            font-size: 9px;
            font-weight: 700;
            padding: 4px 5px;
            border-radius: 5px;
            text-align: center;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .status-present {
            background: #e5f7eb;
            color: #23834b;
        }

        .status-late {
            background: #fff0ed;
            color: #d75e52;
        }

        .status-half {
            background: #f0edff;
            color: #7665d5;
        }

        .status-no-record {
            background: #f0f2f5;
            color: #7d8999;
        }

        .status-future {
            background: #f7f8fa;
            color: #b1b9c5;
        }

        .status-rest {
            background: #f2f4f8;
            color: #a0a8b4;
        }

        .legend {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            margin-top: 20px;
            padding-top: 17px;
            border-top: 1px solid var(--border);
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            color: #687587;
        }

        .legend-dot {
            width: 9px;
            height: 9px;
            border-radius: 3px;
        }

        .detail-panel {
            position: sticky;
            top: 20px;
        }

        .detail-date {
            color: var(--muted);
            font-size: 12px;
            margin-top: 7px;
            margin-bottom: 20px;
        }

        .detail-status {
            display: inline-block;
            padding: 7px 11px;
            border-radius: 7px;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 20px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            padding: 14px 0;
            border-bottom: 1px solid var(--border);
        }

        .detail-row:last-of-type {
            border-bottom: none;
        }

        .detail-label {
            font-size: 12px;
            color: var(--muted);
        }

        .detail-value {
            font-size: 13px;
            font-weight: 700;
            color: var(--navy);
            text-align: right;
        }

        .remarks-box {
            background: #f7f9fc;
            border-radius: 9px;
            padding: 12px;
            margin-top: 16px;
        }

        .remarks-box .detail-label {
            margin-bottom: 6px;
        }

        .remarks-text {
            font-size: 12px;
            color: #566477;
            overflow-wrap: anywhere;
        }

        .empty-detail {
            text-align: center;
            padding: 28px 10px;
        }

        .empty-detail i {
            font-size: 35px;
            color: #b5c0ce;
        }

        .empty-detail h6 {
            font-size: 14px;
            font-weight: 700;
            margin-top: 12px;
            color: var(--navy);
        }

        .empty-detail p {
            font-size: 12px;
            color: var(--muted);
            margin: 0;
        }

        .footer-note {
            color: var(--muted);
            font-size: 11px;
            margin-top: 18px;
            line-height: 1.6;
        }

        @media (max-width: 1100px) {
            .calendar-layout {
                grid-template-columns: 1fr;
            }

            .detail-panel {
                position: static;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                position: static;
                width: 100%;
                min-height: auto;
                padding: 15px;
            }

            .brand {
                margin-bottom: 15px;
            }

            .sidebar-label {
                display: none;
            }

            .sidebar-nav {
                display: flex;
                flex-wrap: wrap;
                gap: 5px;
            }

            .sidebar-link {
                margin: 0;
                padding: 9px 11px;
                font-size: 12px;
            }

            .main-content {
                margin-left: 0;
                padding: 20px 14px;
            }

            .topbar {
                align-items: flex-start;
            }

            .page-title {
                font-size: 22px;
            }

            .panel {
                padding: 15px;
            }

            .calendar-grid {
                gap: 4px;
            }

            .calendar-day {
                min-height: 66px;
                padding: 6px 3px;
                border-radius: 7px;
            }

            .calendar-day.selected {
                padding: 5px 2px;
            }

            .day-status {
                font-size: 8px;
                padding: 3px 2px;
            }

            .weekday {
                font-size: 9px;
            }

            .legend {
                gap: 10px;
            }
        }

        @media (max-width: 420px) {
            .stats-grid {
                gap: 9px;
            }

            .stat-card {
                padding: 13px;
            }

            .stat-number {
                font-size: 21px;
            }

            .stat-label {
                font-size: 11px;
            }

            .welcome-card {
                padding: 20px;
            }

            .month-title {
                font-size: 15px;
            }

            .calendar-day {
                min-height: 58px;
            }

            .day-number {
                font-size: 11px;
                width: 22px;
                height: 22px;
            }
        }
    </style>
</head>

<body>

<!-- Sidebar -->
<aside class="sidebar">

    <div class="brand">
        Be<span>Present</span>
    </div>

    <div class="sidebar-label">
        EMPLOYEE MENU
    </div>

    <nav class="sidebar-nav">

        <a href="dashboard.php" class="sidebar-link">
            <i class="bi bi-grid"></i>
            Dashboard
        </a>

        <a href="history.php" class="sidebar-link">
            <i class="bi bi-clock-history"></i>
            Attendance History
        </a>

        <a href="calendar.php" class="sidebar-link active">
            <i class="bi bi-calendar3"></i>
            Attendance Calendar
        </a>

        <a href="../logout.php" class="sidebar-link">
            <i class="bi bi-box-arrow-right"></i>
            Logout
        </a>

    </nav>

</aside>

<!-- Main Content -->
<main class="main-content">

    <div class="topbar">

        <div>
            <h1 class="page-title">Attendance Calendar</h1>

            <p class="page-subtitle">
                View your monthly attendance at a glance.
            </p>
        </div>

        <a href="dashboard.php" class="back-button">
            <i class="bi bi-arrow-left me-1"></i>
            Dashboard
        </a>

    </div>

    <!-- Welcome Card -->
    <section class="welcome-card">

        <h2>
            Hello, <?= escapeCalendar($employee['first_name']); ?>!
        </h2>

        <p>
            Here's your attendance overview for
            <?= escapeCalendar($monthStart->format('F Y')); ?>.
        </p>

        <span class="employee-code">
            <i class="bi bi-person-badge me-1"></i>
            <?= escapeCalendar($employee['employee_code']); ?>
        </span>

    </section>

    <!-- Monthly Statistics -->
    <section class="stats-grid">

        <div class="stat-card">

            <div class="stat-top">
                <div class="stat-icon icon-teal">
                    <i class="bi bi-calendar-check"></i>
                </div>
            </div>

            <div class="stat-label">Recorded Days</div>

            <div class="stat-number">
                <?= $totalRecorded; ?>
            </div>

        </div>

        <div class="stat-card">

            <div class="stat-top">
                <div class="stat-icon icon-green">
                    <i class="bi bi-check-circle"></i>
                </div>
            </div>

            <div class="stat-label">Present</div>

            <div class="stat-number">
                <?= $presentCount; ?>
            </div>

        </div>

        <div class="stat-card">

            <div class="stat-top">
                <div class="stat-icon icon-coral">
                    <i class="bi bi-clock"></i>
                </div>
            </div>

            <div class="stat-label">Late</div>

            <div class="stat-number">
                <?= $lateCount; ?>
            </div>

        </div>

        <div class="stat-card">

            <div class="stat-top">
                <div class="stat-icon icon-lavender">
                    <i class="bi bi-hourglass-split"></i>
                </div>
            </div>

            <div class="stat-label">Half Day</div>

            <div class="stat-number">
                <?= $halfDayCount; ?>
            </div>

        </div>

    </section>

    <!-- Calendar and Details -->
    <section class="calendar-layout">

        <div class="panel">

            <div class="month-navigation">

                <a
                    href="<?= escapeCalendar($previousMonthUrl); ?>"
                    class="month-arrow"
                    aria-label="Previous month"
                >
                    <i class="bi bi-chevron-left"></i>
                </a>

                <div class="month-title">
                    <?= escapeCalendar($monthStart->format('F Y')); ?>
                </div>

                <a
                    href="<?= escapeCalendar($nextMonthUrl); ?>"
                    class="month-arrow"
                    aria-label="Next month"
                >
                    <i class="bi bi-chevron-right"></i>
                </a>

            </div>

            <div class="calendar-grid">

                <?php
                $weekdays = [
                    'Mon', 'Tue', 'Wed',
                    'Thu', 'Fri', 'Sat', 'Sun'
                ];

                foreach ($weekdays as $weekday):
                ?>

                    <div class="weekday">
                        <?= $weekday; ?>
                    </div>

                <?php endforeach; ?>

                <!-- Empty spaces before the first day -->
                <?php for ($i = 1; $i < $firstWeekday; $i++): ?>

                    <div class="calendar-day empty"></div>

                <?php endfor; ?>

                <!-- Calendar dates -->
                <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>

                    <?php

                    $dateKey = sprintf(
                        '%04d-%02d-%02d',
                        $selectedYear,
                        $selectedMonth,
                        $day
                    );

                    $dayObject = new DateTime($dateKey);

                    $dayOfWeek = (int)$dayObject->format('N');

                    $isSunday = $dayOfWeek === 7;
                    $isFuture = $dateKey > $todayString;
                    $isToday = $dateKey === $todayString;
                    $isSelected = $dateKey === $selectedDay;

                    $record = $attendanceByDate[$dateKey] ?? null;

                    $statusText = '';
                    $statusClass = '';

                    if ($record) {

                        if ($record['status'] === 'Present') {
                            $statusText = 'Present';
                            $statusClass = 'status-present';

                        } elseif ($record['status'] === 'Late') {
                            $statusText = 'Late';
                            $statusClass = 'status-late';

                        } elseif ($record['status'] === 'Half Day') {
                            $statusText = 'Half Day';
                            $statusClass = 'status-half';
                        }

                    } elseif ($isSunday) {

                        $statusText = 'Rest Day';
                        $statusClass = 'status-rest';

                    } elseif ($isFuture) {

                        $statusText = 'Upcoming';
                        $statusClass = 'status-future';

                    } else {

                        $statusText = 'No Record';
                        $statusClass = 'status-no-record';

                    }

                    $dayUrl = '?month=' .
                        $selectedMonth .
                        '&year=' .
                        $selectedYear .
                        '&day=' .
                        $dateKey;

                    $dayClasses = 'calendar-day';

                    if ($isToday) {
                        $dayClasses .= ' today';
                    }

                    if ($isSelected) {
                        $dayClasses .= ' selected';
                    }

                    ?>

                    <a
                        href="<?= escapeCalendar($dayUrl); ?>"
                        class="<?= $dayClasses; ?>"
                        aria-label="<?= escapeCalendar(
                            $dayObject->format('F j, Y') .
                            ' - ' .
                            $statusText
                        ); ?>"
                    >

                        <span class="day-number">
                            <?= $day; ?>
                        </span>

                        <span class="day-status <?= $statusClass; ?>">
                            <?= escapeCalendar($statusText); ?>
                        </span>

                    </a>

                <?php endfor; ?>

            </div>

            <!-- Legend -->
            <div class="legend">

                <div class="legend-item">
                    <span class="legend-dot status-present"></span>
                    Present
                </div>

                <div class="legend-item">
                    <span class="legend-dot status-late"></span>
                    Late
                </div>

                <div class="legend-item">
                    <span class="legend-dot status-half"></span>
                    Half Day
                </div>

                <div class="legend-item">
                    <span class="legend-dot status-no-record"></span>
                    No Record
                </div>

                <div class="legend-item">
                    <span class="legend-dot status-rest"></span>
                    Rest Day
                </div>

                <div class="legend-item">
                    <span class="legend-dot status-future"></span>
                    Upcoming
                </div>

            </div>

            <p class="footer-note">
                Attendance information is based on the records saved in
                BePresent. Sundays are displayed as rest days.
                A working day without a saved record is not automatically
                considered an absence.
            </p>

        </div>

        <!-- Selected Day Details -->
        <div class="panel detail-panel">

            <h3 class="panel-heading">
                <i class="bi bi-calendar-event me-2"></i>
                Day Details
            </h3>

            <div class="detail-date">
                <?= escapeCalendar(
                    $selectedDateObject->format('l, F j, Y')
                ); ?>
            </div>

            <?php if ($selectedRecord): ?>

                <?php

                $detailStatusClass = 'status-present';

                if ($selectedRecord['status'] === 'Late') {
                    $detailStatusClass = 'status-late';

                } elseif ($selectedRecord['status'] === 'Half Day') {
                    $detailStatusClass = 'status-half';
                }

                ?>

                <span class="detail-status <?= $detailStatusClass; ?>">
                    <?= escapeCalendar($selectedRecord['status']); ?>
                </span>

                <div class="detail-row">

                    <span class="detail-label">
                        <i class="bi bi-box-arrow-in-right me-1"></i>
                        Time In
                    </span>

                    <span class="detail-value">
                        <?= formatAttendanceTime(
                            $selectedRecord['time_in']
                        ); ?>
                    </span>

                </div>

                <div class="detail-row">

                    <span class="detail-label">
                        <i class="bi bi-box-arrow-right me-1"></i>
                        Time Out
                    </span>

                    <span class="detail-value">
                        <?= formatAttendanceTime(
                            $selectedRecord['time_out']
                        ); ?>
                    </span>

                </div>

                <?php if (!empty($selectedRecord['remarks'])): ?>

                    <div class="remarks-box">

                        <div class="detail-label">
                            Remarks
                        </div>

                        <div class="remarks-text">
                            <?= escapeCalendar(
                                $selectedRecord['remarks']
                            ); ?>
                        </div>

                    </div>

                <?php endif; ?>

            <?php else: ?>

                <div class="empty-detail">

                    <?php if ($selectedDayIsFuture): ?>

                        <i class="bi bi-calendar-plus"></i>

                        <h6>Upcoming Day</h6>

                        <p>
                            This date has not happened yet.
                        </p>

                    <?php elseif (!$selectedDayIsWorkday): ?>

                        <i class="bi bi-calendar-minus"></i>

                        <h6>Rest Day</h6>

                        <p>
                            Sunday is currently treated as a rest day.
                        </p>

                    <?php else: ?>

                        <i class="bi bi-calendar-x"></i>

                        <h6>No Attendance Record</h6>

                        <p>
                            There is no attendance record saved for this date.
                        </p>

                    <?php endif; ?>

                </div>

            <?php endif; ?>

        </div>

    </section>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
