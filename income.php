<?php
require_once 'config/auth.php';
requireLogin();
require_once 'config/db.php';

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'User';
$error = '';
$success = '';

// Check for session messages (from the PRG redirect)
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
    $transactionId = $_POST['transaction_id'];
    $stmt = $pdo->prepare("DELETE FROM transactions WHERE id = ? AND user_id = ? AND type = 'income'");
    if ($stmt->execute([$transactionId, $userId])) {
        $_SESSION['success_msg'] = "Income deleted successfully!";
    } else {
        $_SESSION['error_msg'] = "Failed to delete income.";
    }
    header("Location: income.php");
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
            $stmt = $pdo->prepare("INSERT INTO transactions (user_id, category_id, payment_method_id, type, title, amount, transaction_date) VALUES (?, ?, ?, 'income', ?, ?, ?)");
            $successMsg = "Income added successfully!";
            $executeParams = [$userId, $categoryId, $paymentMethodId, $title, $amount, $date];
        } else {
            $transactionId = $_POST['transaction_id'];
            $stmt = $pdo->prepare("UPDATE transactions SET category_id = ?, payment_method_id = ?, title = ?, amount = ?, transaction_date = ? WHERE id = ? AND user_id = ? AND type = 'income'");
            $successMsg = "Income updated successfully!";
            $executeParams = [$categoryId, $paymentMethodId, $title, $amount, $date, $transactionId, $userId];
        }
        
        if ($stmt->execute($executeParams)) {
            $_SESSION['success_msg'] = $successMsg;
            header("Location: income.php");
            exit;
        } else {
            $error = "Database operation failed.";
        }
    }
}

// Fetch Categories (Filtered for 'income') and Payment Methods
$catStmt = $pdo->query("SELECT id, name FROM categories WHERE type = 'income'");
$categories = $catStmt->fetchAll();

$payStmt = $pdo->query("SELECT id, name FROM payment_methods");
$paymentMethods = $payStmt->fetchAll();

// Handle Edit Mode (Fetch single income data to pre-fill the form)
$editMode = false;
$editData = [
    'id' => '', 'title' => '', 'amount' => '', 'transaction_date' => date('Y-m-d'), 
    'category_id' => '', 'payment_method_id' => ''
];

if (isset($_GET['edit_id'])) {
    $editStmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ? AND user_id = ? AND type = 'income'");
    $editStmt->execute([$_GET['edit_id'], $userId]);
    $fetchedData = $editStmt->fetch();
    
    if ($fetchedData) {
        $editMode = true;
        $editData = $fetchedData;
    }
}

// Handle Read (Fetch all user's income)
$incStmt = $pdo->prepare("SELECT t.id, t.title, t.amount, t.transaction_date, c.name as category_name, p.name as payment_name 
                          FROM transactions t 
                          JOIN categories c ON t.category_id = c.id 
                          LEFT JOIN payment_methods p ON t.payment_method_id = p.id 
                          WHERE t.user_id = ? AND t.type = 'income' 
                          ORDER BY t.transaction_date DESC");
$incStmt->execute([$userId]);
$incomes = $incStmt->fetchAll();
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
                <a href="income.php" class="list-group-item list-group-item-action active fw-semibold border-0 py-3 px-4">
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


    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-dark"><i class="fa-solid fa-arrow-trend-up text-success me-2"></i> My Income</h2>
        <a href="index.php" class="btn btn-outline-secondary">Back to Dashboard</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Form Panel -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white pt-3 pb-0 border-0 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold m-0"><?= $editMode ? 'Edit Income' : 'Add New Income' ?></h5>
                    <?php if ($editMode): ?>
                        <a href="income.php" class="btn btn-sm btn-outline-secondary">Cancel</a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <form method="POST" onsubmit="this.querySelector('button[type=submit]').disabled = true; this.querySelector('button[type=submit]').innerHTML = 'Saving...';">
                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                        <input type="hidden" name="action" value="<?= $editMode ? 'edit' : 'add' ?>">
                        <?php if ($editMode): ?>
                            <input type="hidden" name="transaction_id" value="<?= $editData['id'] ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Title</label>
                            <input type="text" name="title" class="form-control" placeholder="e.g. Salary" value="<?= htmlspecialchars($editData['title']) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Amount (₹)</label>
                            <input type="number" step="0.01" name="amount" class="form-control" value="<?= htmlspecialchars($editData['amount']) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Date</label>
                            <input type="date" name="transaction_date" class="form-control" value="<?= htmlspecialchars($editData['transaction_date']) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Source (Category)</label>
                            <select name="category_id" class="form-select" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= $editData['category_id'] == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Payment Method</label>
                            <select name="payment_method_id" class="form-select" required>
                                <?php foreach ($paymentMethods as $pm): ?>
                                    <option value="<?= $pm['id'] ?>" <?= $editData['payment_method_id'] == $pm['id'] ? 'selected' : '' ?>><?= htmlspecialchars($pm['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" class="btn <?= $editMode ? 'btn-primary' : 'btn-success' ?> w-100 fw-semibold py-2">
                            <?= $editMode ? 'Update Income' : 'Save Income' ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Table Panel -->
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
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
                                <?php if (empty($incomes)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">No income found. Start adding some!</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($incomes as $inc): ?>
                                        <tr>
                                            <td><?= date('d M Y', strtotime($inc['transaction_date'])) ?></td>
                                            <td class="fw-semibold"><?= htmlspecialchars($inc['title']) ?></td>
                                            <td><span class="badge bg-secondary"><?= htmlspecialchars($inc['category_name']) ?></span></td>
                                            <td><?= htmlspecialchars($inc['payment_name']) ?></td>
                                            <td class="text-end text-success fw-bold">₹<?= number_format($inc['amount'], 2) ?></td>
                                            <td class="text-center">
                                                <a href="income.php?edit_id=<?= $inc['id'] ?>" class="btn btn-sm btn-outline-primary me-1" title="Edit">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </a>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this income record?');">
                                                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="transaction_id" value="<?= $inc['id'] ?>">
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
                  </div> <!-- Closes row g-4 -->
        </div> <!-- Closes col-md-10 -->
    </div> <!-- Closes row -->
</div> <!-- Closes container-fluid -->

</body>
</html>