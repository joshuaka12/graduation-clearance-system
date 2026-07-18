<?php
session_start();
require_once '../../config/config.php';

if (!isLoggedIn() || !isDepartmentHead()) {
    redirect('../../auth/login.php');
}

$user_id = $_SESSION['user_id'];

// Get department ID
$stmt = $pdo->prepare("SELECT department_id FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch();

if (!$user_data || !$user_data['department_id']) {
    $_SESSION['error'] = "No department assigned.";
    redirect('../../auth/login.php');
}

$department_id = $user_data['department_id'];

// Get item ID from URL
$item_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$item_id) {
    $_SESSION['error'] = "Invalid item ID.";
    redirect('index.php');
}

// Verify item belongs to this department
$stmt = $pdo->prepare("SELECT * FROM clearance_items WHERE id = ? AND department_id = ?");
$stmt->execute([$item_id, $department_id]);
$item = $stmt->fetch();

if (!$item) {
    $_SESSION['error'] = "Item not found.";
    redirect('index.php');
}

// Delete the item
$stmt = $pdo->prepare("DELETE FROM clearance_items WHERE id = ? AND department_id = ?");
$stmt->execute([$item_id, $department_id]);

$_SESSION['success'] = "Item deleted successfully.";
redirect('index.php');
?>