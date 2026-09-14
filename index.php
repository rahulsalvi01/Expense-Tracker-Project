<?php
require_once 'config/auth.php';
requireLogin();
require_once 'config/db.php';

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'User';

// 1. Calculate Total Income & Expenses
$totalsStmt = $pdo->prepare("SELECT type, SUM(amount) as total FROM transactions WHERE user_id = ? GROUP BY type");
$totalsStmt->execute([$userId]);
$totals = $totalsStmt->fetchAll(PDO::FETCH_KEY_PAIR); 

$totalIncome = $totals['income'] ?? 0.00;
$totalExpense = $totals['expense'] ?? 0.00;
$totalBalance = $totalIncome - $totalExpense;

// 2. Fetch the 5 Most Recent Transactions
$recentStmt = $pdo->prepare("SELECT t.id, t.title, t.amount, t.transaction_date, t.type, c.name as category_name 
                             FROM transactions t 
                             LEFT JOIN categories c ON t.category_id = c.id 
                             WHERE t.user_id = ? 
                             ORDER BY t.transaction_date DESC 
                             LIMIT 5");
$recentStmt->execute([$userId]);
$recentTransactions = $recentStmt->fetchAll();

// 3. NEW: Calculate Budget Used for the Current Month
$currentMonth = (int)date('m');
$currentYear = (int)date('Y');

// Get total limit of all budgets set for this month
$budgetTotalStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM budgets WHERE user_id = ? AND month = ? AND year = ?");
$budgetTotalStmt->execute([$userId, $currentMonth, $currentYear]);
$totalBudgetLimit = $budgetTotalStmt->fetchColumn();

// Get total spent so far this month in ONLY the budgeted categories
$spentStmt = $pdo->prepare("
    SELECT COALESCE(SUM(t.amount), 0) 
    FROM transactions t
    JOIN budgets b ON t.category_id = b.category_id AND t.user_id = b.user_id
    WHERE t.user_id = ? 
      AND t.type = 'expense' 
      AND MONTH(t.transaction_date) = ? 
      AND YEAR(t.transaction_date) = ?
      AND b.month = ? 
      AND b.year = ?
");
$spentStmt->execute([$userId, $currentMonth, $currentYear, $currentMonth, $currentYear]);
$totalBudgetSpent = $spentStmt->fetchColumn();

// Calculate Percentage and Color
$budgetPercent = ($totalBudgetLimit > 0) ? min(100, round(($totalBudgetSpent / $totalBudgetLimit) * 100)) : 0;
$budgetColor = 'text-success'; // Green if good
if ($budgetPercent >= 100) $budgetColor = 'text-danger'; // Red if over
elseif ($budgetPercent >= 75) $budgetColor = 'text-warning'; // Yellow if close
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - FinTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
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
                <a href="index.php" class="list-group-item list-group-item-action active fw-semibold border-0 py-3 px-4">
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
                <a href="reports.php" class="list-group-item list-group-item-action fw-semibold border-0 py-3 px-4 text-secondary">
                    <i class="fa-solid fa-file-invoice me-3 w-20px"></i> Reports
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-10 py-4 px-5">
            <h3 class="fw-bold text-dark mb-4">Dashboard Overview</h3>
            
            <!-- Summary Widgets -->
            <div class="row g-4 mb-5">
                <div class="col-md-3">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body p-4">
                            <p class="text-muted fw-semibold mb-1">Total Balance</p>
                            <h3 class="fw-bold m-0 <?= $totalBalance >= 0 ? 'text-dark' : 'text-danger' ?>">
                                ₹<?= number_format($totalBalance, 2) ?>
                            </h3>
                        </div>
                    </div>
                </div>
                <!-- Income Card with Pastel Green styling -->
                <div class="col-md-3">
                    <div class="card shadow-sm border-0 h-100 bg-success-subtle">
                        <div class="card-body p-4">
                            <p class="text-success fw-semibold mb-1" style="opacity: 0.8;">Total Income</p>
                            <h3 class="fw-bold m-0 text-success">₹<?= number_format($totalIncome, 2) ?></h3>
                        </div>
                    </div>
                </div>
                <!-- Expense Card with Pastel Red styling -->
                <div class="col-md-3">
                    <div class="card shadow-sm border-0 h-100 bg-danger-subtle">
                        <div class="card-body p-4">
                            <p class="text-danger fw-semibold mb-1" style="opacity: 0.8;">Total Expenses</p>
                            <h3 class="fw-bold m-0 text-danger">₹<?= number_format($totalExpense, 2) ?></h3>
                        </div>
                    </div>
                </div>
                <!-- NEW: Dynamic Budget Card -->
                <div class="col-md-3">
                    <div class="card shadow-sm border-0 h-100 bg-light">
                        <div class="card-body p-4">
                            <p class="text-muted fw-semibold mb-1">Budget Used (<?= date('M') ?>)</p>
                            <h3 class="fw-bold m-0 <?= $budgetColor ?>"><?= $budgetPercent ?>%</h3>
                            <small class="text-muted">₹<?= number_format($totalBudgetSpent, 2) ?> / ₹<?= number_format($totalBudgetLimit, 2) ?></small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- Recent Transactions Table -->
                <div class="col-md-8">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white border-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
                            <h5 class="fw-bold m-0">Recent Transactions</h5>
                            <a href="transactions.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        <div class="card-body p-4">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle m-0">
                                    <tbody>
                                        <?php if (empty($recentTransactions)): ?>
                                            <tr>
                                                <td colspan="4" class="text-center py-4 text-muted">No transactions yet. Start adding some!</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($recentTransactions as $t): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <!-- Pastel Badges for Icons -->
                                                            <div class="rounded-circle p-2 me-3 text-center <?= $t['type'] === 'income' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?>" style="width: 40px; height: 40px;">
                                                                <i class="fa-solid <?= $t['type'] === 'income' ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' ?>"></i>
                                                            </div>
                                                            <div>
                                                                <h6 class="m-0 fw-semibold"><?= htmlspecialchars($t['title']) ?></h6>
                                                                <small class="text-muted"><?= htmlspecialchars($t['category_name'] ?? 'Uncategorized') ?></small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="text-muted"><?= date('d M Y', strtotime($t['transaction_date'])) ?></td>
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

                <!-- Quick Actions Widget -->
                <div class="col-md-4">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white border-0 pt-4 pb-0">
                            <h5 class="fw-bold m-0">Quick Actions</h5>
                        </div>
                        <div class="card-body p-4">
                            <a href="expenses.php" class="btn btn-outline-danger w-100 mb-3 py-2 fw-semibold" style="background-color: var(--bs-danger-bg-subtle);">
                                <i class="fa-solid fa-minus-circle me-2"></i> Add Expense
                            </a>
                            <a href="income.php" class="btn btn-outline-success w-100 mb-3 py-2 fw-semibold" style="background-color: var(--bs-success-bg-subtle);">
                                <i class="fa-solid fa-plus-circle me-2"></i> Add Income
                            </a>
                            <a href="transactions.php" class="btn btn-outline-secondary w-100 py-2 fw-semibold" style="background-color: var(--bs-secondary-bg-subtle);">
                                <i class="fa-solid fa-magnifying-glass me-2"></i> Search Records
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>

</body>
</html>