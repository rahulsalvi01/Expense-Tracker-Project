<?php
require_once 'config/auth.php';
requireLogin();
require_once 'config/db.php';

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'User'; // Added for the navbar
$error = '';
$success = '';

// Check for session messages
if (isset($_SESSION['success_msg'])) {
    $success = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
}
if (isset($_SESSION['error_msg'])) {
    $error = $_SESSION['error_msg'];
    unset($_SESSION['error_msg']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
}

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $transactionId = $_POST['transaction_id'];
    $stmt = $pdo->prepare("DELETE FROM transactions WHERE id = ? AND user_id = ? AND type = 'expense'");
    if ($stmt->execute([$transactionId, $userId])) {
        $_SESSION['success_msg'] = "Expense deleted successfully!";
    } else {
        $_SESSION['error_msg'] = "Failed to delete expense.";
    }
    header("Location: expenses.php");
    exit;
}

// Handle Create (Add) & Update (Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['add', 'edit'])) {
    $title = trim($_POST['title']);
    $amount = $_POST['amount'];
    $categoryId = $_POST['category_id'];
    $paymentMethodId = $_POST['payment_method_id'];
    $date = $_POST['transaction_date'];

    if (empty($title) || empty($amount) || empty($categoryId) || empty($date)) {
        $error = "Please fill in all required fields.";
    } elseif (!is_numeric($amount) || $amount <= 0) {
        $error = "Amount must be greater than zero.";
    } else {
        if ($_POST['action'] === 'add') {
            $stmt = $pdo->prepare("INSERT INTO transactions (user_id, category_id, payment_method_id, type, title, amount, transaction_date) VALUES (?, ?, ?, 'expense', ?, ?, ?)");
            $successMsg = "Expense added successfully!";
            $executeParams = [$userId, $categoryId, $paymentMethodId, $title, $amount, $date];
        } else {
            $transactionId = $_POST['transaction_id'];
            $stmt = $pdo->prepare("UPDATE transactions SET category_id = ?, payment_method_id = ?, title = ?, amount = ?, transaction_date = ? WHERE id = ? AND user_id = ? AND type = 'expense'");
            $successMsg = "Expense updated successfully!";
            $executeParams = [$categoryId, $paymentMethodId, $title, $amount, $date, $transactionId, $userId];
        }
        
        if ($stmt->execute($executeParams)) {
            $_SESSION['success_msg'] = $successMsg;
            header("Location: expenses.php");
            exit;
        } else {
            $error = "Database operation failed.";
        }
    }
}

// Fetch Categories and Payment Methods
$catStmt = $pdo->query("SELECT id, name FROM categories WHERE type = 'expense'");
$categories = $catStmt->fetchAll();

$payStmt = $pdo->query("SELECT id, name FROM payment_methods");
$paymentMethods = $payStmt->fetchAll();

// Handle Edit Mode
$editMode = false;
$editData = [
    'id' => '', 'title' => '', 'amount' => '', 'transaction_date' => date('Y-m-d'), 
    'category_id' => '', 'payment_method_id' => ''
];

if (isset($_GET['edit_id'])) {
    $editStmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ? AND user_id = ? AND type = 'expense'");
    $editStmt->execute([$_GET['edit_id'], $userId]);
    $fetchedData = $editStmt->fetch();
    
    if ($fetchedData) {
        $editMode = true;
        $editData = $fetchedData;
    }
}

// Handle Read (Fetch all user's expenses)
$expStmt = $pdo->prepare("SELECT t.id, t.title, t.amount, t.transaction_date, c.name as category_name, p.name as payment_name 
                          FROM transactions t 
                          JOIN categories c ON t.category_id = c.id 
                          LEFT JOIN payment_methods p ON t.payment_method_id = p.id 
                          WHERE t.user_id = ? AND t.type = 'expense' 
                          ORDER BY t.transaction_date DESC");
$expStmt->execute([$userId]);
$expenses = $expStmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expenses - FinTrack Pro Max</title>
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
        <div class="col-md-2 glass-panel min-vh-100 shadow-sm border-end py-4 px-2 d-none d-md-block" style="position: fixed; top: 70px; height: calc(100vh - 70px); z-index: 10;">
            <div class="list-group list-group-flush rounded-0 px-2">
                <a href="index.php" class="list-group-item list-group-item-action fw-semibold py-3 px-4 animate-fade-in delay-1">
                    <i class="fa-solid fa-house me-3 w-20px"></i> Dashboard
                </a>
                <a href="transactions.php" class="list-group-item list-group-item-action fw-semibold py-3 px-4 animate-fade-in delay-1">
                    <i class="fa-solid fa-list me-3 w-20px"></i> Transactions
                </a>
                <!-- This link is ACTIVE because we are on Expenses -->
                <a href="expenses.php" class="list-group-item list-group-item-action active fw-semibold py-3 px-4 animate-fade-in delay-2">
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
        <div class="col-md-10 py-4 px-3 px-md-5 offset-md-2 pb-mobile-nav" style="position: relative; z-index: 5;">
            <div class="d-flex justify-content-between align-items-center mb-4 animate-fade-in">
                <h2 class="fw-bold text-light"><i class="fa-solid fa-arrow-trend-down text-danger me-2"></i> My Expenses</h2>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger animate-fade-in"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success animate-fade-in"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- Form Panel -->
                <div class="col-lg-4">
                    <div class="card glass-panel border-0 animate-fade-in delay-1">
                        <div class="card-header border-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
                            <h5 class="fw-bold m-0 text-light"><?= $editMode ? 'Edit Expense' : 'Add New Expense' ?></h5>
                            <?php if ($editMode): ?>
                                <a href="expenses.php" class="btn btn-sm btn-outline-secondary">Cancel</a>
                            <?php endif; ?>
                        </div>
                        <div class="card-body p-4">
                            <form method="POST" onsubmit="this.querySelector('button[type=submit]').disabled = true; this.querySelector('button[type=submit]').innerHTML = 'Saving...';">
                                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                <input type="hidden" name="action" value="<?= $editMode ? 'edit' : 'add' ?>">
                                <?php if ($editMode): ?>
                                    <input type="hidden" name="transaction_id" value="<?= $editData['id'] ?>">
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-semibold text-secondary">Title</label>
                                    <input type="text" name="title" class="form-control bg-transparent text-light border-secondary" placeholder="e.g. Lunch" value="<?= htmlspecialchars($editData['title']) ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-semibold text-secondary">Amount (₹)</label>
                                    <input type="number" step="0.01" name="amount" class="form-control bg-transparent text-light border-secondary" value="<?= htmlspecialchars($editData['amount']) ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-semibold text-secondary">Date</label>
                                    <input type="date" name="transaction_date" class="form-control bg-transparent text-light border-secondary" value="<?= htmlspecialchars($editData['transaction_date']) ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold text-secondary">Category</label>
                                    <select name="category_id" class="form-select bg-transparent text-light border-secondary" required>
                                        <option class="text-dark" value="">Select Category</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option class="text-dark" value="<?= $cat['id'] ?>" <?= $editData['category_id'] == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label fw-semibold text-secondary">Payment Method</label>
                                    <select name="payment_method_id" class="form-select bg-transparent text-light border-secondary" required>
                                        <?php foreach ($paymentMethods as $pm): ?>
                                            <option class="text-dark" value="<?= $pm['id'] ?>" <?= $editData['payment_method_id'] == $pm['id'] ? 'selected' : '' ?>><?= htmlspecialchars($pm['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <button type="submit" class="btn <?= $editMode ? 'btn-outline-primary' : 'btn-outline-danger' ?> w-100 fw-semibold py-2">
                                    <?= $editMode ? 'Update Expense' : 'Save Expense' ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Table Panel -->
                <div class="col-lg-8">
                    <div class="card glass-panel border-0 h-100 animate-fade-in delay-2">
                        <div class="card-body p-4">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle text-light border-dark">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Title</th>
                                            <th>Category</th>
                                            <th>Method</th>
                                            <th class="text-end">Amount</th>
                                            <th class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($expenses)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center py-4 text-secondary">No expenses found. Start adding some!</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($expenses as $exp): ?>
                                                <tr>
                                                    <td class="border-secondary"><?= date('d M Y', strtotime($exp['transaction_date'])) ?></td>
                                                    <td class="fw-semibold border-secondary"><?= htmlspecialchars($exp['title']) ?></td>
                                                    <td class="border-secondary"><span class="badge bg-secondary"><?= htmlspecialchars($exp['category_name']) ?></span></td>
                                                    <td class="border-secondary"><?= htmlspecialchars($exp['payment_name']) ?></td>
                                                    <td class="text-end text-danger fw-bold border-secondary">₹<?= number_format($exp['amount'], 2) ?></td>
                                                    <td class="text-center border-secondary">
                                                        <a href="expenses.php?edit_id=<?= $exp['id'] ?>" class="btn btn-sm btn-outline-primary me-1" title="Edit">
                                                            <i class="fa-solid fa-pen-to-square"></i>
                                                        </a>
                                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this expense?');">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                                            <input type="hidden" name="transaction_id" value="<?= $exp['id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                                <i class="fa-solid fa-trash"></i>
                                                            </button>
                                                        </form>
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
            </div>
        </div>
    </div>
</div>

<!-- Bottom Navigation for Mobile -->
<div class="bottom-nav d-md-none glass-panel">
    <a href="index.php" class="bottom-nav-item">
        <i class="fa-solid fa-house"></i>
        <span>Home</span>
    </a>
    <a href="transactions.php" class="bottom-nav-item">
        <i class="fa-solid fa-list"></i>
        <span>History</span>
    </a>
    <a href="expenses.php" class="bottom-nav-item active">
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

<!-- Three.js for 3D Background -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<script src="app3d.js"></script>
</body>
</html>