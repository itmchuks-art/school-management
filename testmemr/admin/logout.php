<?php
require_once __DIR__ . '/../includes/db.php';

unset($_SESSION['admin_id'], $_SESSION['admin_name']);
flash('👋 Logged out.', 'info');
redirect(SITE_URL . '/admin/login.php');