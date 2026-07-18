<?php
// This file redirects to view-certificate.php with print parameter
session_start();
require_once '../../config/config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../../auth/login.php');
}

$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($student_id == 0) {
    die('Invalid student ID.');
}

// Redirect to view-certificate with print parameter
header("Location: view-certificate.php?id=" . $student_id . "&print=1");
exit();