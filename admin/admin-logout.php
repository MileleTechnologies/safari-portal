<?php
// ============================================
// admin/admin-logout.php — Logout
// ============================================
require_once __DIR__ . '/../config.php';
session_destroy();
redirect(BASE_URL . '/admin/admin-login.php');
