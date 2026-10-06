<?php
require_once __DIR__ . '/includes/db.php';

/* Clear the session token so another device can log in immediately */
if (!empty($_SESSION['user_id'])) {
    clear_session_token($pdo, (int)$_SESSION['user_id']);
}

unset(
    $_SESSION['user_id'],
    $_SESSION['admin_id'],
    $_SESSION['exam_type'],
    $_SESSION['session_token'],
    $_SESSION['session_started']
);

session_destroy();
header('Location: index.php');
exit;