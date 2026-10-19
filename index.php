<?php
require_once 'config/auth.php';
requireLogin();
require_once 'config/db.php';

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'User';

// Auto-seed Demo Account data if the demo user has no transactions yet
$userEmail = $_SESSION['user_email'] ?? '';
$isDemo = (stripos($userEmail, 'demo') !== false || stripos($userName, 'demo') !== false);

if ($isDemo) {
    try {
        $checkTx = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE user_id = ?");
        $checkTx->execute([$userId]);
        if ((int)$checkTx->fetchColumn() === 0) {
            // Fetch categories dynamically
            $catMap = [];
            $incomeCatDefault = null;
            $expenseCatDefault = null;
            $cStmt = $pdo->query("SELECT id, name, type FROM categories");
            while ($r = $cStmt->fetch()) {
                $catMap[strtolower(trim($r['name']))] = $r['id'];
                if ($r['type'] === 'income' && !$incomeCatDefault) $incomeCatDefault = $r['id'];
                if ($r['type'] === 'expense' && !$expenseCatDefault) $expenseCatDefault = $r['id'];
            }

            // Fetch payment methods dynamically
            $pmMap = [];
            $pmDefault = null;
            $pStmt = $pdo->query("SELECT id, name FROM payment_methods");
            while ($r = $pStmt->fetch()) {
                $pmMap[strtolower(trim($r['name']))] = $r['id'];
                if (!$pmDefault) $pmDefault = $r['id'];
            }

            $getCat = function($n, $type) use ($catMap, $incomeCatDefault, $expenseCatDefault) {
                $k = strtolower(trim($n));
                if (isset($catMap[$k])) return $catMap[$k];
                return ($type === 'income') ? $incomeCatDefault : $expenseCatDefault;
            };

            $getPm = function($n) use ($pmMap, $pmDefault) {
                $k = strtolower(trim($n));
                return $pmMap[$k] ?? $pmDefault;
            };

            $currentDay = (int)date('d');
            $seedData = [
                ['income', 'Monthly Salary', 75000.00, 'Salary', 'Bank Transfer', 0, 1],
                ['income', 'Freelance Web Design', 18500.00, 'Freelance', 'UPI', 0, min($currentDay, 12)],
                ['income', 'Stock Dividend Payout', 4200.00, 'Investments', 'Bank Transfer', 0, min($currentDay, 15)],
                ['expense', 'Apartment Rent', 22000.00, 'Rent & Mortgage', 'Bank Transfer', 0, 2],
                ['expense', 'High-Speed Broadband & Electric', 3450.00, 'Utilities', 'UPI', 0, min($currentDay, 5)],
                ['expense', 'Supermarket Grocery Run', 4850.00, 'Food & Dining', 'Credit Card', 0, min($currentDay, 6)],
                ['expense', 'Dinner with Friends', 2150.00, 'Food & Dining', 'UPI', 0, min($currentDay, 9)],
                ['expense', 'Uber & Metro Commute', 1650.00, 'Transportation', 'UPI', 0, min($currentDay, 11)],
                ['expense', 'Weekly Groceries & Fresh Produce', 3120.00, 'Food & Dining', 'Debit Card', 0, min($currentDay, 14)],
                ['expense', 'Amazon Shopping - Home Essentials', 4500.00, 'Shopping', 'Credit Card', 0, min($currentDay, 16)],
                ['expense', 'Netflix & Spotify Premium', 999.00, 'Entertainment', 'Credit Card', 0, min($currentDay, 17)],
                ['expense', 'Pharmacy & Health Checkup', 1250.00, 'Healthcare', 'UPI', 0, min($currentDay, 18)],
                ['income', 'Monthly Salary', 75000.00, 'Salary', 'Bank Transfer', 1, 1],
                ['income', 'Freelance Consultation', 15000.00, 'Freelance', 'UPI', 1, 14],
                ['expense', 'Apartment Rent', 22000.00, 'Rent & Mortgage', 'Bank Transfer', 1, 2],
                ['expense', 'Electricity & Water Bill', 3100.00, 'Utilities', 'UPI', 1, 5],
                ['expense', 'Groceries & Dining', 11200.00, 'Food & Dining', 'Credit Card', 1, 12],
                ['expense', 'Fuel & Car Maintenance', 4200.00, 'Transportation', 'Debit Card', 1, 18],
                ['expense', 'Weekend Getaway', 6800.00, 'Entertainment', 'Credit Card', 1, 22],
                ['expense', 'Apparel & Tech Gadget', 5900.00, 'Shopping', 'Credit Card', 1, 25],
                ['income', 'Monthly Salary', 75000.00, 'Salary', 'Bank Transfer', 2, 1],
                ['expense', 'Apartment Rent', 22000.00, 'Rent & Mortgage', 'Bank Transfer', 2, 2],
                ['expense', 'Food & Supplies', 9800.00, 'Food & Dining', 'Credit Card', 2, 10],
                ['expense', 'Utilities Bill', 2950.00, 'Utilities', 'UPI', 2, 6],
                ['expense', 'Movie & Dining', 3400.00, 'Entertainment', 'Debit Card', 2, 20]
            ];

            $insTx = $pdo->prepare("INSERT INTO transactions (user_id, category_id, payment_method_id, type, title, amount, transaction_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
            foreach ($seedData as $item) {
                $cId = $getCat($item[3], $item[0]);
                $pId = $getPm($item[4]);
                $date = date('Y-m-d', strtotime("-{$item[5]} month", strtotime(date('Y-m-') . sprintf('%02d', $item[6]))));
                $insTx->execute([$userId, $cId, $pId, $item[0], $item[1], $item[2], $date]);
            }

            // Seed Budgets
            $budgets = [
                ['Food & Dining', 16000.00],
                ['Shopping', 8000.00],
                ['Transportation', 5000.00],
                ['Entertainment', 4000.00],
                ['Utilities', 5000.00]
            ];
            $insB = $pdo->prepare("INSERT INTO budgets (user_id, category_id, month, year, amount) VALUES (?, ?, ?, ?, ?)");
            $curM = (int)date('m');
            $curY = (int)date('Y');
            foreach ($budgets as $b) {
                $cId = $getCat($b[0], 'expense');
                if ($cId) {
                    $insB->execute([$userId, $cId, $curM, $curY, $b[1]]);
                }
            }
        }
    } catch (Exception $e) {
        // Silently continue if seeding fails
    }
}

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
    <title>Dashboard - FinTrack Pro Max</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
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
                <a href="index.php" class="list-group-item list-group-item-action active fw-semibold py-3 px-4 animate-fade-in delay-1">
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
            <h3 class="fw-bold mb-4 text-light animate-fade-in">Dashboard Overview</h3>
            
            <!-- Summary Widgets -->
            <div class="row g-4 mb-5">
                <div class="col-md-3">
                    <div class="card glass-panel card-balance-grad border-0 h-100 animate-fade-in delay-1">
                        <div class="card-body p-4 position-relative overflow-hidden">
                            <i class="fa-solid fa-scale-balanced position-absolute opacity-25" style="font-size: 5rem; right: -10px; bottom: -10px; color: var(--accent-blue);"></i>
                            <p class="text-secondary fw-semibold mb-1">Total Balance</p>
                            <h3 class="fw-bold m-0 <?= $totalBalance >= 0 ? 'text-primary' : 'text-danger' ?>">
                                ₹<?= number_format($totalBalance, 2) ?>
                            </h3>
                        </div>
                    </div>
                </div>
                <!-- Income Card -->
                <div class="col-md-3">
                    <div class="card glass-panel card-income-grad border-0 h-100 animate-fade-in delay-2">
                        <div class="card-body p-4 position-relative overflow-hidden">
                            <i class="fa-solid fa-arrow-trend-up position-absolute opacity-25" style="font-size: 5rem; right: -10px; bottom: -10px; color: var(--accent-green);"></i>
                            <p class="text-secondary fw-semibold mb-1" style="opacity: 0.8;">Total Income</p>
                            <h3 class="fw-bold m-0" style="color: var(--accent-green);">₹<?= number_format($totalIncome, 2) ?></h3>
                        </div>
                    </div>
                </div>
                <!-- Expense Card -->
                <div class="col-md-3">
                    <div class="card glass-panel card-expense-grad border-0 h-100 animate-fade-in delay-3">
                        <div class="card-body p-4 position-relative overflow-hidden">
                            <i class="fa-solid fa-arrow-trend-down position-absolute opacity-25" style="font-size: 5rem; right: -10px; bottom: -10px; color: var(--accent-red);"></i>
                            <p class="text-secondary fw-semibold mb-1" style="opacity: 0.8;">Total Expenses</p>
                            <h3 class="fw-bold m-0" style="color: var(--accent-red);">₹<?= number_format($totalExpense, 2) ?></h3>
                        </div>
                    </div>
                </div>
                <!-- Budget Card -->
                <div class="col-md-3">
                    <div class="card glass-panel card-budget-grad border-0 h-100 animate-fade-in delay-4">
                        <div class="card-body p-4 position-relative overflow-hidden">
                            <i class="fa-solid fa-chart-pie position-absolute opacity-25" style="font-size: 5rem; right: -10px; bottom: -10px; color: var(--accent-orange);"></i>
                            <p class="text-secondary fw-semibold mb-1">Budget Used (<?= date('M') ?>)</p>
                            <h3 class="fw-bold m-0 <?= $budgetColor ?>"><?= $budgetPercent ?>%</h3>
                            <small class="text-secondary">₹<?= number_format($totalBudgetSpent, 2) ?> / ₹<?= number_format($totalBudgetLimit, 2) ?></small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- Recent Transactions Table -->
                <div class="col-md-8">
                    <div class="card glass-panel border-0 h-100 animate-fade-in delay-3">
                        <div class="card-header border-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
                            <h5 class="fw-bold m-0 text-light">Recent Transactions</h5>
                            <a href="transactions.php" class="btn btn-sm btn-outline-primary" style="background: rgba(59, 130, 246, 0.1);">View All</a>
                        </div>
                        <div class="card-body p-4">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle m-0 text-light border-dark">
                                    <tbody>
                                        <?php if (empty($recentTransactions)): ?>
                                            <tr>
                                                <td colspan="4" class="text-center py-4 text-secondary">No transactions yet. Start adding some!</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($recentTransactions as $t): ?>
                                                <tr>
                                                    <td class="border-secondary">
                                                        <div class="d-flex align-items-center">
                                                            <div class="icon-circle me-3 <?= $t['type'] === 'income' ? 'icon-income' : 'icon-expense' ?>">
                                                                <i class="fa-solid <?= $t['type'] === 'income' ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' ?>"></i>
                                                            </div>
                                                            <div>
                                                                <h6 class="m-0 fw-semibold text-light"><?= htmlspecialchars($t['title']) ?></h6>
                                                                <small class="text-secondary"><?= htmlspecialchars($t['category_name'] ?? 'Uncategorized') ?></small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="text-secondary border-secondary"><?= date('d M Y', strtotime($t['transaction_date'])) ?></td>
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

                <!-- Quick Actions Widget -->
                <div class="col-md-4">
                    <div class="card glass-panel border-0 h-100 animate-fade-in delay-4">
                        <div class="card-header border-0 pt-4 pb-0">
                            <h5 class="fw-bold m-0 text-light">Quick Actions</h5>
                        </div>
                        <div class="card-body p-4 d-flex flex-column justify-content-center">
                            <a href="expenses.php" class="btn w-100 mb-3 py-3 fw-semibold text-light" style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(239, 68, 68, 0.4)); border: 1px solid rgba(239, 68, 68, 0.3);">
                                <i class="fa-solid fa-minus-circle me-2"></i> Add Expense
                            </a>
                            <a href="income.php" class="btn w-100 mb-3 py-3 fw-semibold text-light" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(16, 185, 129, 0.4)); border: 1px solid rgba(16, 185, 129, 0.3);">
                                <i class="fa-solid fa-plus-circle me-2"></i> Add Income
                            </a>
                            <a href="transactions.php" class="btn w-100 py-3 fw-semibold text-light" style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.2), rgba(59, 130, 246, 0.4)); border: 1px solid rgba(59, 130, 246, 0.3);">
                                <i class="fa-solid fa-magnifying-glass me-2"></i> Search Records
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>

<!-- Three.js for 3D Background -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<script src="app3d.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>