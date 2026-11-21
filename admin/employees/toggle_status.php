
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

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

// Verify CSRF token
if (
    !isset($_POST['csrf_token']) ||
    !isset($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    die("Invalid request. Please go back and try again.");
}

$employee_id = filter_input(INPUT_POST, 'employee_id', FILTER_VALIDATE_INT);
$new_status = $_POST['new_status'] ?? '';

if (!$employee_id || !in_array($new_status, ['Active', 'Inactive'], true)) {
    header("Location: index.php");
    exit;
}

try {

    $conn->beginTransaction();

    // Find employee and linked user account
    $stmt = $conn->prepare("
        SELECT user_id
        FROM employees
        WHERE id = :id
        FOR UPDATE
    ");

    $stmt->execute(['id' => $employee_id]);
    $employee = $stmt->fetch();

    if (!$employee) {
        $conn->rollBack();
        header("Location: index.php");
        exit;
    }

    // Update employee status
    $updateEmployee = $conn->prepare("
        UPDATE employees
        SET employment_status = :status
        WHERE id = :id
    ");

    $updateEmployee->execute([
        'status' => $new_status,
        'id' => $employee_id
    ]);

    // Update linked login account
    if (!empty($employee['user_id'])) {

        $updateUser = $conn->prepare("
            UPDATE users
            SET is_active = :is_active
            WHERE id = :user_id
        ");

        $updateUser->execute([
            'is_active' => $new_status === 'Active' ? 1 : 0,
            'user_id' => $employee['user_id']
        ]);
    }

    $conn->commit();

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    $message = $new_status === 'Inactive'
        ? 'deactivated'
        : 'activated';

    header("Location: index.php?success=" . $message);
    exit;

} catch (PDOException $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log($e->getMessage());

    die("Unable to update employee status. Please try again.");
}
