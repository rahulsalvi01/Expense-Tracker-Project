<?php
require_once 'config/auth.php';
requireLogin();
require_once 'config/db.php';

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'User';
$currentMonth = (int)date('m');
$currentYear = (int)date('Y');

// 1. Category-wise Expenses (Doughnut Chart)
$catExpStmt = $pdo->prepare("
    SELECT c.name, SUM(t.amount) as total 
    FROM transactions t 
    JOIN categories c ON t.category_id = c.id 
    WHERE t.user_id = ? AND t.type = 'expense' 
    GROUP BY c.id 
    ORDER BY total DESC
");
$catExpStmt->execute([$userId]);
$categoryExpenses = $catExpStmt->fetchAll();

$doughnutLabels = [];
$doughnutData = [];
foreach ($categoryExpenses as $row) {
    $doughnutLabels[] = $row['name'];
    $doughnutData[] = $row['total'];
}

// 2. Monthly Income vs Expense (Bar Chart - Last 6 Months)
$monthlyStmt = $pdo->prepare("
    SELECT DATE_FORMAT(transaction_date, '%Y-%m') as month_year, type, SUM(amount) as total 
    FROM transactions 
    WHERE user_id = ? AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month_year, type 
    ORDER BY month_year ASC
");
$monthlyStmt->execute([$userId]);
$monthlyRawData = $monthlyStmt->fetchAll();

$monthlyLabels = [];
$incomeData = [];
$expenseData = [];
$processedMonths = [];

foreach ($monthlyRawData as $row) {
    $m = $row['month_year'];
    if (!isset($processedMonths[$m])) {
        $processedMonths[$m] = ['income' => 0, 'expense' => 0];
        $monthlyLabels[] = date('M Y', strtotime($m . '-01'));
    }
    $processedMonths[$m][$row['type']] = (float)$row['total'];
}

foreach ($processedMonths as $data) {
    $incomeData[] = $data['income'];
    $expenseData[] = $data['expense'];
}

// 3. Spending Trend (Line Chart - Last 30 Days)
$trendStmt = $pdo->prepare("
    SELECT DATE(transaction_date) as tx_date, SUM(amount) as total 
    FROM transactions 
    WHERE user_id = ? AND type = 'expense' AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY tx_date 
    ORDER BY tx_date ASC
");
$trendStmt->execute([$userId]);
$trendRaw = $trendStmt->fetchAll();

$trendLabels = [];
$trendData = [];
foreach ($trendRaw as $row) {
    $trendLabels[] = date('d M', strtotime($row['tx_date']));
    $trendData[] = $row['total'];
}

// 4. Budget Utilization (Bar Chart for Current Month)
$budgetStmt = $pdo->prepare("
    SELECT c.name, b.amount as limit_amount,
           (SELECT COALESCE(SUM(amount), 0) FROM transactions t 
            WHERE t.category_id = b.category_id AND t.user_id = b.user_id AND t.type = 'expense'
            AND MONTH(t.transaction_date) = b.month AND YEAR(t.transaction_date) = b.year) as spent_amount
    FROM budgets b
    JOIN categories c ON b.category_id = c.id
    WHERE b.user_id = ? AND b.month = ? AND b.year = ?
");
$budgetStmt->execute([$userId, $currentMonth, $currentYear]);
$budgetsRaw = $budgetStmt->fetchAll();

$budgetLabels = [];
$budgetLimits = [];
$budgetSpent = [];
foreach ($budgetsRaw as $b) {
    $budgetLabels[] = $b['name'];
    $budgetLimits[] = $b['limit_amount'];
    $budgetSpent[] = $b['spent_amount'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - FinTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-light">

<!-- Top Navigation -->
<nav class="navbar navbar-expand-lg navbar-white bg-white shadow-sm border-bottom">
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
        <div class="col-md-2 bg-white min-vh-100 shadow-sm border-end py-4 px-0">
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
                <!-- Active Analytics Link -->
                <a href="analytics.php" class="list-group-item list-group-item-action active fw-semibold border-0 py-3 px-4">
                    <i class="fa-solid fa-chart-line me-3 w-20px"></i> Analytics
                </a>
                <a href="reports.php" class="list-group-item list-group-item-action fw-semibold border-0 py-3 px-4 text-secondary">
                    <i class="fa-solid fa-file-invoice me-3 w-20px"></i> Reports
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-10 py-4 px-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold text-dark"><i class="fa-solid fa-chart-line text-info me-2"></i> Analytics & Charts</h2>
            </div>

            <div class="row g-4">
                <!-- Monthly Income vs Expense (Bar Chart) -->
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body">
                            <h5 class="fw-bold mb-4">Income vs Expenses (Last 6 Months)</h5>
                            <div style="position: relative; height: 300px; width: 100%;">
                                <canvas id="incomeExpenseChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Category-wise Expenses (Doughnut Chart) -->
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body">
                            <h5 class="fw-bold mb-4">Expenses by Category</h5>
                            <?php if(empty($doughnutData)): ?>
                                <p class="text-muted text-center mt-5">No expense data available.</p>
                            <?php else: ?>
                                <div style="position: relative; height: 300px; width: 100%;">
                                    <canvas id="categoryChart"></canvas>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- 30-Day Spending Trend (Line Chart) -->
                <div class="col-lg-7">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body">
                            <h5 class="fw-bold mb-4">Spending Trend (Last 30 Days)</h5>
                            <div style="position: relative; height: 300px; width: 100%;">
                                <canvas id="trendChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Budget Utilization & Top Categories -->
                <div class="col-lg-5">
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-body">
                            <h5 class="fw-bold mb-4">Budget Utilization (<?= date('F Y') ?>)</h5>
                            <?php if(empty($budgetLabels)): ?>
                                <p class="text-muted text-center">No budgets set for this month.</p>
                            <?php else: ?>
                                <div style="position: relative; height: 250px; width: 100%;">
                                    <canvas id="budgetChart"></canvas>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Top Categories Table -->
                    <div class="card shadow-sm border-0">
                        <div class="card-body">
                            <h5 class="fw-bold mb-3">Top Spending Categories</h5>
                            <div class="table-responsive">
                                <table class="table table-sm table-borderless align-middle m-0">
                                    <tbody>
                                        <?php 
                                        $topCount = 0;
                                        foreach ($categoryExpenses as $catExp): 
                                            if ($topCount++ >= 5) break; 
                                        ?>
                                            <tr>
                                                <td class="fw-semibold text-secondary"><?= htmlspecialchars($catExp['name']) ?></td>
                                                <td class="text-end text-danger fw-bold">₹<?= number_format($catExp['total'], 2) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if(empty($categoryExpenses)): ?>
                                            <tr><td class="text-muted">No expenses yet.</td></tr>
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

<script>
    const doughnutLabels = <?= json_encode($doughnutLabels) ?>;
    const doughnutData = <?= json_encode($doughnutData) ?>;
    
    const monthlyLabels = <?= json_encode($monthlyLabels) ?>;
    const incomeData = <?= json_encode($incomeData) ?>;
    const expenseData = <?= json_encode($expenseData) ?>;
    
    const trendLabels = <?= json_encode($trendLabels) ?>;
    const trendData = <?= json_encode($trendData) ?>;

    const budgetLabels = <?= json_encode($budgetLabels) ?>;
    const budgetLimits = <?= json_encode($budgetLimits) ?>;
    const budgetSpent = <?= json_encode($budgetSpent) ?>;

    const pastelColors = [
        'rgba(255, 99, 132, 0.7)',
        'rgba(54, 162, 235, 0.7)',
        'rgba(255, 206, 86, 0.7)',
        'rgba(75, 192, 192, 0.7)',
        'rgba(153, 102, 255, 0.7)',
        'rgba(255, 159, 64, 0.7)'
    ];

    if (document.getElementById('categoryChart')) {
        new Chart(document.getElementById('categoryChart'), {
            type: 'doughnut',
            data: {
                labels: doughnutLabels,
                datasets: [{
                    data: doughnutData,
                    backgroundColor: pastelColors,
                    borderWidth: 1
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }

    if (document.getElementById('incomeExpenseChart')) {
        new Chart(document.getElementById('incomeExpenseChart'), {
            type: 'bar',
            data: {
                labels: monthlyLabels,
                datasets: [
                    { label: 'Income', data: incomeData, backgroundColor: 'rgba(25, 135, 84, 0.7)', maxBarThickness: 40 },
                    { label: 'Expenses', data: expenseData, backgroundColor: 'rgba(220, 53, 69, 0.7)', maxBarThickness: 40 }
                ]
            },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
        });
    }

    if (document.getElementById('trendChart')) {
        new Chart(document.getElementById('trendChart'), {
            type: 'line',
            data: {
                labels: trendLabels,
                datasets: [{
                    label: 'Daily Expenses', data: trendData,
                    borderColor: 'rgba(13, 110, 253, 0.8)', backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    borderWidth: 2, fill: true, tension: 0.4
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
        });
    }

    if (document.getElementById('budgetChart')) {
        new Chart(document.getElementById('budgetChart'), {
            type: 'bar',
            data: {
                labels: budgetLabels,
                datasets: [
                    { label: 'Spent', data: budgetSpent, backgroundColor: 'rgba(255, 193, 7, 0.8)', maxBarThickness: 30 },
                    { label: 'Limit', data: budgetLimits, backgroundColor: 'rgba(200, 200, 200, 0.4)', maxBarThickness: 30 }
                ]
            },
            options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y', scales: { x: { beginAtZero: true } } }
        });
    }
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>