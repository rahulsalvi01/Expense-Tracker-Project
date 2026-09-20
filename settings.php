<?php
require_once 'config/auth.php';
requireLogin();
require_once 'config/db.php';

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'User';
$userEmail = $_SESSION['user_email'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - FinTrack Pro Max</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .theme-switch {
            width: 60px;
            height: 30px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            position: relative;
            cursor: pointer;
            border: 1px solid var(--glass-border);
            display: inline-block;
        }
        .theme-switch .toggle-btn {
            width: 24px;
            height: 24px;
            background: var(--accent-purple);
            border-radius: 50%;
            position: absolute;
            top: 2px;
            left: 3px;
            transition: all 0.3s ease;
        }
        body.light-mode .theme-switch .toggle-btn {
            left: 31px;
            background: var(--accent-blue);
        }
    </style>
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
            <span class="me-3 fw-semibold text-light" id="nav-user-name">
                <i class="fa-solid fa-circle-user fs-4 me-2 align-middle text-primary"></i> <?= htmlspecialchars($userName) ?>
            </span>
            <a href="login.php" class="btn btn-sm btn-outline-danger" style="border-radius: 8px;">Logout</a>
        </div>
    </div>
</nav>

<div class="container-fluid" style="margin-top: 70px;">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2 glass-panel min-vh-100 shadow-sm border-end py-4 px-2" style="position: fixed; top: 70px; height: calc(100vh - 70px); z-index: 10;">
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
                <a href="reports.php" class="list-group-item list-group-item-action fw-semibold py-3 px-4 animate-fade-in delay-4">
                    <i class="fa-solid fa-file-invoice me-3 w-20px"></i> Reports
                </a>
                <a href="settings.php" class="list-group-item list-group-item-action active fw-semibold py-3 px-4 animate-fade-in delay-4">
                    <i class="fa-solid fa-gear me-3 w-20px"></i> Settings
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-10 py-4 px-5 offset-md-2" style="position: relative; z-index: 5;">
            <h3 class="fw-bold mb-4 text-light animate-fade-in">Settings</h3>
            
            <div class="row g-4">
                <div class="col-md-6">
                    <!-- Profile Edit Card -->
                    <div class="card glass-panel border-0 animate-fade-in delay-1">
                        <div class="card-header border-0 pt-4 pb-0">
                            <h5 class="fw-bold m-0 text-light"><i class="fa-solid fa-user-edit me-2"></i> Edit Profile</h5>
                        </div>
                        <div class="card-body p-4">
                            <div id="profileAlert" class="alert d-none" role="alert"></div>
                            
                            <form id="profileForm">
                                <div class="mb-3">
                                    <label class="form-label text-secondary small fw-semibold">Full Name</label>
                                    <input type="text" class="form-control" name="name" id="name" value="<?= htmlspecialchars($userName) ?>" required>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label text-secondary small fw-semibold">Email Address</label>
                                    <input type="email" class="form-control" name="email" id="email" value="<?= htmlspecialchars($userEmail) ?>" required>
                                </div>
                                <button type="submit" class="btn btn-primary" style="background: linear-gradient(135deg, var(--accent-blue), var(--accent-purple)); border: none;">Save Changes</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <!-- Preferences Card -->
                    <div class="card glass-panel border-0 animate-fade-in delay-2">
                        <div class="card-header border-0 pt-4 pb-0">
                            <h5 class="fw-bold m-0 text-light"><i class="fa-solid fa-palette me-2"></i> Preferences</h5>
                        </div>
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center p-3 rounded" style="background: var(--glass-bg); border: 1px solid var(--glass-border);">
                                <div>
                                    <h6 class="mb-1 text-light">Theme Mode</h6>
                                    <small class="text-secondary">Toggle between Dark and Light mode</small>
                                </div>
                                <div class="theme-switch" id="themeToggleBtn">
                                    <div class="toggle-btn"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<script src="app.js"></script>
<script src="app3d.js"></script>

<script>
    $('#profileForm').on('submit', function(e) {
        e.preventDefault();
        $('#profileAlert').addClass('d-none').removeClass('alert-success alert-danger');
        
        $.post('api/auth/update_profile.php', $(this).serialize(), function(response) {
            if(response.status === 'success') {
                $('#profileAlert').removeClass('d-none').addClass('alert-success').text(response.message);
                $('#nav-user-name').html('<i class="fa-solid fa-circle-user fs-4 me-2 align-middle text-primary"></i> ' + $('#name').val());
            } else {
                $('#profileAlert').removeClass('d-none').addClass('alert-danger').text(response.message);
            }
        }, 'json').fail(function() {
            $('#profileAlert').removeClass('d-none').addClass('alert-danger').text('Server error updating profile.');
        });
    });
</script>

</body>
</html>
