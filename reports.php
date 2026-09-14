<?php
require_once 'config/auth.php';
requireLogin();
require_once 'config/db.php';

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'User';

// Set default month and year, or get from URL filters
$selectedMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// ---------------------------------------------------------
// CSV EXPORT LOGIC
// ---------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    $exportStmt = $pdo->prepare("
        SELECT t.transaction_date, t.title, c.name as category, t.type, t.amount 
        FROM transactions t
        LEFT JOIN categories c ON t.category_id = c.id
        WHERE t.user_id = ? AND MONTH(t.transaction_date) = ? AND YEAR(t.transaction_date) = ?
        ORDER BY t.transaction_date DESC
    ");
    $exportStmt->execute([$userId, $selectedMonth, $selectedYear]);
    $exportData = $exportStmt->fetchAll(PDO::FETCH_ASSOC);

    $filename = "FinTrack_Report_{$selectedYear}_{$selectedMonth}.csv";
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date', 'Title', 'Category', 'Type', 'Amount (INR)']);
    
    foreach ($exportData as $row) {
        $row['transaction_date'] = date('Y-m-d', strtotime($row['transaction_date']));
        fputcsv($output, $row);
    }
    fclose($output);
    exit; 
}

// ---------------------------------------------------------
// REPORT DATA FETCHING (For HTML View)
// ---------------------------------------------------------
$summaryStmt = $pdo->prepare("
    SELECT type, SUM(amount) as total 
    FROM transactions 
    WHERE user_id = ? AND MONTH(transaction_date) = ? AND YEAR(transaction_date) = ?
    GROUP BY type
");
$summaryStmt->execute([$userId, $selectedMonth, $selectedYear]);
$summaryData = $summaryStmt->fetchAll(PDO::FETCH_KEY_PAIR);

$totalIncome = $summaryData['income'] ?? 0.00;
$totalExpense = $summaryData['expense'] ?? 0.00;
$netSavings = $totalIncome - $totalExpense;

$catStmt = $pdo->prepare("
    SELECT c.name, SUM(t.amount) as total 
    FROM transactions t
    JOIN categories c ON t.category_id = c.id
    WHERE t.user_id = ? AND t.type = 'expense' AND MONTH(t.transaction_date) = ? AND YEAR(t.transaction_date) = ?
    GROUP BY c.id
    ORDER BY total DESC
");
$catStmt->execute([$userId, $selectedMonth, $selectedYear]);
$categoryBreakdown = $catStmt->fetchAll();

$transStmt = $pdo->prepare("
    SELECT t.transaction_date, t.title, c.name as category, t.type, t.amount 
    FROM transactions t
    LEFT JOIN categories c ON t.category_id = c.id
    WHERE t.user_id = ? AND MONTH(t.transaction_date) = ? AND YEAR(t.transaction_date) = ?
    ORDER BY t.transaction_date DESC
");
$transStmt->execute([$userId, $selectedMonth, $selectedYear]);
$transactions = $transStmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - FinTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        /* CSS to hide non-essential elements when printing */
        @media print {
            .no-print { display: none !important; }
            body { background-color: #fff !important; }
            .card { border: none !important; box-shadow: none !important; }
            .col-md-2 { display: none !important; } /* Hides sidebar */
            .col-md-10 { width: 100% !important; flex: 0 0 100% !important; max-width: 100% !important; padding: 0 !important; } /* Expands content */
        }
    </style>
</head>
<body class="bg-light">

<!-- Top Navigation -->
<nav class="navbar navbar-expand-lg navbar-white bg-white shadow-sm border-bottom no-print">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold text-primary fs-4" href="index.php">
            <i class="fa-solid fa-wallet me-2"></i>FinTrack
        </a>
        <div class="d-flex align-items-center">
            <span class="me-3 text-secondary fw-semibold">
                <i class="fa-solid fa-circle-user fs-4 me-2 align-middle"></i> <?= htmlspecialchars($userName) ?>
            </span>
            <a href="login.php" class="btn btn-sm btn-outline-danger">Logout</a>
        </div>
    </div>
</nav>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2 bg-white min-vh-100 shadow-sm border-end py-4 px-0 no-print">
            <div class="list-group list-group-flush rounded-0">
                <a href="index.php" class="list-group-item list-group-item-action fw-semibold border-0 py-3 px-4 text-secondary">
                    <i class="fa-solid fa-house me-3 w-20px"></i> Dashboard
                </a>
                <a href="transactions.php" class="list-group-item list-group-item-action fw-semibold border-0 py-3 px-4 text-secondary">
                    <i class="fa-solid fa-list me-3 w-20px"></i> Transactions
                </a>
                <a href="expenses.php" class="list-group-item list-group-item-action fw-semibold border-0 py-3 px-4 text-secondary">
                    <i class="fa-solid fa-arrow-trend-down me-3 w-20px"></i> Expenses
                </a>
                <a href="income.php" class="list-group-item list-group-item-action fw-semibold border-0 py-3 px-4 text-secondary">
                    <i class="fa-solid fa-arrow-trend-up me-3 w-20px"></i> Income
                </a>
                <a href="budgets.php" class="list-group-item list-group-item-action fw-semibold border-0 py-3 px-4 text-secondary">
                    <i class="fa-solid fa-chart-pie me-3 w-20px"></i> Budgets
                </a>
                <a href="analytics.php" class="list-group-item list-group-item-action fw-semibold border-0 py-3 px-4 text-secondary">
                    <i class="fa-solid fa-chart-line me-3 w-20px"></i> Analytics
                </a>
                <!-- Active Reports Link -->
                <a href="reports.php" class="list-group-item list-group-item-action active fw-semibold border-0 py-3 px-4">
                    <i class="fa-solid fa-file-invoice me-3 w-20px"></i> Reports
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-10 py-4 px-5">
            <!-- Header Area -->
            <div class="d-flex justify-content-between align-items-center mb-4 no-print">
                <h2 class="fw-bold text-dark"><i class="fa-solid fa-file-invoice text-primary me-2"></i> Monthly Report</h2>
                <div>
                    <button onclick="window.print()" class="btn btn-secondary me-2"><i class="fa-solid fa-print me-1"></i> Print</button>
                    <a href="reports.php?action=export_csv&month=<?= $selectedMonth ?>&year=<?= $selectedYear ?>" class="btn btn-success"><i class="fa-solid fa-file-csv me-1"></i> Export CSV</a>
                </div>
            </div>

            <!-- Print Header (Only visible when printing) -->
            <div class="d-none d-print-block mb-4 text-center">
                <h2 class="fw-bold">FinTrack Financial Report</h2>
                <p class="fs-5 text-muted">Period: <?= date('F', mktime(0, 0, 0, $selectedMonth, 10)) ?> <?= $selectedYear ?></p>
                <hr>
            </div>

            <!-- Filter Form (Hidden on Print) -->
            <div class="card shadow-sm border-0 mb-4 no-print">
                <div class="card-body">
                    <form method="GET" action="reports.php" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Month</label>
                            <select name="month" class="form-select">
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?= $m ?>" <?= $m == $selectedMonth ? 'selected' : '' ?>>
                                        <?= date('F', mktime(0, 0, 0, $m, 10)) ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Year</label>
                            <select name="year" class="form-select">
                                <?php 
                                $currentYr = date('Y');
                                for ($y = $currentYr; $y >= $currentYr - 5; $y--): 
                                ?>
                                    <option value="<?= $y ?>" <?= $y == $selectedYear ? 'selected' : '' ?>><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100 fw-semibold">Generate Report</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <!-- Summary Cards -->
                <div class="col-md-4">
                    <div class="card shadow-sm border-0 h-100 bg-success-subtle">
                        <div class="card-body p-4 text-center">
                            <p class="text-success fw-semibold mb-1">Total Income</p>
                            <h3 class="fw-bold m-0 text-success">₹<?= number_format($totalIncome, 2) ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm border-0 h-100 bg-danger-subtle">
                        <div class="card-body p-4 text-center">
                            <p class="text-danger fw-semibold mb-1">Total Expenses</p>
                            <h3 class="fw-bold m-0 text-danger">₹<?= number_format($totalExpense, 2) ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm border-0 h-100 <?= $netSavings >= 0 ? 'bg-primary-subtle' : 'bg-warning-subtle' ?>">
                        <div class="card-body p-4 text-center">
                            <p class="fw-semibold mb-1 <?= $netSavings >= 0 ? 'text-primary' : 'text-warning text-dark' ?>">Net Savings</p>
                            <h3 class="fw-bold m-0 <?= $netSavings >= 0 ? 'text-primary' : 'text-warning text-dark' ?>">₹<?= number_format($netSavings, 2) ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- Category Breakdown -->
                <div class="col-md-5">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white pt-4 pb-0 border-0">
                            <h5 class="fw-bold m-0">Expense Breakdown</h5>
                        </div>
                        <div class="card-body p-4">
                            <?php if (empty($categoryBreakdown)): ?>
                                <p class="text-muted text-center py-3">No expenses recorded for this period.</p>
                            <?php else: ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($categoryBreakdown as $cat): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                            <span class="fw-semibold text-secondary"><?= htmlspecialchars($cat['name']) ?></span>
                                            <span class="fw-bold text-danger">₹<?= number_format($cat['total'], 2) ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Detailed Transactions -->
                <div class="col-md-7">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white pt-4 pb-0 border-0">
                            <h5 class="fw-bold m-0">Transaction Log</h5>
                        </div>
                        <div class="card-body p-4">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle m-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Date</th>
                                            <th>Title</th>
                                            <th>Category</th>
                                            <th class="text-end">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($transactions)): ?>
                                            <tr>
                                                <td colspan="4" class="text-center py-4 text-muted">No transactions found.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($transactions as $t): ?>
                                                <tr>
                                                    <td><?= date('d M Y', strtotime($t['transaction_date'])) ?></td>
                                                    <td class="fw-semibold"><?= htmlspecialchars($t['title']) ?></td>
                                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($t['category'] ?? 'N/A') ?></span></td>
                                                    <td class="text-end fw-bold <?= $t['type'] === 'income' ? 'text-success' : 'text-danger' ?>">
                                                        <?= $t['type'] === 'income' ? '+' : '-' ?>₹<?= number_format($t['amount'], 2) ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div> <!-- Closes inner row -->
        </div> <!-- Closes col-md-10 -->
    </div> <!-- Closes outer row -->
</div> <!-- Closes container-fluid -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>