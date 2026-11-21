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

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

$errors = [];

// Default form values
$employeeId = '';
$attendanceDate = date('Y-m-d');
$timeIn = '';
$timeOut = '';
$status = 'Present';
$remarks = '';
$correctionReason = '';

// Fetch employees for the dropdown
$employeeStmt = $conn->query("
    SELECT
        id,
        employee_code,
        first_name,
        last_name,
        employment_status
    FROM employees
    ORDER BY first_name ASC, last_name ASC
");

$employees = $employeeStmt->fetchAll();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Verify CSRF token
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($submittedToken) ||
        !hash_equals($csrfToken, $submittedToken)
    ) {
        $errors[] = "Invalid form request. Please refresh the page and try again.";
    }

    // Get submitted values safely
    $employeeId = $_POST['employee_id'] ?? '';
    $attendanceDate = $_POST['attendance_date'] ?? '';
    $timeIn = $_POST['time_in'] ?? '';
    $timeOut = $_POST['time_out'] ?? '';
    $status = $_POST['status'] ?? '';
    $remarks = $_POST['remarks'] ?? '';
    $correctionReason = $_POST['correction_reason'] ?? '';

    $fields = [
        'employeeId',
        'attendanceDate',
        'timeIn',
        'timeOut',
        'status',
        'remarks',
        'correctionReason'
    ];

    foreach ($fields as $field) {
        if (!is_string($$field)) {
            $$field = '';
        }
    }

    $employeeId = trim($employeeId);
    $attendanceDate = trim($attendanceDate);
    $timeIn = trim($timeIn);
    $timeOut = trim($timeOut);
    $remarks = trim($remarks);
    $correctionReason = trim($correctionReason);

    // Validate employee ID
    $employeeIdValue = filter_var(
        $employeeId,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($employeeIdValue === false) {
        $errors[] = "Please select a valid employee.";
    }

    // Validate attendance date
    $dateObject = DateTime::createFromFormat(
        '!Y-m-d',
        $attendanceDate
    );

    if (
        !$dateObject ||
        $dateObject->format('Y-m-d') !== $attendanceDate
    ) {
        $errors[] = "Please enter a valid attendance date.";
    } else {

        // Do not allow future attendance dates
        if ($attendanceDate > date('Y-m-d')) {
            $errors[] = "Attendance date cannot be in the future.";
        }

        // Sunday is the system's designated rest day
        if ($dateObject->format('w') === '0') {
            $errors[] = "Sunday is a rest day. Manual attendance cannot be added for Sundays.";
        }
    }

    // Validate Time-In
    if ($timeIn === '') {
        $errors[] = "Time-In is required.";
    } else {

        $timeInObject = DateTime::createFromFormat(
            '!H:i',
            $timeIn
        );

        if (
            !$timeInObject ||
            $timeInObject->format('H:i') !== $timeIn
        ) {
            $errors[] = "Please enter a valid Time-In.";
        }
    }

    // Validate Time-Out
    if ($timeOut !== '') {

        $timeOutObject = DateTime::createFromFormat(
            '!H:i',
            $timeOut
        );

        if (
            !$timeOutObject ||
            $timeOutObject->format('H:i') !== $timeOut
        ) {
            $errors[] = "Please enter a valid Time-Out.";
        }
    }

    // Validate status
    $allowedStatuses = ['Present', 'Late', 'Half Day'];

    if (!in_array($status, $allowedStatuses, true)) {
        $errors[] = "Please select a valid attendance status.";
    }

    // Validate correction reason
    if ($correctionReason === '') {
        $errors[] = "Please provide a reason for manually adding this record.";
    } elseif (strlen($correctionReason) > 255) {
        $errors[] = "The reason must not exceed 255 characters.";
    }

    // Validate remarks
    if (strlen($remarks) > 255) {
        $errors[] = "Remarks must not exceed 255 characters.";
    }

    // Convert time values
    $newTimeIn = $timeIn !== ''
        ? $timeIn . ':00'
        : null;

    $newTimeOut = $timeOut !== ''
        ? $timeOut . ':00'
        : null;

    // Check Time-In and Time-Out relationship
    if ($newTimeOut !== null && $newTimeIn === null) {
        $errors[] = "Time-In is required when a Time-Out is provided.";
    } elseif (
        $newTimeIn !== null &&
        $newTimeOut !== null &&
        $newTimeOut < $newTimeIn
    ) {
        $errors[] = "Time-Out cannot be earlier than Time-In.";
    }

    // Verify employee exists
    if ($employeeIdValue !== false) {

        $checkEmployee = $conn->prepare("
            SELECT id
            FROM employees
            WHERE id = :id
            LIMIT 1
        ");

        $checkEmployee->execute([
            'id' => $employeeIdValue
        ]);

        if (!$checkEmployee->fetch()) {
            $errors[] = "The selected employee does not exist.";
        }
    }

    // Save attendance and audit log
    if (empty($errors)) {

        try {

            $conn->beginTransaction();

            // Insert attendance record
            $insertStmt = $conn->prepare("
                INSERT INTO attendance (
                    employee_id,
                    attendance_date,
                    time_in,
                    time_out,
                    status,
                    remarks
                ) VALUES (
                    :employee_id,
                    :attendance_date,
                    :time_in,
                    :time_out,
                    :status,
                    :remarks
                )
            ");

            $insertStmt->execute([
                'employee_id' => $employeeIdValue,
                'attendance_date' => $attendanceDate,
                'time_in' => $newTimeIn,
                'time_out' => $newTimeOut,
                'status' => $status,
                'remarks' => $remarks !== '' ? $remarks : null
            ]);

            $attendanceId = (int) $conn->lastInsertId();

            // Record the manual creation in attendance history
            $logStmt = $conn->prepare("
                INSERT INTO attendance_logs (
                    attendance_id,
                    admin_user_id,
                    old_time_in,
                    new_time_in,
                    old_time_out,
                    new_time_out,
                    old_status,
                    new_status,
                    correction_reason
                ) VALUES (
                    :attendance_id,
                    :admin_user_id,
                    NULL,
                    :new_time_in,
                    NULL,
                    :new_time_out,
                    NULL,
                    :new_status,
                    :correction_reason
                )
            ");

            $logStmt->execute([
                'attendance_id' => $attendanceId,
                'admin_user_id' => $_SESSION['user_id'],
                'new_time_in' => $newTimeIn,
                'new_time_out' => $newTimeOut,
                'new_status' => $status,
                'correction_reason' => $correctionReason
            ]);

            $conn->commit();

            header("Location: index.php?date=" . urlencode($attendanceDate) . "&created=1");
            exit;

        } catch (PDOException $e) {

            if ($conn->inTransaction()) {
                $conn->rollBack();
            }

            error_log($e->getMessage());

            if ($e->getCode() === '23000') {
                $errors[] = "An attendance record already exists for this employee on the selected date.";
            } else {
                $errors[] = "Unable to create the attendance record. Please try again.";
            }

        } catch (Throwable $e) {

            if ($conn->inTransaction()) {
                $conn->rollBack();
            }

            error_log($e->getMessage());

            $errors[] = "Something went wrong. Please try again.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Add Manual Attendance | BePresent</title>

    <link rel="stylesheet" href="../../assets/css/dashboard.css">

    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>

        .edit-container {
            max-width: 850px;
            margin: 25px auto;
        }

        .edit-card {
            background: white;
            padding: 28px;
            border-radius: 10px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.04);
        }

        .employee-details {
            background: #f7f8fa;
            padding: 18px;
            border-radius: 8px;
            margin-bottom: 25px;
        }

        .employee-details h3 {
            color: #243b64;
            font-size: 17px;
            margin: 0 0 8px;
        }

        .employee-details p {
            color: #777;
            font-size: 13px;
            margin: 5px 0;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 20px;
        }

        .form-group label {
            color: #444;
            font-size: 13px;
            font-weight: 600;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #dddddd;
            border-radius: 7px;
            font-size: 13px;
            font-family: inherit;
            box-sizing: border-box;
        }

        .form-group textarea {
            min-height: 90px;
            resize: vertical;
        }

        .form-help {
            color: #888;
            font-size: 11px;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 25px;
            flex-wrap: wrap;
        }

        .save-button,
        .cancel-button {
            display: inline-block;
            padding: 12px 20px;
            border-radius: 7px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }

        .save-button {
            background: #243b64;
            color: white;
            border: none;
        }

        .save-button:hover {
            background: #192c4d;
        }

        .cancel-button {
            background: #eeeeee;
            color: #444;
        }

        .message {
            padding: 13px 16px;
            border-radius: 7px;
            margin-bottom: 20px;
            font-size: 13px;
        }

        .message-error {
            background: #fdecec;
            color: #a12b2b;
        }

        .required {
            color: #d93025;
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
                <h1>Add Manual Attendance</h1>

                <p>
                    Create an attendance record on behalf of an employee.
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

        <div class="edit-container">

            <section class="edit-card">

                <?php if (!empty($errors)): ?>

                    <div class="message message-error">

                        <strong>Please check the following:</strong>

                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>

                    </div>

                <?php endif; ?>

                <div class="employee-details">

                    <h3>
                        <i class="bi bi-info-circle"></i>
                        Manual Attendance
                    </h3>

                    <p>
                        Use this form when an employee cannot record their attendance through the system.
                    </p>

                    <p>
                        A reason is required, and the action will be saved in Correction History.
                    </p>

                </div>

                <form method="POST">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars($csrfToken) ?>"
                    >

                    <div class="form-group">

                        <label for="employee_id">
                            Employee <span class="required">*</span>
                        </label>

                        <select name="employee_id" id="employee_id" required>

                            <option value="">Select Employee</option>

                            <?php foreach ($employees as $employee): ?>

                                <option
                                    value="<?= (int) $employee['id'] ?>"
                                    <?= (string) $employeeId === (string) $employee['id'] ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars(
                                        $employee['employee_code'] . ' - ' .
                                        $employee['first_name'] . ' ' .
                                        $employee['last_name'] . ' (' .
                                        $employee['employment_status'] . ')'
                                    ) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="form-group">

                        <label for="attendance_date">
                            Attendance Date <span class="required">*</span>
                        </label>

                        <input
                            type="date"
                            id="attendance_date"
                            name="attendance_date"
                            value="<?= htmlspecialchars($attendanceDate) ?>"
                            max="<?= date('Y-m-d') ?>"
                            required
                        >

                        <span class="form-help">
                            Future dates and Sundays are not allowed.
                        </span>

                    </div>

                    <div class="form-group">

                        <label for="time_in">
                            Time-In <span class="required">*</span>
                        </label>

                        <input
                            type="time"
                            id="time_in"
                            name="time_in"
                            value="<?= htmlspecialchars($timeIn) ?>"
                            required
                        >

                    </div>

                    <div class="form-group">

                        <label for="time_out">Time-Out</label>

                        <input
                            type="time"
                            id="time_out"
                            name="time_out"
                            value="<?= htmlspecialchars($timeOut) ?>"
                        >

                        <span class="form-help">
                            Leave blank if the employee has not yet completed Time-Out.
                        </span>

                    </div>

                    <div class="form-group">

                        <label for="status">
                            Attendance Status <span class="required">*</span>
                        </label>

                        <select name="status" id="status" required>

                            <option value="Present"
                                <?= $status === 'Present' ? 'selected' : '' ?>>
                                Present
                            </option>

                            <option value="Late"
                                <?= $status === 'Late' ? 'selected' : '' ?>>
                                Late
                            </option>

                            <option value="Half Day"
                                <?= $status === 'Half Day' ? 'selected' : '' ?>>
                                Half Day
                            </option>

                        </select>

                    </div>

                    <div class="form-group">

                        <label for="remarks">Remarks</label>

                        <textarea
                            id="remarks"
                            name="remarks"
                            maxlength="255"
                            placeholder="Enter additional attendance remarks..."
                        ><?= htmlspecialchars($remarks) ?></textarea>

                    </div>

                    <div class="form-group">

                        <label for="correction_reason">
                            Reason for Manual Attendance
                            <span class="required">*</span>
                        </label>

                        <textarea
                            id="correction_reason"
                            name="correction_reason"
                            maxlength="255"
                            required
                            placeholder="Example: Employee forgot to Time-In. Attendance verified by the supervisor."
                        ><?= htmlspecialchars($correctionReason) ?></textarea>

                        <span class="form-help">
                            This reason will be recorded in the attendance correction history.
                        </span>

                    </div>

                    <div class="form-actions">

                        <button type="submit" class="save-button">
                            <i class="bi bi-plus-circle"></i>
                            Create Attendance
                        </button>

                        <a href="index.php" class="cancel-button">
                            Cancel
                        </a>

                    </div>

                </form>

            </section>

        </div>

    </main>

</body>
</html>