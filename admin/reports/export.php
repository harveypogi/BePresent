
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

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$search = trim($_GET['search'] ?? '');

function validDate($date) {
    $dateObject = DateTime::createFromFormat('Y-m-d', $date);

    return $dateObject &&
        $dateObject->format('Y-m-d') === $date;
}

if (
    !validDate($startDate) ||
    !validDate($endDate) ||
    $startDate > $endDate
) {
    die("Invalid date range.");
}

$sql = "
    SELECT
        employees.employee_code,
        employees.first_name,
        employees.last_name,
        employees.position,
        attendance.attendance_date,
        attendance.time_in,
        attendance.time_out,
        attendance.status,
        attendance.remarks
    FROM attendance
    INNER JOIN employees
        ON attendance.employee_id = employees.id
    WHERE attendance.attendance_date
        BETWEEN :start_date AND :end_date
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

// Prepare CSV download
$filename = "BePresent_Attendance_" .
    $startDate . "_to_" . $endDate . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// UTF-8 BOM for better Excel compatibility
fwrite($output, "\xEF\xBB\xBF");

// CSV headings
fputcsv($output, [
    'Employee ID',
    'First Name',
    'Last Name',
    'Position',
    'Attendance Date',
    'Time In',
    'Time Out',
    'Status',
    'Remarks'
]);

// CSV records
foreach ($records as $record) {

    fputcsv($output, [
        $record['employee_code'],
        $record['first_name'],
        $record['last_name'],
        $record['position'] ?? '',
        $record['attendance_date'],
        $record['time_in'] ?? '',
        $record['time_out'] ?? '',
        $record['status'],
        $record['remarks'] ?? ''
    ]);
}

fclose($output);
exit;
