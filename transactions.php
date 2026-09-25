<?php
require_once 'config/auth.php';
requireLogin();
require_once 'config/db.php';

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'User'; // Added for the navbar
$success = '';
$error = '';

// Handle Delete Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $transactionId = $_POST['transaction_id'];
    $stmt = $pdo->prepare("DELETE FROM transactions WHERE id = ? AND user_id = ?");
    if ($stmt->execute([$transactionId, $userId])) {
        $success = "Transaction deleted successfully!";
    } else {
        $error = "Failed to delete transaction.";
    }
}

// 1. Setup Pagination Variables
$limit = 10; 
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// 2. Setup Search & Filter Variables
$search = $_GET['search'] ?? '';
$typeFilter = $_GET['type'] ?? '';
$categoryFilter = $_GET['category'] ?? '';

// 3. Build Dynamic SQL Query
$whereClause = "t.user_id = :user_id";
$params = [':user_id' => $userId];

if (!empty($search)) {
    $whereClause .= " AND t.title LIKE :search";
    $params[':search'] = "%$search%";
}
if (!empty($typeFilter)) {
    $whereClause .= " AND t.type = :type";
    $params[':type'] = $typeFilter;
}
if (!empty($categoryFilter)) {
    $whereClause .= " AND t.category_id = :category";
    $params[':category'] = $categoryFilter;
}

// 4. Get Total Rows for Pagination
$countSql = "SELECT COUNT(*) FROM transactions t WHERE $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// 5. Fetch Filtered & Paginated Data
$sql = "SELECT t.id, t.title, t.amount, t.transaction_date, t.type, c.name as category_name, p.name as payment_name 
        FROM transactions t 
        LEFT JOIN categories c ON t.category_id = c.id 
        LEFT JOIN payment_methods p ON t.payment_method_id = p.id 
        WHERE $whereClause 
        ORDER BY t.transaction_date DESC 
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$transactions = $stmt->fetchAll();

// Fetch Categories for the filter dropdown
$catStmt = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
$allCategories = $catStmt->fetchAll();

// Fetch all distinct transaction titles for the Auto-Suggest feature
$titleStmt = $pdo->prepare("SELECT DISTINCT title FROM transactions WHERE user_id = ? ORDER BY title ASC");
$titleStmt->execute([$userId]);
$uniqueTitles = $titleStmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions - FinTrack Pro Max</title>
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
                <a href="index.php" class="list-group-item list-group-item-action fw-semibold py-3 px-4 animate-fade-in delay-1">
                    <i class="fa-solid fa-house me-3 w-20px"></i> Dashboard
                </a>
                <a href="transactions.php" class="list-group-item list-group-item-action active fw-semibold py-3 px-4 animate-fade-in delay-1">
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
            <div class="d-flex justify-content-between align-items-center mb-4 animate-fade-in">
                <h2 class="fw-bold text-light"><i class="fa-solid fa-list text-primary me-2"></i> All Transactions</h2>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger animate-fade-in"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success animate-fade-in"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <!-- FILTER SECTION -->
            <div class="card glass-panel border-0 mb-4 animate-fade-in delay-1">
                <div class="card-body">
                    <form method="GET" action="transactions.php" class="row g-3">
                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-secondary">Search Title</label>
                            <input type="text" id="searchBox" name="search" class="form-control bg-transparent text-light border-secondary" placeholder="Search..." value="<?= htmlspecialchars($search) ?>" autocomplete="off">
                            
                            <datalist id="titleSuggestions">
                                <?php foreach ($uniqueTitles as $titleOption): ?>
                                    <option value="<?= htmlspecialchars($titleOption) ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label fw-semibold text-secondary">Type</label>
                            <select name="type" class="form-select bg-transparent text-light border-secondary">
                                <option class="text-dark" value="">All Types</option>
                                <option class="text-dark" value="income" <?= $typeFilter === 'income' ? 'selected' : '' ?>>Income</option>
                                <option class="text-dark" value="expense" <?= $typeFilter === 'expense' ? 'selected' : '' ?>>Expense</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold text-secondary">Category</label>
                            <select name="category" class="form-select bg-transparent text-light border-secondary">
                                <option class="text-dark" value="">All Categories</option>
                                <?php foreach ($allCategories as $cat): ?>
                                    <option class="text-dark" value="<?= $cat['id'] ?>" <?= $categoryFilter == $cat['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-outline-primary w-100 fw-semibold"><i class="fa-solid fa-filter"></i> Filter</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- TRANSACTIONS TABLE -->
            <div class="card glass-panel border-0 animate-fade-in delay-2">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-light border-dark">
                            <thead>
                                <tr>
                                    <th class="ps-4">Date</th>
                                    <th>Title</th>
                                    <th>Category</th>
                                    <th>Type</th>
                                    <th>Method</th>
                                    <th class="text-end">Amount</th>
                                    <th class="text-center pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($transactions)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-5 text-secondary">No transactions found matching your criteria.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($transactions as $t): ?>
                                        <tr>
                                            <td class="ps-4 border-secondary"><?= date('d M Y', strtotime($t['transaction_date'])) ?></td>
                                            <td class="fw-semibold border-secondary"><?= htmlspecialchars($t['title']) ?></td>
                                            <td class="border-secondary"><span class="badge bg-secondary"><?= htmlspecialchars($t['category_name'] ?? 'N/A') ?></span></td>
                                            <td class="border-secondary">
                                                <?php if ($t['type'] === 'income'): ?>
                                                    <span class="badge bg-success-subtle text-success border border-success">Income</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger-subtle text-danger border border-danger">Expense</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="border-secondary"><?= htmlspecialchars($t['payment_name'] ?? 'N/A') ?></td>
                                            <td class="text-end fw-bold border-secondary <?= $t['type'] === 'income' ? 'text-success' : 'text-danger' ?>">
                                                <?= $t['type'] === 'income' ? '+' : '-' ?>₹<?= number_format($t['amount'], 2) ?>
                                            </td>
                                            <td class="text-center pe-4 border-secondary">
                                                <?php $editPage = $t['type'] === 'income' ? 'income.php' : 'expenses.php'; ?>
                                                <a href="<?= $editPage ?>?edit_id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-primary me-1" title="Edit">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </a>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this transaction?');">
                                                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="transaction_id" value="<?= $t['id'] ?>">
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
                
                <!-- PAGINATION -->
                <?php if ($totalPages > 1): ?>
                    <div class="card-footer bg-transparent border-top border-secondary py-3">
                        <nav>
                            <ul class="pagination justify-content-center mb-0">
                                <?php
                                    $queryStr = $_GET;
                                    unset($queryStr['page']); 
                                    $baseQuery = http_build_query($queryStr);
                                    $baseQuery = $baseQuery ? '&' . $baseQuery : '';
                                ?>
                                
                                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                    <a class="page-link bg-transparent text-primary border-secondary" href="?page=<?= $page - 1 ?><?= $baseQuery ?>">Previous</a>
                                </li>
                                
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                        <a class="page-link <?= ($page == $i) ? 'bg-primary border-primary text-white' : 'bg-transparent text-primary border-secondary' ?>" href="?page=<?= $i ?><?= $baseQuery ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                                    <a class="page-link bg-transparent text-primary border-secondary" href="?page=<?= $page + 1 ?><?= $baseQuery ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Three.js for 3D Background -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<script src="app3d.js"></script>
<script>
    const searchBox = document.getElementById('searchBox');
    
    function manageSuggestions() {
        if (searchBox.value.trim().length > 0) {
            searchBox.setAttribute('list', 'titleSuggestions');
        } else {
            searchBox.removeAttribute('list');
        }
    }

    searchBox.addEventListener('input', manageSuggestions);
    manageSuggestions();
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>