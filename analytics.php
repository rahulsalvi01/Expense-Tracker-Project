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
    <title>Analytics - FinTrack Pro Max</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<script>if(localStorage.getItem('theme') === 'light') document.body.classList.add('light-mode');</script>

<!-- 3D Background Canvas -->
<canvas id="bg-canvas"></canvas>

<!-- Top Navigation -->
<nav class="navbar navbar-expand-lg glass-nav shadow-sm border-bottom fixed-top">
    <div class="container-fluid px-4">
        <button class="navbar-toggler border-0 shadow-none text-primary d-md-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar" aria-controls="mobileSidebar">
            <i class="fa-solid fa-bars fs-3"></i>
        </button>
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

<!-- Mobile Offcanvas Sidebar -->
<div class="offcanvas offcanvas-start glass-panel d-md-none" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel" style="width: 280px; z-index: 1050; top: 70px; height: calc(100vh - 70px);">
    <div class="offcanvas-body p-0">
        <div class="list-group list-group-flush rounded-0 px-2 mt-3">
            <a href="index.php" class="list-group-item list-group-item-action fw-semibold mobile-nav-item py-3 px-4">
                <i class="fa-solid fa-house me-3 w-20px"></i> Dashboard
            </a>
            <a href="transactions.php" class="list-group-item list-group-item-action fw-semibold mobile-nav-item py-3 px-4">
                <i class="fa-solid fa-list me-3 w-20px"></i> Transactions
            </a>
            <a href="expenses.php" class="list-group-item list-group-item-action fw-semibold mobile-nav-item py-3 px-4">
                <i class="fa-solid fa-arrow-trend-down me-3 w-20px"></i> Expenses
            </a>
            <a href="income.php" class="list-group-item list-group-item-action fw-semibold mobile-nav-item py-3 px-4">
                <i class="fa-solid fa-arrow-trend-up me-3 w-20px"></i> Income
            </a>
            <a href="budgets.php" class="list-group-item list-group-item-action fw-semibold mobile-nav-item py-3 px-4">
                <i class="fa-solid fa-chart-pie me-3 w-20px"></i> Budgets
            </a>
            <a href="analytics.php" class="list-group-item list-group-item-action fw-semibold mobile-nav-item py-3 px-4">
                <i class="fa-solid fa-chart-line me-3 w-20px"></i> Analytics
            </a>
            <a href="reports.php" class="list-group-item list-group-item-action fw-semibold mobile-nav-item py-3 px-4">
                <i class="fa-solid fa-file-invoice me-3 w-20px"></i> Reports
            </a>
            <a href="settings.php" class="list-group-item list-group-item-action fw-semibold mobile-nav-item py-3 px-4">
                <i class="fa-solid fa-gear me-3 w-20px"></i> Settings
            </a>
        </div>
    </div>
</div>


<div class="container-fluid" style="margin-top: 70px;">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2 glass-panel min-vh-100 shadow-sm border-end py-4 px-2 d-none d-md-block" style="position: fixed; top: 70px; height: calc(100vh - 70px); z-index: 10;">
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
                <a href="analytics.php" class="list-group-item list-group-item-action active fw-semibold py-3 px-4 animate-fade-in delay-3">
                    <i class="fa-solid fa-chart-line me-3 w-20px"></i> Analytics
                </a>
                <a href="reports.php" class="list-group-item list-group-item-action fw-semibold py-3 px-4 animate-fade-in delay-4">
                    <i class="fa-solid fa-file-invoice me-3 w-20px"></i> Reports
                </a>
                <a href="settings.php" class="list-group-item list-group-item-action fw-semibold py-3 px-4 animate-fade-in delay-4">
                    <i class="fa-solid fa-gear me-3 w-20px"></i> Settings
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-10 py-4 px-3 px-md-5 offset-md-2" style="position: relative; z-index: 5;">
            <div class="d-flex justify-content-between align-items-center mb-4 animate-fade-in">
                <h2 class="fw-bold text-light"><i class="fa-solid fa-chart-line text-info me-2"></i> Analytics & Charts</h2>
            </div>

            <div class="row g-4">
                <!-- Monthly Income vs Expense (Bar Chart) -->
                <div class="col-lg-8 animate-fade-in delay-1">
                    <div class="card glass-panel border-0 h-100">
                        <div class="card-body">
                            <h5 class="fw-bold mb-4 text-light">Income vs Expenses (Last 6 Months)</h5>
                            <div style="position: relative; height: 300px; width: 100%;">
                                <canvas id="incomeExpenseChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Category-wise Expenses (Doughnut Chart) -->
                <div class="col-lg-4 animate-fade-in delay-1">
                    <div class="card glass-panel border-0 h-100">
                        <div class="card-body">
                            <h5 class="fw-bold mb-4 text-light">Expenses by Category</h5>
                            <?php if(empty($doughnutData)): ?>
                                <p class="text-secondary text-center mt-5">No expense data available.</p>
                            <?php else: ?>
                                <div style="position: relative; height: 300px; width: 100%;">
                                    <canvas id="categoryChart"></canvas>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- 30-Day Spending Trend (Line Chart) -->
                <div class="col-lg-7 animate-fade-in delay-2">
                    <div class="card glass-panel border-0 h-100">
                        <div class="card-body">
                            <h5 class="fw-bold mb-4 text-light">Spending Trend (Last 30 Days)</h5>
                            <div style="position: relative; height: 300px; width: 100%;">
                                <canvas id="trendChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Budget Utilization & Top Categories -->
                <div class="col-lg-5 animate-fade-in delay-2">
                    <div class="card glass-panel border-0 mb-4">
                        <div class="card-body">
                            <h5 class="fw-bold mb-4 text-light">Budget Utilization (<?= date('F Y') ?>)</h5>
                            <?php if(empty($budgetLabels)): ?>
                                <p class="text-secondary text-center">No budgets set for this month.</p>
                            <?php else: ?>
                                <div style="position: relative; height: 250px; width: 100%;">
                                    <canvas id="budgetChart"></canvas>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Top Categories Table -->
                    <div class="card glass-panel border-0">
                        <div class="card-body">
                            <h5 class="fw-bold mb-3 text-light">Top Spending Categories</h5>
                            <div class="table-responsive">
                                <table class="table table-sm table-borderless align-middle m-0 text-light">
                                    <tbody>
                                        <?php 
                                        $topCount = 0;
                                        foreach ($categoryExpenses as $catExp): 
                                            if ($topCount++ >= 5) break; 
                                        ?>
                                            <tr>
                                                <td class="fw-semibold text-secondary border-bottom border-secondary"><?= htmlspecialchars($catExp['name']) ?></td>
                                                <td class="text-end text-danger fw-bold border-bottom border-secondary">₹<?= number_format($catExp['total'], 2) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if(empty($categoryExpenses)): ?>
                                            <tr><td class="text-secondary">No expenses yet.</td></tr>
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
        'rgba(139, 92, 246, 0.8)',  /* Purple */
        'rgba(59, 130, 246, 0.8)',  /* Blue */
        'rgba(16, 185, 129, 0.8)',  /* Emerald */
        'rgba(245, 158, 11, 0.8)',  /* Amber */
        'rgba(239, 68, 68, 0.8)',   /* Red */
        'rgba(236, 72, 153, 0.8)'   /* Pink */
    ];

    // Global Chart Settings responsive to Theme
    const isLightMode = document.body.classList.contains('light-mode');
    Chart.defaults.color = isLightMode ? 'rgba(0, 0, 0, 0.7)' : 'rgba(255, 255, 255, 0.7)';
    Chart.defaults.borderColor = isLightMode ? 'rgba(0, 0, 0, 0.1)' : 'rgba(255, 255, 255, 0.1)';
    const gridColor = isLightMode ? 'rgba(0, 0, 0, 0.05)' : 'rgba(255, 255, 255, 0.05)';

    if (document.getElementById('categoryChart')) {
        new Chart(document.getElementById('categoryChart'), {
            type: 'doughnut',
            data: {
                labels: doughnutLabels,
                datasets: [{
                    data: doughnutData,
                    backgroundColor: pastelColors,
                    borderWidth: 0,
                    hoverOffset: 10
                }]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: { color: 'rgba(255,255,255,0.7)' }
                    }
                },
                cutout: '75%'
            }
        });
    }

    if (document.getElementById('incomeExpenseChart')) {
        new Chart(document.getElementById('incomeExpenseChart'), {
            type: 'bar',
            data: {
                labels: monthlyLabels,
                datasets: [
                    { label: 'Income', data: incomeData, backgroundColor: 'rgba(16, 185, 129, 0.8)', borderRadius: 4, maxBarThickness: 40 },
                    { label: 'Expenses', data: expenseData, backgroundColor: 'rgba(239, 68, 68, 0.8)', borderRadius: 4, maxBarThickness: 40 }
                ]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false, 
                scales: { 
                    y: { beginAtZero: true, grid: { color: gridColor } },
                    x: { grid: { display: false } }
                } 
            }
        });
    }

    if (document.getElementById('trendChart')) {
        new Chart(document.getElementById('trendChart'), {
            type: 'line',
            data: {
                labels: trendLabels,
                datasets: [{
                    label: 'Daily Expenses', data: trendData,
                    borderColor: 'rgba(139, 92, 246, 1)', backgroundColor: 'rgba(139, 92, 246, 0.2)',
                    borderWidth: 3, fill: true, tension: 0.4, pointBackgroundColor: 'rgba(139, 92, 246, 1)'
                }]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false, 
                scales: { 
                    y: { beginAtZero: true, grid: { color: gridColor } },
                    x: { grid: { display: false } }
                } 
            }
        });
    }

    if (document.getElementById('budgetChart')) {
        new Chart(document.getElementById('budgetChart'), {
            type: 'bar',
            data: {
                labels: budgetLabels,
                datasets: [
                    { label: 'Spent', data: budgetSpent, backgroundColor: 'rgba(245, 158, 11, 0.8)', borderRadius: 4, maxBarThickness: 20 },
                    { label: 'Limit', data: budgetLimits, backgroundColor: 'rgba(255, 255, 255, 0.1)', borderRadius: 4, maxBarThickness: 20 }
                ]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false, 
                indexAxis: 'y', 
                scales: { 
                    x: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' } },
                    y: { grid: { display: false } }
                } 
            }
        });
    }
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<script src="app3d.js"></script>
</body>
</html>