<?php
require_once 'config/auth.php';
requireLogin();
require_once 'config/db.php';

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'User'; // FIXED: Added missing variable
$error = '';
$success = '';

// FIXED: Added missing CSRF token verification for all POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
}

// Check for session messages (PRG pattern)
if (isset($_SESSION['success_msg'])) {
    $success = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
}
if (isset($_SESSION['error_msg'])) {
    $error = $_SESSION['error_msg'];
    unset($_SESSION['error_msg']);
}

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $budgetId = $_POST['budget_id'];
    $stmt = $pdo->prepare("DELETE FROM budgets WHERE id = ? AND user_id = ?");
    if ($stmt->execute([$budgetId, $userId])) {
        $_SESSION['success_msg'] = "Budget deleted successfully!";
    } else {
        $_SESSION['error_msg'] = "Failed to delete budget.";
    }
    header("Location: budgets.php");
    exit;
}

// Handle Create / Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['add', 'edit'])) {
    $categoryId = $_POST['category_id'];
    $amount = $_POST['amount'];
    
    // HTML5 month input returns YYYY-MM (e.g., "2026-09")
    $budgetMonthInput = $_POST['budget_month']; 
    $parts = explode('-', $budgetMonthInput);
    
    if (count($parts) === 2 && !empty($categoryId) && is_numeric($amount) && $amount > 0) {
        $year = (int)$parts[0];
        $month = (int)$parts[1];
        
        // Check if a budget already exists for this category and month
        $checkStmt = $pdo->prepare("SELECT id FROM budgets WHERE user_id = ? AND category_id = ? AND month = ? AND year = ? AND id != ?");
        $budgetId = $_POST['budget_id'] ?? 0;
        $checkStmt->execute([$userId, $categoryId, $month, $year, $budgetId]);
        
        if ($checkStmt->fetch()) {
            $_SESSION['error_msg'] = "A budget for this category and month already exists.";
            header("Location: budgets.php");
            exit;
        }

        if ($_POST['action'] === 'add') {
            $stmt = $pdo->prepare("INSERT INTO budgets (user_id, category_id, amount, month, year) VALUES (?, ?, ?, ?, ?)");
            if ($stmt->execute([$userId, $categoryId, $amount, $month, $year])) {
                $_SESSION['success_msg'] = "Budget set successfully!";
            }
        } else {
            $stmt = $pdo->prepare("UPDATE budgets SET category_id = ?, amount = ?, month = ?, year = ? WHERE id = ? AND user_id = ?");
            if ($stmt->execute([$categoryId, $amount, $month, $year, $budgetId, $userId])) {
                $_SESSION['success_msg'] = "Budget updated successfully!";
            }
        }
        header("Location: budgets.php");
        exit;
    } else {
        $error = "Please fill in all fields correctly.";
    }
}

// Fetch Expense Categories for the dropdown
$catStmt = $pdo->query("SELECT id, name FROM categories WHERE type = 'expense' ORDER BY name ASC");
$categories = $catStmt->fetchAll();

// Handle Edit Mode Data Fetching
$editMode = false;
$editData = ['id' => '', 'category_id' => '', 'amount' => '', 'budget_month' => date('Y-m')];

if (isset($_GET['edit_id'])) {
    $editStmt = $pdo->prepare("SELECT * FROM budgets WHERE id = ? AND user_id = ?");
    $editStmt->execute([$_GET['edit_id'], $userId]);
    $fetched = $editStmt->fetch();
    if ($fetched) {
        $editMode = true;
        // Format month and year back to YYYY-MM for the HTML input
        $formattedMonth = str_pad($fetched['month'], 2, '0', STR_PAD_LEFT);
        $editData = [
            'id' => $fetched['id'],
            'category_id' => $fetched['category_id'],
            'amount' => $fetched['amount'],
            'budget_month' => $fetched['year'] . '-' . $formattedMonth
        ];
    }
}

// Fetch all budgets WITH their dynamically calculated spent amounts
$sql = "SELECT b.id, b.amount as limit_amount, b.month, b.year, c.name as category_name,
               (SELECT COALESCE(SUM(amount), 0) FROM transactions t 
                WHERE t.category_id = b.category_id 
                  AND t.user_id = b.user_id 
                  AND t.type = 'expense'
                  AND MONTH(t.transaction_date) = b.month 
                  AND YEAR(t.transaction_date) = b.year) as spent_amount
        FROM budgets b
        JOIN categories c ON b.category_id = c.id
        WHERE b.user_id = ?
        ORDER BY b.year DESC, b.month DESC, c.name ASC";

$budgetStmt = $pdo->prepare($sql);
$budgetStmt->execute([$userId]);
$budgets = $budgetStmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FinTrack</title>
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
                <a href="budgets.php" class="list-group-item list-group-item-action active fw-semibold border-0 py-3 px-4">
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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold text-dark"><i class="fa-solid fa-chart-pie text-warning me-2"></i> Monthly Budgets</h2>
               
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- CREATE/EDIT BUDGET FORM -->
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white pt-3 pb-0 border-0 d-flex justify-content-between align-items-center">
                            <h5 class="fw-bold m-0"><?= $editMode ? 'Edit Budget' : 'Set New Budget' ?></h5>
                            <?php if ($editMode): ?>
                                <a href="budgets.php" class="btn btn-sm btn-outline-secondary">Cancel</a>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <form method="POST" onsubmit="this.querySelector('button[type=submit]').disabled = true; this.querySelector('button[type=submit]').innerHTML = 'Saving...';">
                                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                <input type="hidden" name="action" value="<?= $editMode ? 'edit' : 'add' ?>">
                                <?php if ($editMode): ?>
                                    <input type="hidden" name="budget_id" value="<?= $editData['id'] ?>">
                                <?php endif; ?>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Expense Category</label>
                                    <select name="category_id" class="form-select" required>
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?= $cat['id'] ?>" <?= $editData['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($cat['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Month & Year</label>
                                    <input type="month" name="budget_month" class="form-control" value="<?= htmlspecialchars($editData['budget_month']) ?>" required>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label fw-semibold">Maximum Limit (₹)</label>
                                    <input type="number" step="0.01" name="amount" class="form-control" placeholder="e.g. 5000" value="<?= htmlspecialchars($editData['amount']) ?>" required>
                                </div>

                                <button type="submit" class="btn <?= $editMode ? 'btn-primary' : 'btn-warning text-dark' ?> w-100 fw-semibold py-2">
                                    <?= $editMode ? 'Update Budget' : 'Save Budget' ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- BUDGETS DISPLAY -->
                <div class="col-lg-8">
                    <div class="row g-3">
                        <?php if (empty($budgets)): ?>
                            <div class="col-12">
                                <div class="card shadow-sm border-0">
                                    <div class="card-body text-center py-5 text-muted">
                                        <p class="mb-0">You haven't set any budgets yet. Create one to start tracking!</p>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($budgets as $b): 
                                $limit = $b['limit_amount'];
                                $spent = $b['spent_amount'];
                                $percent = ($limit > 0) ? ($spent / $limit) * 100 : 0;
                                
                                if ($percent >= 100) {
                                    $barColor = 'bg-danger';
                                    $textColor = 'text-danger';
                                    $statusText = 'Over Budget';
                                } elseif ($percent >= 75) {
                                    $barColor = 'bg-warning';
                                    $textColor = 'text-warning';
                                    $statusText = 'Near Limit';
                                } else {
                                    $barColor = 'bg-success';
                                    $textColor = 'text-success';
                                    $statusText = 'On Track';
                                }
                                
                                $barWidth = $percent > 100 ? 100 : $percent;
                                
                                $dateObj = DateTime::createFromFormat('!m', $b['month']);
                                $monthName = $dateObj->format('F');
                            ?>
                                <div class="col-md-6">
                                    <div class="card shadow-sm border-0 h-100">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <h6 class="fw-bold mb-0"><?= htmlspecialchars($b['category_name']) ?></h6>
                                                    <small class="text-muted"><?= $monthName ?> <?= $b['year'] ?></small>
                                                </div>
                                                
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-light" type="button" data-bs-toggle="dropdown">
                                                        <i class="fa-solid fa-ellipsis-vertical"></i>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                                        <li><a class="dropdown-item" href="budgets.php?edit_id=<?= $b['id'] ?>"><i class="fa-solid fa-pen text-primary me-2"></i> Edit</a></li>
                                                        <li>
                                                            <form method="POST" onsubmit="return confirm('Delete this budget?');">
                                                                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                                                <input type="hidden" name="action" value="delete">
                                                                <input type="hidden" name="budget_id" value="<?= $b['id'] ?>">
                                                                <button type="submit" class="dropdown-item text-danger"><i class="fa-solid fa-trash me-2"></i> Delete</button>
                                                            </form>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>

                                            <div class="d-flex justify-content-between mb-1 mt-3">
                                                <span class="fw-semibold <?= $textColor ?>">₹<?= number_format($spent, 2) ?> spent</span>
                                                <span class="text-muted small">of ₹<?= number_format($limit, 2) ?></span>
                                            </div>
                                            
                                            <div class="progress" style="height: 10px;">
                                                <div class="progress-bar <?= $barColor ?>" role="progressbar" style="width: <?= $barWidth ?>%;"></div>
                                            </div>
                                            
                                            <div class="mt-2 text-end">
                                                <span class="badge <?= str_replace('bg-', 'bg-', $barColor) ?>-subtle <?= $textColor ?> border-0">
                                                    <?= $statusText ?> (<?= round($percent) ?>%)
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div> <!-- Closes row g-4 -->
        </div> <!-- Closes col-md-10 -->
    </div> <!-- Closes row -->
</div> <!-- Closes container-fluid -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>