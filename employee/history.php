
<?php

session_start();

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'employee'
) {
    header("Location: ../login.php");
    exit;
}

require_once '../connection/connect.php';

date_default_timezone_set('Asia/Manila');

$user_id = $_SESSION['user_id'];

// Get employee information
$stmt = $conn->prepare("
    SELECT
        e.id,
        e.first_name,
        e.last_name,
        e.employee_code,
        e.position
    FROM employees e
    INNER JOIN users u ON e.user_id = u.id
    WHERE e.user_id = ?
    AND u.is_active = 1
");

$stmt->execute([$user_id]);
$employee = $stmt->fetch();

if (!$employee) {
    die("Employee profile not found or account is inactive.");
}

$employee_id = $employee['id'];

// Filters
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$status = $_GET['status'] ?? '';

$errors = [];

if ($start_date !== '') {
    $date = DateTime::createFromFormat('!Y-m-d', $start_date);

    if (!$date || $date->format('Y-m-d') !== $start_date) {
        $errors[] = "Invalid start date.";
        $start_date = '';
    }
}

if ($end_date !== '') {
    $date = DateTime::createFromFormat('!Y-m-d', $end_date);

    if (!$date || $date->format('Y-m-d') !== $end_date) {
        $errors[] = "Invalid end date.";
        $end_date = '';
    }
}

if ($start_date && $end_date && $start_date > $end_date) {
    $errors[] = "Start date cannot be later than end date.";
    $start_date = '';
    $end_date = '';
}

$allowed_statuses = ['Present', 'Late', 'Half Day'];

if ($status !== '' && !in_array($status, $allowed_statuses, true)) {
    $errors[] = "Invalid attendance status.";
    $status = '';
}

// Build query filters
$where = ["employee_id = :employee_id"];
$params = ['employee_id' => $employee_id];

if ($start_date !== '') {
    $where[] = "attendance_date >= :start_date";
    $params['start_date'] = $start_date;
}

if ($end_date !== '') {
    $where[] = "attendance_date <= :end_date";
    $params['end_date'] = $end_date;
}

if ($status !== '') {
    $where[] = "status = :status";
    $params['status'] = $status;
}

$where_sql = implode(' AND ', $where);

// Pagination
$records_per_page = 10;
$page = max(1, (int) ($_GET['page'] ?? 1));

$count_stmt = $conn->prepare("
    SELECT COUNT(*)
    FROM attendance
    WHERE $where_sql
");

$count_stmt->execute($params);
$total_records = (int) $count_stmt->fetchColumn();

$total_pages = max(1, (int) ceil($total_records / $records_per_page));

$page = min($page, $total_pages);
$offset = ($page - 1) * $records_per_page;

// Get attendance records
$stmt = $conn->prepare("
    SELECT
        attendance_date,
        time_in,
        time_out,
        status,
        remarks
    FROM attendance
    WHERE $where_sql
    ORDER BY attendance_date DESC
    LIMIT $records_per_page OFFSET $offset
");

$stmt->execute($params);
$records = $stmt->fetchAll();

// All-time attendance summary
$stmt = $conn->prepare("
    SELECT
        COUNT(*) AS total_days,
        SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) AS present_days,
        SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) AS late_days,
        SUM(CASE WHEN status = 'Half Day' THEN 1 ELSE 0 END) AS half_days
    FROM attendance
    WHERE employee_id = ?
");

$stmt->execute([$employee_id]);
$summary = $stmt->fetch();

$total_days = (int) ($summary['total_days'] ?? 0);
$present_days = (int) ($summary['present_days'] ?? 0);
$late_days = (int) ($summary['late_days'] ?? 0);
$half_days = (int) ($summary['half_days'] ?? 0);

// Pagination URL
function historyPageUrl($pageNumber)
{
    $params = $_GET;
    $params['page'] = $pageNumber;

    return '?' . http_build_query($params);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Attendance History | BePresent</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>

        :root {
            --navy: #17233F;
            --teal: #13B8A6;
            --teal-dark: #0E9385;
            --coral: #F47770;
            --purple: #8B7CF6;
            --amber: #E9A23B;
            --background: #F4F6FA;
            --text: #26334D;
            --muted: #8993A7;
            --border: #E8ECF3;
        }

        body {
            background: var(--background);
            color: var(--text);
            font-family: Arial, sans-serif;
        }

        .navbar {
            background: linear-gradient(110deg, var(--navy), #243454);
            padding: 15px 0;
            box-shadow: 0 4px 15px rgba(23,35,63,0.12);
        }

        .navbar-brand {
            color: white !important;
            font-size: 21px;
            font-weight: 750;
        }

        .brand-logo {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 35px;
            height: 35px;
            margin-right: 8px;
            background: var(--teal);
            color: var(--navy);
            border-radius: 10px;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-links a {
            color: #D5DEEB;
            text-decoration: none;
            font-size: 13px;
            padding: 8px 12px;
            border-radius: 7px;
        }

        .nav-links a.active,
        .nav-links a:hover {
            background: rgba(19,184,166,0.16);
            color: #68E3D5;
        }

        .logout-button {
            border: 1px solid rgba(255,255,255,0.35);
            color: white;
            border-radius: 8px;
            padding: 7px 12px;
            font-size: 12px;
            text-decoration: none;
        }

        .logout-button:hover {
            background: var(--coral);
            color: white;
        }

        .page-container {
            max-width: 1100px;
            margin: 35px auto;
            padding: 0 12px 30px;
        }

        .page-heading {
            margin-bottom: 25px;
        }

        .page-heading h2 {
            color: var(--navy);
            font-weight: 750;
            font-size: 25px;
        }

        .page-heading p {
            color: var(--muted);
            font-size: 13px;
        }

        .summary-card,
        .content-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 14px;
            box-shadow: 0 4px 16px rgba(23,35,63,0.04);
        }

        .summary-card {
            padding: 20px;
            height: 100%;
        }

        .summary-card p {
            color: var(--muted);
            font-size: 12px;
            margin-bottom: 10px;
        }

        .summary-card h3 {
            color: var(--navy);
            font-size: 27px;
            font-weight: 750;
            margin: 0;
        }

        .summary-icon {
            font-size: 20px;
            margin-bottom: 10px;
        }

        .content-card {
            margin-top: 25px;
            overflow: hidden;
        }

        .content-header {
            padding: 21px 24px;
            border-bottom: 1px solid var(--border);
        }

        .content-header h5 {
            color: var(--navy);
            font-weight: 750;
            margin: 0;
        }

        .content-header p {
            color: var(--muted);
            font-size: 12px;
            margin: 5px 0 0;
        }

        .filter-area {
            padding: 22px 24px;
            background: #FAFBFD;
            border-bottom: 1px solid var(--border);
        }

        .form-label {
            color: #68758C;
            font-size: 12px;
            font-weight: 700;
        }

        .form-control,
        .form-select {
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 10px;
            font-size: 13px;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--teal);
            box-shadow: 0 0 0 3px rgba(19,184,166,0.12);
        }

        .filter-button {
            background: var(--teal);
            color: var(--navy);
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            padding: 10px 18px;
        }

        .filter-button:hover {
            background: var(--teal-dark);
            color: white;
        }

        .reset-button {
            color: #68758C;
            border: 1px solid var(--border);
            background: white;
            border-radius: 8px;
            font-size: 13px;
            padding: 10px 18px;
            text-decoration: none;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 700px;
        }

        thead {
            background: #F7F9FC;
        }

        th {
            padding: 15px 20px;
            color: #77839A;
            font-size: 10px;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        td {
            padding: 16px 20px;
            border-top: 1px solid #F0F2F6;
            color: #56627A;
            font-size: 13px;
        }

        tbody tr:hover {
            background: #F6FBFA;
        }

        .status {
            display: inline-block;
            padding: 6px 11px;
            border-radius: 20px;
            font-size: 11px;
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

        .empty-state {
            text-align: center;
            padding: 45px 20px;
            color: var(--muted);
        }

        .empty-state i {
            display: block;
            font-size: 35px;
            margin-bottom: 10px;
            color: #B5C0D0;
        }

        .pagination-area {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
            padding: 18px 24px;
            border-top: 1px solid var(--border);
        }

        .pagination-area p {
            color: var(--muted);
            font-size: 12px;
            margin: 0;
        }

        .pagination .page-link {
            color: var(--navy);
            border-color: var(--border);
            font-size: 12px;
        }

        .pagination .active .page-link {
            background: var(--teal);
            border-color: var(--teal);
            color: var(--navy);
        }

        @media (max-width: 650px) {
            .nav-links {
                margin-top: 12px;
                width: 100%;
            }

            .page-container {
                margin-top: 25px;
            }

            .content-header,
            .filter-area {
                padding: 18px;
            }

            .pagination-area {
                padding: 18px;
            }
        }

    </style>

</head>

<body>

<nav class="navbar navbar-dark">

    <div class="container d-flex flex-wrap justify-content-between">

        <a class="navbar-brand" href="dashboard.php">
            <span class="brand-logo">B</span>
            BePresent
        </a>

        <div class="nav-links">

            <a href="dashboard.php">
                <i class="bi bi-grid me-1"></i>
                Dashboard
            </a>

            <a href="history.php" class="active">
                <i class="bi bi-clock-history me-1"></i>
                Attendance History
            </a>

            <a href="../logout.php" class="logout-button">
                <i class="bi bi-box-arrow-right me-1"></i>
                Logout
            </a>

        </div>

    </div>

</nav>


<div class="container page-container">

    <!-- PAGE HEADING -->

    <div class="page-heading">

        <h2>Attendance History</h2>

        <p>
            Review your previous attendance records and working hours.
        </p>

        <div class="text-muted small">
            <?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?>
            · <?= htmlspecialchars($employee['employee_code']) ?>
        </div>

    </div>


    <!-- SUMMARY -->

    <div class="row g-3">

        <div class="col-6 col-lg-3">
            <div class="summary-card">
                <div class="summary-icon" style="color:var(--navy);">
                    <i class="bi bi-calendar-check"></i>
                </div>
                <p>Total Recorded Days</p>
                <h3><?= number_format($total_days) ?></h3>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="summary-card">
                <div class="summary-icon" style="color:var(--teal-dark);">
                    <i class="bi bi-person-check"></i>
                </div>
                <p>Present Days</p>
                <h3><?= number_format($present_days) ?></h3>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="summary-card">
                <div class="summary-icon" style="color:var(--amber);">
                    <i class="bi bi-clock-history"></i>
                </div>
                <p>Late Days</p>
                <h3><?= number_format($late_days) ?></h3>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="summary-card">
                <div class="summary-icon" style="color:var(--purple);">
                    <i class="bi bi-calendar2-minus"></i>
                </div>
                <p>Half Days</p>
                <h3><?= number_format($half_days) ?></h3>
            </div>
        </div>

    </div>


    <!-- HISTORY TABLE -->

    <section class="content-card">

        <div class="content-header">

            <h5>My Attendance Records</h5>

            <p>
                Showing your recorded attendance, newest first.
            </p>

        </div>


        <!-- FILTERS -->

        <form method="GET" class="filter-area">

            <div class="row g-3 align-items-end">

                <div class="col-md-3">

                    <label class="form-label">Start Date</label>

                    <input
                        type="date"
                        name="start_date"
                        class="form-control"
                        value="<?= htmlspecialchars($start_date) ?>"
                    >

                </div>


                <div class="col-md-3">

                    <label class="form-label">End Date</label>

                    <input
                        type="date"
                        name="end_date"
                        class="form-control"
                        value="<?= htmlspecialchars($end_date) ?>"
                    >

                </div>


                <div class="col-md-3">

                    <label class="form-label">Status</label>

                    <select name="status" class="form-select">

                        <option value="">All Statuses</option>

                        <option value="Present" <?= $status === 'Present' ? 'selected' : '' ?>>
                            Present
                        </option>

                        <option value="Late" <?= $status === 'Late' ? 'selected' : '' ?>>
                            Late
                        </option>

                        <option value="Half Day" <?= $status === 'Half Day' ? 'selected' : '' ?>>
                            Half Day
                        </option>

                    </select>

                </div>


                <div class="col-md-3 d-flex gap-2">

                    <button type="submit" class="btn filter-button flex-grow-1">
                        <i class="bi bi-funnel me-1"></i>
                        Filter
                    </button>

                    <a href="history.php" class="btn reset-button">
                        Reset
                    </a>

                </div>

            </div>

        </form>


        <!-- RECORDS -->

        <?php if (!empty($errors)): ?>

            <div class="alert alert-danger m-3">

                <?php foreach ($errors as $error): ?>
                    <div><?= htmlspecialchars($error) ?></div>
                <?php endforeach; ?>

            </div>

        <?php endif; ?>


        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>
                        <th>DATE</th>
                        <th>DAY</th>
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

                            $status_class = 'status-present';

                            if ($record['status'] === 'Late') {
                                $status_class = 'status-late';
                            } elseif ($record['status'] === 'Half Day') {
                                $status_class = 'status-half';
                            }

                            ?>

                            <tr>

                                <td>
                                    <strong>
                                        <?= date(
                                            'M d, Y',
                                            strtotime($record['attendance_date'])
                                        ) ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= date(
                                        'l',
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
                                    <span class="status <?= $status_class ?>">
                                        <?= htmlspecialchars($record['status']) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= !empty($record['remarks'])
                                        ? htmlspecialchars($record['remarks'])
                                        : '--' ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>

                            <td colspan="6">

                                <div class="empty-state">

                                    <i class="bi bi-calendar-x"></i>

                                    <strong>No attendance records found.</strong>

                                    <p class="small mb-0 mt-2">
                                        Try changing your filters or check again later.
                                    </p>

                                </div>

                            </td>

                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>


        <!-- PAGINATION -->

        <div class="pagination-area">

            <p>
                Showing
                <?= $total_records > 0 ? $offset + 1 : 0 ?>
                -
                <?= min($offset + $records_per_page, $total_records) ?>
                of <?= number_format($total_records) ?> records
            </p>


            <?php if ($total_pages > 1): ?>

                <nav>

                    <ul class="pagination pagination-sm mb-0">

                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">

                            <a class="page-link"
                                href="<?= htmlspecialchars(historyPageUrl(max(1, $page - 1))) ?>">
                                Previous
                            </a>

                        </li>


                        <?php for (
                            $i = max(1, $page - 2);
                            $i <= min($total_pages, $page + 2);
                            $i++
                        ): ?>

                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">

                                <a class="page-link"
                                    href="<?= htmlspecialchars(historyPageUrl($i)) ?>">
                                    <?= $i ?>
                                </a>

                            </li>

                        <?php endfor; ?>


                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">

                            <a class="page-link"
                                href="<?= htmlspecialchars(historyPageUrl(min($total_pages, $page + 1))) ?>">
                                Next
                            </a>

                        </li>

                    </ul>

                </nav>

            <?php endif; ?>

        </div>

    </section>

</div>

</body>
</html>
