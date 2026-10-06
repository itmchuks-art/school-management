<?php
require_once __DIR__ . '/../includes/db.php';

/* Release the device lock */
if (!empty($_SESSION['user_id'])) {
    clear_session_token($pdo, (int)$_SESSION['user_id']);
}

unset(
    $_SESSION['user_id'],
    $_SESSION['user_name'],
    $_SESSION['session_token'],
    $_SESSION['session_started']
);

flash('👋 You have been logged out.', 'info');
redirect(SITE_URL . '/login.php');