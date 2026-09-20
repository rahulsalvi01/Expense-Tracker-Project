<?php
require_once '../../config/db.php';
require_once '../../config/auth.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

requireLogin();
$userId = $_SESSION['user_id'];

try {
    $pdo->beginTransaction();

    // Delete user's transactions
    $stmt1 = $pdo->prepare("DELETE FROM transactions WHERE user_id = ?");
    $stmt1->execute([$userId]);

    // Delete user's budgets
    $stmt2 = $pdo->prepare("DELETE FROM budgets WHERE user_id = ?");
    $stmt2->execute([$userId]);

    // Delete the user
    $stmt3 = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt3->execute([$userId]);

    $pdo->commit();

    // Destroy session
    session_unset();
    session_destroy();

    echo json_encode(['status' => 'success', 'message' => 'Account deleted successfully.']);
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
