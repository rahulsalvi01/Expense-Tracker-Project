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
    <title>Reports - FinTrack Pro Max</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        /* CSS to hide non-essential elements when printing */
        @media print {
            .no-print { display: none !important; }
            body { background-color: #fff !important; }
            .card { border: none !important; box-shadow: none !important; background: transparent !important; backdrop-filter: none !important; }
            .col-md-2 { display: none !important; } /* Hides sidebar */
            .col-md-10 { width: 100% !important; flex: 0 0 100% !important; max-width: 100% !important; padding: 0 !important; margin-left: 0 !important; } /* Expands content */
            .text-light { color: #000 !important; }
            canvas { display: none !important; }
        }
    </style>
</head>
<body>
<script>if(localStorage.getItem('theme') === 'light') document.body.classList.add('light-mode');</script>

<!-- 3D Background Canvas -->
<canvas id="bg-canvas"></canvas>

<!-- Top Navigation -->
<nav class="navbar navbar-expand-lg glass-nav shadow-sm border-bottom fixed-top no-print">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold text-primary fs-4" href="index.php" style="background: linear-gradient(90deg, #3b82f6, #8b5cf6); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
            <i class="fa-solid fa-wallet me-2" style="-webkit-text-fill-color: #3b82f6;"></i>FinTrack
        </a>
        <div class="d-flex align-items-center">
            <span class="me-3 fw-semibold text-light">
                <i class="fa-solid fa-circle-user fs-4 me-2 align-middle text-primary"></i> <?= htmlspecialchars($userName) ?>
            </span>
            <a href="login.php" class="btn btn-sm btn-outline-danger" style="border-radius: 8px;">Logout</a>
        </div>
    </div>
</nav>

<div class="container-fluid" style="margin-top: 70px;">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2 glass-panel min-vh-100 shadow-sm border-end py-4 px-2 no-print d-none d-md-block" style="position: fixed; top: 70px; height: calc(100vh - 70px); z-index: 10;">
            <div class="list-group list-group-flush rounded-0 px-2">
                <a href="index.php" class="list-group-item list-group-item-action fw-semibold py-3 px-4 animate-fade-in delay-1">
                    <i class="fa-solid fa-house me-3 w-20px"></i> Dashboard
                </a>
                <a href="transactions.php" class="list-group-item list-group-item-action fw-semibold py-3 px-4 animate-fade-in delay-1">
                    <i class="fa-solid fa-list me-3 w-20px"></i> Transactions
                </a>
                <a href="expenses.php" class="list-group-item list-group-item-action fw-semibold py-3 px-4 animate-fade-in delay-2">
                    <i class="fa-solid fa-arrow-trend-down me-3 w-20px"></i> Expenses
                </a>
                <a href="income.php" class="list-group-item list-group-item-action fw-semibold py-3 px-4 animate-fade-in delay-2">
                    <i class="fa-solid fa-arrow-trend-up me-3 w-20px"></i> Income
                </a>
                <a href="budgets.php" class="list-group-item list-group-item-action fw-semibold py-3 px-4 animate-fade-in delay-3">
                    <i class="fa-solid fa-chart-pie me-3 w-20px"></i> Budgets
                </a>
                <a href="analytics.php" class="list-group-item list-group-item-action fw-semibold py-3 px-4 animate-fade-in delay-3">
                    <i class="fa-solid fa-chart-line me-3 w-20px"></i> Analytics
                </a>
                <!-- Active Reports Link -->
                <a href="reports.php" class="list-group-item list-group-item-action active fw-semibold py-3 px-4 animate-fade-in delay-4">
                    <i class="fa-solid fa-file-invoice me-3 w-20px"></i> Reports
                </a>
                <a href="settings.php" class="list-group-item list-group-item-action fw-semibold py-3 px-4 animate-fade-in delay-4">
                    <i class="fa-solid fa-gear me-3 w-20px"></i> Settings
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-10 py-4 px-3 px-md-5 offset-md-2 pb-mobile-nav" style="position: relative; z-index: 5;">
            <!-- Header Area -->
            <div class="d-flex justify-content-between align-items-center mb-4 no-print animate-fade-in">
                <h2 class="fw-bold text-light"><i class="fa-solid fa-file-invoice text-primary me-2"></i> Monthly Report</h2>
                <div>
                    <button onclick="window.print()" class="btn btn-outline-secondary me-2"><i class="fa-solid fa-print me-1"></i> Print</button>
                    <a href="reports.php?action=export_csv&month=<?= $selectedMonth ?>&year=<?= $selectedYear ?>" class="btn btn-outline-success"><i class="fa-solid fa-file-csv me-1"></i> Export CSV</a>
                </div>
            </div>

            <!-- Print Header (Only visible when printing) -->
            <div class="d-none d-print-block mb-4 text-center text-dark">
                <h2 class="fw-bold">FinTrack Financial Report</h2>
                <p class="fs-5 text-secondary">Period: <?= date('F', mktime(0, 0, 0, $selectedMonth, 10)) ?> <?= $selectedYear ?></p>
                <hr>
            </div>

            <!-- Filter Form (Hidden on Print) -->
            <div class="card glass-panel border-0 mb-4 no-print animate-fade-in delay-1">
                <div class="card-body p-4">
                    <form method="GET" action="reports.php" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold text-secondary">Month</label>
                            <select name="month" class="form-select bg-transparent text-light border-secondary">
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option class="text-dark" value="<?= $m ?>" <?= $m == $selectedMonth ? 'selected' : '' ?>>
                                        <?= date('F', mktime(0, 0, 0, $m, 10)) ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold text-secondary">Year</label>
                            <select name="year" class="form-select bg-transparent text-light border-secondary">
                                <?php 
                                $currentYr = date('Y');
                                for ($y = $currentYr; $y >= $currentYr - 5; $y--): 
                                ?>
                                    <option class="text-dark" value="<?= $y ?>" <?= $y == $selectedYear ? 'selected' : '' ?>><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-outline-primary w-100 fw-semibold">Generate Report</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <!-- Summary Cards -->
                <div class="col-md-4 animate-fade-in delay-1">
                    <div class="card glass-panel border-0 h-100" style="border-left: 4px solid #10b981 !important;">
                        <div class="card-body p-4 text-center">
                            <p class="text-success fw-semibold mb-1">Total Income</p>
                            <h3 class="fw-bold m-0 text-success">₹<?= number_format($totalIncome, 2) ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 animate-fade-in delay-2">
                    <div class="card glass-panel border-0 h-100" style="border-left: 4px solid #ef4444 !important;">
                        <div class="card-body p-4 text-center">
                            <p class="text-danger fw-semibold mb-1">Total Expenses</p>
                            <h3 class="fw-bold m-0 text-danger">₹<?= number_format($totalExpense, 2) ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 animate-fade-in delay-3">
                    <div class="card glass-panel border-0 h-100" style="border-left: 4px solid <?= $netSavings >= 0 ? '#3b82f6' : '#f59e0b' ?> !important;">
                        <div class="card-body p-4 text-center">
                            <p class="fw-semibold mb-1 <?= $netSavings >= 0 ? 'text-primary' : 'text-warning' ?>">Net Savings</p>
                            <h3 class="fw-bold m-0 <?= $netSavings >= 0 ? 'text-primary' : 'text-warning' ?>">₹<?= number_format($netSavings, 2) ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- Category Breakdown -->
                <div class="col-md-5 animate-fade-in delay-2">
                    <div class="card glass-panel border-0 h-100">
                        <div class="card-header bg-transparent pt-4 pb-0 border-0">
                            <h5 class="fw-bold m-0 text-light">Expense Breakdown</h5>
                        </div>
                        <div class="card-body p-4">
                            <?php if (empty($categoryBreakdown)): ?>
                                <p class="text-secondary text-center py-3">No expenses recorded for this period.</p>
                            <?php else: ?>
                                <ul class="list-group list-group-flush bg-transparent">
                                    <?php foreach ($categoryBreakdown as $cat): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center px-0 bg-transparent border-secondary text-light">
                                            <span class="fw-semibold"><?= htmlspecialchars($cat['name']) ?></span>
                                            <span class="fw-bold text-danger">₹<?= number_format($cat['total'], 2) ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Detailed Transactions -->
                <div class="col-md-7 animate-fade-in delay-3">
                    <div class="card glass-panel border-0 h-100">
                        <div class="card-header bg-transparent pt-4 pb-0 border-0">
                            <h5 class="fw-bold m-0 text-light">Transaction Log</h5>
                        </div>
                        <div class="card-body p-4">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle m-0 text-light border-dark">
                                    <thead>
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
                                                <td colspan="4" class="text-center py-4 text-secondary border-0">No transactions found.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($transactions as $t): ?>
                                                <tr>
                                                    <td class="border-secondary"><?= date('d M Y', strtotime($t['transaction_date'])) ?></td>
                                                    <td class="fw-semibold border-secondary"><?= htmlspecialchars($t['title']) ?></td>
                                                    <td class="border-secondary"><span class="badge bg-secondary"><?= htmlspecialchars($t['category'] ?? 'N/A') ?></span></td>
                                                    <td class="text-end fw-bold border-secondary <?= $t['type'] === 'income' ? 'text-success' : 'text-danger' ?>">
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

<!-- Bottom Navigation for Mobile -->
<div class="bottom-nav d-md-none glass-panel no-print">
    <a href="index.php" class="bottom-nav-item">
        <i class="fa-solid fa-house"></i>
        <span>Home</span>
    </a>
    <a href="transactions.php" class="bottom-nav-item">
        <i class="fa-solid fa-list"></i>
        <span>History</span>
    </a>
    <a href="expenses.php" class="bottom-nav-item">
        <i class="fa-solid fa-minus-circle" style="color: var(--accent-red);"></i>
        <span>Expense</span>
    </a>
    <a href="income.php" class="bottom-nav-item">
        <i class="fa-solid fa-plus-circle" style="color: var(--accent-green);"></i>
        <span>Income</span>
    </a>
    <a href="settings.php" class="bottom-nav-item">
        <i class="fa-solid fa-gear"></i>
        <span>Settings</span>
    </a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<script src="app3d.js"></script>
</body>
</html>