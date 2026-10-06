<?php
require_once __DIR__ . '/includes/db.php';

/* Logged in? Send to dashboard or grade picker */
if (!empty($_SESSION['user_id'])) {
    $st = $pdo->prepare('SELECT grade FROM users WHERE id = ? LIMIT 1');
    $st->execute([(int)$_SESSION['user_id']]);
    $u = $st->fetch();

    if ($u) {
        $dest = empty($u['grade'])
            ? SITE_URL . '/student/choose_grade.php'
            : SITE_URL . '/student/dashboard.php';
        redirect($dest);
    }
    unset($_SESSION['user_id'], $_SESSION['user_name']);
}

/* Not logged in → go to login page (nothing else) */
redirect(SITE_URL . '/login.php');