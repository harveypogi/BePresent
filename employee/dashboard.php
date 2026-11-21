
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

$user_id = (int) $_SESSION['user_id'];

$today = date('Y-m-d');
$current_time = date('H:i:s');

$message = '';
$message_type = 'success';

// Work schedule
$official_start_time = '08:00:00';
$grace_period_minutes = 15;

// Sunday is a rest day.
$is_sunday = (date('N') == 7);

// Generate CSRF token if one does not exist.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Retrieve employee information and verify account status.
$stmt = $conn->prepare("
    SELECT
        e.id,
        e.first_name,
        e.last_name,
        e.employee_code,
        e.position,
        u.password
    FROM employees e
    INNER JOIN users u ON e.user_id = u.id
    WHERE e.user_id = :user_id
      AND u.is_active = 1
    LIMIT 1
");

$stmt->execute([
    'user_id' => $user_id
]);

$employee = $stmt->fetch();

if (!$employee) {
    session_unset();
    session_destroy();

    header("Location: ../login.php");
    exit;
}

$employee_id = (int) $employee['id'];
$password_hash = $employee['password'];

// Retrieve any message saved after a previous form submission.
if (isset($_SESSION['attendance_flash'])) {

    $message = $_SESSION['attendance_flash']['message'];
    $message_type = $_SESSION['attendance_flash']['type'];

    unset($_SESSION['attendance_flash']);
}

// Handle attendance actions.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $message = '';
    $message_type = 'success';

    // Validate CSRF token.
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals(
            $_SESSION['csrf_token'],
            $_POST['csrf_token']
        )
    ) {

        $message = "Invalid request. Please refresh the page and try again.";
        $message_type = 'error';

    } else {

        $action = $_POST['action'] ?? '';
        $entered_password = $_POST['password'] ?? '';

        if (!in_array($action, ['time_in', 'time_out'], true)) {

            $message = "Invalid attendance action.";
            $message_type = 'error';

        } elseif (!password_verify($entered_password, $password_hash)) {

            $message = "Incorrect password. Your attendance was not recorded.";
            $message_type = 'error';

        } elseif ($action === 'time_in') {

            if ($is_sunday) {

                $message = "Today is Sunday, your scheduled rest day.";
                $message_type = 'error';

            } else {

                try {

                    // Check whether attendance already exists today.
                    $check = $conn->prepare("
                        SELECT id
                        FROM attendance
                        WHERE employee_id = :employee_id
                          AND attendance_date = :today
                        LIMIT 1
                    ");

                    $check->execute([
                        'employee_id' => $employee_id,
                        'today' => $today
                    ]);

                    if ($check->fetch()) {

                        $message = "You have already recorded your attendance today.";
                        $message_type = 'error';

                    } else {

                        // Determine whether the employee is late.
                        $late_deadline = date(
                            'H:i:s',
                            strtotime(
                                $official_start_time .
                                " +$grace_period_minutes minutes"
                            )
                        );

                        $attendance_status = (
                            $current_time > $late_deadline
                        ) ? 'Late' : 'Present';

                        // Insert attendance.
                        $insert = $conn->prepare("
                            INSERT INTO attendance
                            (
                                employee_id,
                                attendance_date,
                                time_in,
                                status
                            )
                            VALUES
                            (
                                :employee_id,
                                :today,
                                :time_in,
                                :status
                            )
                        ");

                        $insert->execute([
                            'employee_id' => $employee_id,
                            'today' => $today,
                            'time_in' => $current_time,
                            'status' => $attendance_status
                        ]);

                        $message = $attendance_status === 'Late'
                            ? "Time-In recorded successfully. Your attendance is marked Late."
                            : "Time-In recorded successfully. Your attendance is marked Present.";
                    }

                } catch (PDOException $e) {

                    error_log($e->getMessage());

                    // Handle duplicate employee/date submissions.
                    if ($e->getCode() === '23000') {

                        $message = "Your attendance has already been recorded today.";

                    } else {

                        $message = "Something went wrong. Please try again.";
                    }

                    $message_type = 'error';
                }
            }

        } elseif ($action === 'time_out') {

            try {

                // Retrieve today's attendance before allowing Time-Out.
                $check = $conn->prepare("
                    SELECT
                        id,
                        time_in,
                        time_out
                    FROM attendance
                    WHERE employee_id = :employee_id
                      AND attendance_date = :today
                    LIMIT 1
                ");

                $check->execute([
                    'employee_id' => $employee_id,
                    'today' => $today
                ]);

                $todayAttendance = $check->fetch();

                if (!$todayAttendance || !$todayAttendance['time_in']) {

                    $message = "You must record your Time-In first.";
                    $message_type = 'error';

                } elseif ($todayAttendance['time_out'] !== null) {

                    $message = "You have already recorded your Time-Out.";
                    $message_type = 'error';

                } elseif (
                    strtotime($current_time) <
                    strtotime($todayAttendance['time_in'])
                ) {

                    $message = "Time-Out cannot be earlier than your Time-In.";
                    $message_type = 'error';

                } else {

                    // The SQL condition also protects against invalid updates.
                    $update = $conn->prepare("
                        UPDATE attendance
                        SET time_out = :time_out
                        WHERE employee_id = :employee_id
                          AND attendance_date = :today
                          AND time_in IS NOT NULL
                          AND time_out IS NULL
                          AND time_in <= :comparison_time
                    ");

                    $update->execute([
                        'time_out' => $current_time,
                        'employee_id' => $employee_id,
                        'today' => $today,
                        'comparison_time' => $current_time
                    ]);

                    if ($update->rowCount() > 0) {

                        $message = "Time-Out recorded successfully!";

                    } else {

                        $message = "Unable to record Time-Out. Please check your attendance record.";
                        $message_type = 'error';
                    }
                }

            } catch (PDOException $e) {

                error_log($e->getMessage());

                $message = "Something went wrong. Please try again.";
                $message_type = 'error';
            }
        }
    }

    // Save the message and redirect to prevent form resubmission.
    $_SESSION['attendance_flash'] = [
        'message' => $message,
        'type' => $message_type
    ];

    header("Location: dashboard.php");
    exit;
}

// Get today's attendance.
$stmt = $conn->prepare("
    SELECT *
    FROM attendance
    WHERE employee_id = :employee_id
      AND attendance_date = :today
    LIMIT 1
");

$stmt->execute([
    'employee_id' => $employee_id,
    'today' => $today
]);

$attendance = $stmt->fetch();

$time_in = $attendance['time_in'] ?? null;
$time_out = $attendance['time_out'] ?? null;
$attendance_status = $attendance['status'] ?? null;

// Calculate working hours.
$working_hours = null;

if ($time_in && $time_out) {

    $time_in_timestamp = strtotime($time_in);
    $time_out_timestamp = strtotime($time_out);

    $total_minutes = max(
        0,
        (int) floor(
            ($time_out_timestamp - $time_in_timestamp) / 60
        )
    );

    $hours = floor($total_minutes / 60);
    $minutes = $total_minutes % 60;

    $working_hours = $hours .
        ' hour(s) ' .
        $minutes .
        ' minute(s)';
}

// Attendance status styling.
$status_class = 'status-neutral';

if ($attendance_status === 'Present') {

    $status_class = 'status-present';

} elseif ($attendance_status === 'Late') {

    $status_class = 'status-late';

} elseif ($attendance_status === 'Half Day') {

    $status_class = 'status-half';
}

?>


<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Employee Dashboard | BePresent</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>

        :root {
            --bp-navy: #17233F;
            --bp-navy-light: #243454;
            --bp-teal: #13B8A6;
            --bp-teal-dark: #0E9385;
            --bp-coral: #F47770;
            --bp-purple: #8B7CF6;
            --bp-amber: #E9A23B;
            --bp-background: #F4F6FA;
            --bp-white: #FFFFFF;
            --bp-text: #26334D;
            --bp-muted: #8993A7;
            --bp-border: #E8ECF3;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--bp-background);
            font-family: Arial, sans-serif;
            color: var(--bp-text);
        }

        /* NAVBAR */

        .navbar {
            background: linear-gradient(
                110deg,
                var(--bp-navy),
                #243454
            );
            padding: 15px 0;
            box-shadow: 0 4px 15px rgba(23, 35, 63, 0.12);
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            color: white !important;
            font-size: 21px;
            font-weight: 750;
            letter-spacing: -0.5px;
        }

        .brand-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 35px;
            height: 35px;
            background: var(--bp-teal);
            color: var(--bp-navy);
            border-radius: 10px;
            font-weight: 800;
        }

        .logout-button {
            border: 1px solid rgba(255,255,255,0.35);
            color: white;
            border-radius: 8px;
            padding: 7px 14px;
            font-size: 13px;
            transition: 0.2s;
        }

        .logout-button:hover {
            background: var(--bp-coral);
            border-color: var(--bp-coral);
            color: white;
        }

        /* MAIN CONTAINER */

        .dashboard-container {
            max-width: 1050px;
            margin: 38px auto;
            padding-bottom: 30px;
        }

        /* WELCOME SECTION */

        .welcome-card {
            position: relative;
            overflow: hidden;
            background: linear-gradient(
                115deg,
                var(--bp-navy),
                #30476D
            );
            color: white;
            padding: 32px;
            border-radius: 16px;
            margin-bottom: 25px;
            box-shadow: 0 8px 22px rgba(23, 35, 63, 0.12);
        }

        .welcome-card::after {
            content: '';
            position: absolute;
            width: 210px;
            height: 210px;
            border: 35px solid rgba(19,184,166,0.12);
            border-radius: 50%;
            right: -55px;
            top: -75px;
        }

        .welcome-card h3 {
            font-weight: 750;
            font-size: 25px;
            margin-bottom: 9px;
            position: relative;
            z-index: 1;
        }

        .welcome-card p {
            color: #C5D1E3;
            font-size: 13px;
            position: relative;
            z-index: 1;
        }

        .employee-tag {
            display: inline-block;
            margin-top: 7px;
            padding: 6px 11px;
            border-radius: 20px;
            background: rgba(19,184,166,0.16);
            color: #68E3D5;
            font-size: 11px;
            font-weight: 700;
        }

        /* DATE */

        .date-heading {
            display: flex;
            align-items: center;
            gap: 9px;
            color: #68758C;
            font-size: 13px;
            font-weight: 600;
            margin: 22px 3px;
        }

        .date-heading i {
            color: var(--bp-teal-dark);
            font-size: 17px;
        }

        /* CARDS */

        .attendance-card,
        .summary-card {
            background: var(--bp-white);
            border: 1px solid var(--bp-border);
            border-radius: 15px;
            padding: 28px;
            box-shadow: 0 4px 16px rgba(23,35,63,0.04);
            margin-bottom: 24px;
        }

        .card-heading {
            color: var(--bp-navy);
            font-size: 18px;
            font-weight: 750;
            margin-bottom: 5px;
        }

        .card-subheading {
            color: var(--bp-muted);
            font-size: 12px;
        }

        /* TIME DISPLAY */

        .time-box {
            background: #F7F9FC;
            border: 1px solid var(--bp-border);
            border-radius: 13px;
            padding: 22px 15px;
            height: 100%;
            text-align: center;
        }

        .time-label {
            color: var(--bp-muted);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin-bottom: 12px;
        }

        .attendance-time {
            font-size: 27px;
            font-weight: 800;
            color: var(--bp-navy);
        }

        .time-icon {
            font-size: 20px;
            margin-bottom: 9px;
            color: var(--bp-teal-dark);
        }

        /* STATUS */

        .attendance-details {
            background: #F7F9FC;
            border: 1px solid var(--bp-border);
            border-radius: 12px;
            padding: 20px;
            margin-top: 22px;
            text-align: center;
        }

        .status-label {
            color: var(--bp-muted);
            font-size: 12px;
            margin-bottom: 10px;
        }

        .attendance-status {
            display: inline-block;
            padding: 7px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 750;
        }

        .status-present {
            background: #DDF7F3;
            color: #0E9385;
        }

        .status-late {
            background: #FFF1DF;
            color: #B87519;
        }

        .status-half {
            background: #EEEAFE;
            color: #7664D8;
        }

        .status-neutral {
            background: #EDF0F5;
            color: #68758C;
        }

        .working-hours {
            border-top: 1px solid var(--bp-border);
            margin-top: 18px;
            padding-top: 17px;
        }

        .working-hours small {
            display: block;
            color: var(--bp-muted);
            margin-bottom: 5px;
        }

        .working-hours h4 {
            color: var(--bp-navy);
            font-weight: 750;
            margin: 0;
        }

        /* BUTTONS */

        .attendance-button {
            min-width: 175px;
            padding: 13px 22px;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 750;
            border: none;
            transition: all 0.2s ease;
        }

        .time-in-button {
            background: var(--bp-teal);
            color: var(--bp-navy);
        }

        .time-in-button:hover {
            background: var(--bp-teal-dark);
            color: white;
            transform: translateY(-2px);
        }

        .time-out-button {
            background: var(--bp-coral);
            color: white;
        }

        .time-out-button:hover {
            background: #E45F58;
            color: white;
            transform: translateY(-2px);
        }

        .completed-button {
            background: #E8EDF3;
            color: #64748B;
        }

        .action-description {
            color: var(--bp-muted);
            font-size: 13px;
            margin-bottom: 17px;
        }

        .action-success {
            color: var(--bp-teal-dark);
            font-weight: 700;
        }

        /* ALERT */

        .custom-alert {
            border: none;
            border-radius: 11px;
            padding: 15px 18px;
            font-size: 13px;
            box-shadow: 0 3px 10px rgba(23,35,63,0.04);
        }

        /* MODAL */

        .modal-content {
            border: none;
            border-radius: 15px;
            box-shadow: 0 15px 40px rgba(23,35,63,0.15);
        }

        .modal-header {
            background: var(--bp-navy);
            color: white;
            padding: 19px 22px;
            border: none;
        }

        .modal-title {
            font-weight: 750;
            font-size: 16px;
        }

        .modal-header .btn-close {
            filter: invert(1);
        }

        .modal-body {
            padding: 24px;
        }

        .modal-footer {
            border-top: 1px solid var(--bp-border);
            padding: 15px 22px;
        }

        .password-input {
            padding: 12px;
            border-radius: 8px;
            border: 1px solid var(--bp-border);
        }

        .password-input:focus {
            border-color: var(--bp-teal);
            box-shadow: 0 0 0 3px rgba(19,184,166,0.12);
        }

        .confirm-button {
            background: var(--bp-teal);
            border: none;
            color: var(--bp-navy);
            font-weight: 700;
        }

        .confirm-button:hover {
            background: var(--bp-teal-dark);
            color: white;
        }

        /* RESPONSIVE */

        @media (max-width: 768px) {

            .dashboard-container {
                margin: 22px auto;
                padding-left: 12px;
                padding-right: 12px;
            }

            .welcome-card {
                padding: 25px 22px;
            }

            .welcome-card h3 {
                font-size: 21px;
            }

            .attendance-card,
            .summary-card {
                padding: 22px 17px;
            }

            .attendance-time {
                font-size: 23px;
            }

            .time-box {
                padding: 18px 10px;
            }
        }

    </style>

</head>

<body>

<!-- NAVBAR -->

<nav class="navbar navbar-dark">

    <div class="container">

        <a class="navbar-brand" href="dashboard.php">
            <span class="brand-logo">B</span>
            BePresent
        </a>

        <div class="d-flex align-items-center gap-2">

    <a href="history.php" class="btn logout-button">
        <i class="bi bi-clock-history me-1"></i>
        History
    </a>

    <a href="../logout.php" class="btn logout-button">
        <i class="bi bi-box-arrow-right me-1"></i>
        Logout
    </a>
    <a href="calendar.php" class="btn logout-button">
    <i class="bi bi-calendar3 me-1"></i>
    Calendar
</a>

</div>

    </div>

</nav>


<!-- MAIN CONTENT -->

<div class="container dashboard-container">


    <!-- WELCOME -->

    <div class="welcome-card">

        <h3>
            Welcome back, <?= htmlspecialchars($employee['first_name']) ?>!
        </h3>

        <p class="mb-1">
            Here's your attendance overview for today.
        </p>

        <p class="mb-0">
            <?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?>

            <?php if (!empty($employee['position'])): ?>
                · <?= htmlspecialchars($employee['position']) ?>
            <?php endif; ?>
        </p>

        <span class="employee-tag">
            <i class="bi bi-person-badge me-1"></i>
            <?= htmlspecialchars($employee['employee_code']) ?>
        </span>

    </div>


    <!-- DATE -->

    <div class="date-heading">

        <i class="bi bi-calendar3"></i>

        <?= date('l, F d, Y') ?>

        <?php if ($is_sunday): ?>
            <span class="badge rounded-pill"
                style="background:#EEEAFE;color:#7664D8;">
                Rest Day
            </span>
        <?php else: ?>
            <span class="badge rounded-pill"
                style="background:#DDF7F3;color:#0E9385;">
                Working Day
            </span>
        <?php endif; ?>

    </div>


    <!-- ALERT MESSAGE -->

    <?php if ($message): ?>

        <div class="alert <?= $message_type === 'success' ? 'alert-success' : 'alert-danger' ?> custom-alert">

            <i class="bi <?= $message_type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill' ?> me-2"></i>

            <?= htmlspecialchars($message) ?>

        </div>

    <?php endif; ?>


    <!-- ATTENDANCE CARD -->

    <div class="attendance-card">

        <div class="mb-4">

            <h4 class="card-heading">
                Today's Attendance
            </h4>

            <p class="card-subheading mb-0">
                Your recorded time-in and time-out information.
            </p>

        </div>


        <!-- TIME IN AND TIME OUT -->

        <div class="row g-3">

            <div class="col-md-6">

                <div class="time-box">

                    <div class="time-icon">
                        <i class="bi bi-box-arrow-in-right"></i>
                    </div>

                    <p class="time-label">
                        Time-In
                    </p>

                    <div class="attendance-time">
                        <?= $time_in
                            ? date('h:i A', strtotime($time_in))
                            : '--:--' ?>
                    </div>

                </div>

            </div>


            <div class="col-md-6">

                <div class="time-box">

                    <div class="time-icon" style="color:var(--bp-coral);">
                        <i class="bi bi-box-arrow-right"></i>
                    </div>

                    <p class="time-label">
                        Time-Out
                    </p>

                    <div class="attendance-time">
                        <?= $time_out
                            ? date('h:i A', strtotime($time_out))
                            : '--:--' ?>
                    </div>

                </div>

            </div>

        </div>


        <!-- ATTENDANCE STATUS -->

        <?php if ($attendance): ?>

            <div class="attendance-details">

                <p class="status-label">
                    Attendance Status
                </p>

                <span class="attendance-status <?= $status_class ?>">
                    <i class="bi bi-circle-fill me-1" style="font-size:7px;"></i>
                    <?= htmlspecialchars($attendance_status) ?>
                </span>

                <?php if ($working_hours !== null): ?>

                    <div class="working-hours">

                        <small>
                            <i class="bi bi-clock me-1"></i>
                            Total Working Hours
                        </small>

                        <h4>
                            <?= htmlspecialchars($working_hours) ?>
                        </h4>

                    </div>

                <?php endif; ?>

            </div>

        <?php endif; ?>


        <!-- ATTENDANCE ACTION -->

        <div class="text-center mt-4">

            <?php if ($is_sunday && !$attendance): ?>

                <p class="action-description">
                    Today is Sunday. Enjoy your scheduled rest day!
                </p>

                <button class="btn attendance-button completed-button" disabled>
                    <i class="bi bi-calendar-heart me-1"></i>
                    Rest Day
                </button>


            <?php elseif (!$attendance): ?>

                <p class="action-description">
                    You haven't recorded your attendance today.
                </p>

                <button
                    type="button"
                    class="btn attendance-button time-in-button"
                    data-bs-toggle="modal"
                    data-bs-target="#attendanceModal"
                    data-action="time_in"
                    data-title="Confirm Time-In"
                    data-button="Confirm Time-In"
                >
                    <i class="bi bi-box-arrow-in-right me-2"></i>
                    Time In
                </button>


            <?php elseif ($time_in && !$time_out): ?>

                <p class="action-description action-success">
                    <i class="bi bi-check-circle-fill me-1"></i>
                    You are currently checked in.
                </p>

                <button
                    type="button"
                    class="btn attendance-button time-out-button"
                    data-bs-toggle="modal"
                    data-bs-target="#attendanceModal"
                    data-action="time_out"
                    data-title="Confirm Time-Out"
                    data-button="Confirm Time-Out"
                >
                    <i class="bi bi-box-arrow-right me-2"></i>
                    Time Out
                </button>


            <?php else: ?>

                <p class="action-description action-success">
                    <i class="bi bi-check-circle-fill me-1"></i>
                    Attendance completed for today!
                </p>

                <button class="btn attendance-button completed-button" disabled>
                    <i class="bi bi-check2-all me-1"></i>
                    Completed
                </button>

            <?php endif; ?>

        </div>

    </div>

</div>


<!-- PASSWORD CONFIRMATION MODAL -->

<div class="modal fade" id="attendanceModal" tabindex="-1" aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title" id="modalTitle">
                    Confirm Attendance
                </h5>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Close">
                </button>

            </div>


            <form method="POST">

                <div class="modal-body">

                    <p class="text-muted small mb-4">
                        Please enter your account password to confirm this attendance action.
                    </p>

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        id="attendanceAction"
                        value=""
                    >

                    <label for="password" class="form-label fw-semibold">
                        Account Password
                    </label>

                    <input
                        type="password"
                        name="password"
                        id="password"
                        class="form-control password-input"
                        placeholder="Enter your password"
                        required
                        autocomplete="current-password"
                    >

                </div>


                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-light"
                        data-bs-dismiss="modal">
                        Cancel
                    </button>

                    <button
                        type="submit"
                        class="btn confirm-button"
                        id="confirmButton">
                        Confirm
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<!-- JAVASCRIPT -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>

    const attendanceModal = document.getElementById('attendanceModal');

    attendanceModal.addEventListener('show.bs.modal', function (event) {

        const button = event.relatedTarget;

        const action = button.getAttribute('data-action');
        const title = button.getAttribute('data-title');
        const buttonText = button.getAttribute('data-button');

        document.getElementById('attendanceAction').value = action;
        document.getElementById('modalTitle').textContent = title;
        document.getElementById('confirmButton').textContent = buttonText;

        document.getElementById('password').value = '';

    });

    attendanceModal.addEventListener('shown.bs.modal', function () {

        document.getElementById('password').focus();

    });

</script>

</body>
</html>
