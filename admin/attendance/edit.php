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

// Validate attendance ID
$attendanceId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$attendanceId || $attendanceId < 1) {
    header("Location: index.php");
    exit;
}

// Create CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

$errors = [];

// Fetch attendance record
$stmt = $conn->prepare("
    SELECT
        attendance.*,
        employees.employee_code,
        employees.first_name,
        employees.last_name,
        employees.position
    FROM attendance
    INNER JOIN employees
        ON attendance.employee_id = employees.id
    WHERE attendance.id = :id
    LIMIT 1
");

$stmt->execute([
    'id' => $attendanceId
]);

$attendance = $stmt->fetch();

if (!$attendance) {
    header("Location: index.php");
    exit;
}

// Default form values
$timeIn = $attendance['time_in']
    ? substr($attendance['time_in'], 0, 5)
    : '';

$timeOut = $attendance['time_out']
    ? substr($attendance['time_out'], 0, 5)
    : '';

$status = $attendance['status'];
$remarks = $attendance['remarks'] ?? '';
$correctionReason = '';

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
$timeIn = $_POST['time_in'] ?? '';
$timeOut = $_POST['time_out'] ?? '';
$status = $_POST['status'] ?? '';
$remarks = $_POST['remarks'] ?? '';
$correctionReason = $_POST['correction_reason'] ?? '';

// Ensure text inputs contain strings
if (!is_string($timeIn)) {
    $timeIn = '';
}

if (!is_string($timeOut)) {
    $timeOut = '';
}

if (!is_string($status)) {
    $status = '';
}

if (!is_string($remarks)) {
    $remarks = '';
}

if (!is_string($correctionReason)) {
    $correctionReason = '';
}

$timeIn = trim($timeIn);
$timeOut = trim($timeOut);
$remarks = trim($remarks);
$correctionReason = trim($correctionReason);


    // Validate status
    $allowedStatuses = ['Present', 'Late', 'Half Day'];

    if (!in_array($status, $allowedStatuses, true)) {
        $errors[] = "Please select a valid attendance status.";
    }

    // Validate Time-In
    if ($timeIn !== '') {

        $timeInObject = DateTime::createFromFormat('!H:i', $timeIn);

        if (
            !$timeInObject ||
            $timeInObject->format('H:i') !== $timeIn
        ) {
            $errors[] = "Please enter a valid Time-In.";
        }
    }

    // Validate Time-Out
    if ($timeOut !== '') {

        $timeOutObject = DateTime::createFromFormat('!H:i', $timeOut);

        if (
            !$timeOutObject ||
            $timeOutObject->format('H:i') !== $timeOut
        ) {
            $errors[] = "Please enter a valid Time-Out.";
        }
    }

    // Validate correction reason
    if ($correctionReason === '') {
        $errors[] = "Please provide a reason for correcting this attendance record.";
    } elseif (strlen($correctionReason) > 255) {
        $errors[] = "The correction reason must not exceed 255 characters.";
    }

    // Validate remarks length
    if (strlen($remarks) > 255) {
        $errors[] = "Remarks must not exceed 255 characters.";
    }

    // Convert empty time values to NULL
    $newTimeIn = $timeIn !== ''
        ? $timeIn . ':00'
        : null;

    $newTimeOut = $timeOut !== ''
        ? $timeOut . ':00'
        : null;

    // Check that Time-Out is not earlier than Time-In
    
// Validate Time-In and Time-Out relationship
if ($newTimeOut !== null && $newTimeIn === null) {
    $errors[] = "Time-In is required when a Time-Out is provided.";
}

if ($newTimeIn !== null && $newTimeOut !== null) {

    if ($newTimeOut < $newTimeIn) {
        $errors[] = "Time-Out cannot be earlier than Time-In.";
    }
}


        if ($newTimeOut < $newTimeIn) {
            $errors[] = "Time-Out cannot be earlier than Time-In.";
        }
    }

    // Save changes
    if (empty($errors)) {

        try {

            $conn->beginTransaction();

            // Fetch the latest values before changing the record
            $oldStmt = $conn->prepare("
                SELECT time_in, time_out, status
                FROM attendance
                WHERE id = :id
                FOR UPDATE
            ");

            $oldStmt->execute([
                'id' => $attendanceId
            ]);

            $oldRecord = $oldStmt->fetch();

            if (!$oldRecord) {
                throw new Exception("Attendance record no longer exists.");
            }

            // Update attendance record
            $updateStmt = $conn->prepare("
                UPDATE attendance
                SET
                    time_in = :time_in,
                    time_out = :time_out,
                    status = :status,
                    remarks = :remarks
                WHERE id = :id
            ");

            $updateStmt->execute([
                'time_in' => $newTimeIn,
                'time_out' => $newTimeOut,
                'status' => $status,
                'remarks' => $remarks !== '' ? $remarks : null,
                'id' => $attendanceId
            ]);

            // Save correction history
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
                    :old_time_in,
                    :new_time_in,
                    :old_time_out,
                    :new_time_out,
                    :old_status,
                    :new_status,
                    :correction_reason
                )
            ");

            $logStmt->execute([
                'attendance_id' => $attendanceId,
                'admin_user_id' => $_SESSION['user_id'],

                'old_time_in' => $oldRecord['time_in'],
                'new_time_in' => $newTimeIn,

                'old_time_out' => $oldRecord['time_out'],
                'new_time_out' => $newTimeOut,

                'old_status' => $oldRecord['status'],
                'new_status' => $status,

                'correction_reason' => $correctionReason
            ]);

            $conn->commit();

            header("Location: edit.php?id=" . $attendanceId . "&success=1");
            exit;

        } catch (Throwable $e) {

            if ($conn->inTransaction()) {
                $conn->rollBack();
            }

            error_log($e->getMessage());

            $errors[] = "Unable to save the attendance correction. Please try again.";
        }
    }
}

// Show success message
$success = isset($_GET['success']) && $_GET['success'] === '1';

// Refresh attendance information after a successful update
if ($success) {

    $stmt = $conn->prepare("
        SELECT
            attendance.*,
            employees.employee_code,
            employees.first_name,
            employees.last_name,
            employees.position
        FROM attendance
        INNER JOIN employees
            ON attendance.employee_id = employees.id
        WHERE attendance.id = :id
        LIMIT 1
    ");

    $stmt->execute([
        'id' => $attendanceId
    ]);

    $attendance = $stmt->fetch();

    $timeIn = $attendance['time_in']
        ? substr($attendance['time_in'], 0, 5)
        : '';

    $timeOut = $attendance['time_out']
        ? substr($attendance['time_out'], 0, 5)
        : '';

    $status = $attendance['status'];
    $remarks = $attendance['remarks'] ?? '';
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Attendance | BePresent</title>

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

        .message-success {
            background: #e8f7ed;
            color: #216b3a;
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

        <!-- TOPBAR -->

        <header class="topbar">

            <div>
                <h1>Edit Attendance</h1>

                <p>
                    Correct an employee's attendance record.
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


        <!-- EDIT FORM -->

        <div class="edit-container">

            <section class="edit-card">

                <?php if ($success): ?>

                    <div class="message message-success">
                        <i class="bi bi-check-circle-fill"></i>
                        Attendance record successfully corrected.
                    </div>

                <?php endif; ?>


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


                <!-- EMPLOYEE INFORMATION -->

                <div class="employee-details">

                    <h3>
                        <?= htmlspecialchars(
                            $attendance['first_name'] . ' ' .
                            $attendance['last_name']
                        ) ?>
                    </h3>

                    <p>
                        Employee ID:
                        <?= htmlspecialchars($attendance['employee_code']) ?>
                    </p>

                    <p>
                        Position:
                        <?= htmlspecialchars($attendance['position'] ?? 'N/A') ?>
                    </p>

                    <p>
                        Attendance Date:
                        <?= date(
                            'F d, Y',
                            strtotime($attendance['attendance_date'])
                        ) ?>
                    </p>

                </div>


                <!-- FORM -->

                <form method="POST">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars($csrfToken) ?>"
                    >


                    <div class="form-group">

                        <label for="time_in">Time-In</label>

                        <input
                            type="time"
                            id="time_in"
                            name="time_in"
                            value="<?= htmlspecialchars($timeIn) ?>"
                        >

                        <span class="form-help">
                            Leave blank if the employee has no Time-In record.
                        </span>

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
                            Leave blank if the employee has no Time-Out record.
                        </span>

                    </div>


                    <div class="form-group">

                        <label for="status">Attendance Status</label>

                        <select name="status" id="status" required>

                            <option
                                value="Present"
                                <?= $status === 'Present' ? 'selected' : '' ?>
                            >
                                Present
                            </option>

                            <option
                                value="Late"
                                <?= $status === 'Late' ? 'selected' : '' ?>
                            >
                                Late
                            </option>

                            <option
                                value="Half Day"
                                <?= $status === 'Half Day' ? 'selected' : '' ?>
                            >
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
                            placeholder="Enter attendance remarks..."
                        ><?= htmlspecialchars($remarks) ?></textarea>

                    </div>


                    <div class="form-group">

                        <label for="correction_reason">
                            Reason for Correction
                            <span class="required">*</span>
                        </label>

                        <textarea
                            id="correction_reason"
                            name="correction_reason"
                            maxlength="255"
                            required
                            placeholder="Explain why this attendance record needs to be corrected..."
                        ><?= htmlspecialchars($correctionReason) ?></textarea>

                        <span class="form-help">
                            This reason will be saved in the attendance correction history.
                        </span>

                    </div>


                    <div class="form-actions">

                        <button type="submit" class="save-button">
                            <i class="bi bi-check-circle"></i>
                            Save Changes
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