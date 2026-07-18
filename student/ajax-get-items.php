<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

if (!isLoggedIn()) exit;

$department_id = (int)$_POST['department_id'];
$stmt = $pdo->prepare("SELECT id, item_name FROM clearance_items WHERE department_id = ? ORDER BY item_name");
$stmt->execute([$department_id]);
$items = $stmt->fetchAll();

echo '<option value="">Not related to specific item</option>';
foreach ($items as $item) {
    echo '<option value="' . $item['id'] . '">' . htmlspecialchars($item['item_name']) . '</option>';
}
?>