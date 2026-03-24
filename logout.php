<?php
// ============================================
// logout.php — User Logout
// ============================================
require_once __DIR__ . '/config.php';

// Clear user session
unset($_SESSION['user_id']);
unset($_SESSION['user_work_id']);
unset($_SESSION['user_name']);

setFlash('success', 'You have been logged out successfully.');
redirect(BASE_URL . '/login.php');
