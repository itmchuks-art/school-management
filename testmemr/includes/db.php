<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ============================================================
   AUTO-DETECT SITE URL
   ============================================================ */
$__here    = str_replace('\\', '/', __DIR__);
$__docroot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');

if ($__docroot && strpos($__here, $__docroot) === 0) {
    $__rel = substr($__here, strlen($__docroot));
    $__rel = dirname($__rel);
    if ($__rel === '/' || $__rel === '.' || $__rel === '\\') $__rel = '';
    define('SITE_URL', $__rel);
} else {
    define('SITE_URL', '/testmemr');
}

/* ============================================================
   DATABASE SETTINGS
   ============================================================ */
$DB_NAME_PLAIN = 'testmemr';
$DB_PASS       = 'novirus123';
$DB_HOSTS      = ['localhost', '127.0.0.1'];

$acc = @get_current_user();

$DB_USERS = [
    'root',
    $DB_NAME_PLAIN,
    $acc ? $acc . '_' . $DB_NAME_PLAIN : '',
    $acc,
];
$DB_USERS = array_values(array_unique(array_filter($DB_USERS)));

$DB_PASSWORDS = array_values(array_unique(array_filter([
    '',
    $DB_PASS,
    'root',
])));

$pdo = null;
$tried = [];

foreach ($DB_HOSTS as $h) {
    foreach ($DB_USERS as $u) {
        foreach ($DB_PASSWORDS as $p) {
            $tried[] = "$u@$h" . ($p === '' ? ' (no pass)' : '');
            try {
                $pdo = new PDO(
                    "mysql:host=$h;dbname=$DB_NAME_PLAIN;charset=utf8mb4",
                    $u, $p,
                    [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES   => false,
                    ]
                );
                break 3;
            } catch (PDOException $e) { }
        }
    }
}

if (!$pdo) {
    http_response_code(500);
    echo "<h1 style='color:#b91c1c;font-family:sans-serif'>⚠️ Could not connect to MySQL</h1>";
    echo "<p style='font-family:sans-serif'>Tried: <code>" . htmlspecialchars(implode(', ', $tried)) . "</code></p>";
    exit;
}

/* ============================================================
   APP CONFIGURATION
   ============================================================ */
define('REQUIRE_SUBSCRIPTION', true);
define('AUTO_APPROVE_PAYMENTS', true);

define('CLASS_LEVELS', [
    'NAT Grade 3',
    'NAT Grade 6',
    'NAT Grade 8',
    'GABECE'
]);

define('MAIN_EXAMS', ['NAT', 'GABECE']);

define('EXAM_TYPES', [
    'NAT Grade 3',
    'NAT Grade 6',
    'NAT Grade 8',
    'GABECE'
]);

/* ============================================================
   SUBSCRIPTION PLANS
   ------------------------------------------------------------
   💰 PRICING (One Grade only)
     • Daily     = D25
     • Weekly    = D75
     • Monthly   = D225
   ============================================================ */
define('PLANS', [

    'single_day' => [
        'days'           => 1,
        'amount'         => 25,
        'label'          => 'D25 — 1 Grade · 1 Day',
        'type'           => 'single',
        'duration_label' => '1 Day',
    ],
    'single_week' => [
        'days'           => 7,
        'amount'         => 75,
        'label'          => 'D75 — 1 Grade · 1 Week',
        'type'           => 'single',
        'duration_label' => '1 Week',
    ],
    'single_month' => [
        'days'           => 30,
        'amount'         => 225,
        'label'          => 'D225 — 1 Grade · 1 Month',
        'type'           => 'single',
        'duration_label' => '1 Month',
    ],
]);

/* ============================================================
   HELPERS
   ============================================================ */

function e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void {
    if (strpos($url, 'http://') !== 0 && strpos($url, 'https://') !== 0) {
        if (SITE_URL !== '' && strpos($url, SITE_URL) === 0) {
            /* already has /testmemr */
        } else {
            $url = rtrim(SITE_URL, '/') . '/' . ltrim($url, '/');
        }
    }

    if (!headers_sent()) {
        header('Location: ' . $url);
    } else {
        echo "<script>window.location=" . json_encode($url) . ";</script>";
    }
    exit;
}

function flash(string $msg, string $type = 'success'): void {
    $_SESSION['flash'][] = ['m' => $msg, 't' => $type];
}

function take_flashes(): array {
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* ============================================================
   SESSION LOCK — one device per account
   ============================================================ */

function detect_device(): string {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    if (preg_match('/\((.*?)\)/', $ua, $m)) {
        $parts  = explode(';', $m[1]);
        $os     = trim($parts[0] ?? '');
        $device = trim($parts[1] ?? '');
        $str    = trim($os . ($device ? ' · ' . $device : ''));
        if ($str !== '') return substr($str, 0, 250);
    }
    return substr($ua, 0, 250);
}

function generate_session_token(PDO $pdo, int $user_id): string {
    $token  = bin2hex(random_bytes(32));
    $device = detect_device();

    $pdo->prepare('UPDATE users
        SET session_token = ?, session_started = NOW(), device_info = ?
        WHERE id = ?')
        ->execute([$token, $device, $user_id]);

    $_SESSION['session_token']   = $token;
    $_SESSION['session_started'] = time();
    return $token;
}

function clear_session_token(PDO $pdo, int $user_id): void {
    $pdo->prepare('UPDATE users SET session_token = NULL, session_started = NULL WHERE id = ?')
        ->execute([$user_id]);
}

/**
 * Called on every protected page.
 * If another device has logged in since this session started → kick them out.
 */
function check_session_lock(PDO $pdo): void {
    if (empty($_SESSION['user_id'])) return;

    /* Session was created before token lock existed — force re-login */
    if (empty($_SESSION['session_token'])) {
        unset($_SESSION['user_id'], $_SESSION['user_name']);
        flash('Please log in again.', 'warning');
        redirect(SITE_URL . '/login.php');
    }

    static $already_checked = false;
    if ($already_checked) return;
    $already_checked = true;

    $st = $pdo->prepare('SELECT session_token FROM users WHERE id = ? LIMIT 1');
    $st->execute([(int)$_SESSION['user_id']]);
    $row = $st->fetch();

    if (!$row) return;

    /* If the token in DB doesn't match → someone else logged in */
    if (!empty($row['session_token']) && $row['session_token'] !== $_SESSION['session_token']) {
        unset(
            $_SESSION['user_id'],
            $_SESSION['user_name'],
            $_SESSION['session_token'],
            $_SESSION['session_started']
        );
        flash('⚠️ This account is already open on another device. If this is not you, someone is using your account.', 'danger');
        redirect(SITE_URL . '/login.php');
    }
}

/* ============================================================
   AUTH
   ============================================================ */

function current_user(PDO $pdo): ?array {
    static $loaded = false, $user = null;
    if ($loaded) return $user;
    $loaded = true;
    if (empty($_SESSION['user_id'])) return null;
    $st = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $st->execute([(int)$_SESSION['user_id']]);
    $user = $st->fetch() ?: null;
    return $user;
}

function require_student(PDO $pdo): array {
    /* Session lock check first */
    check_session_lock($pdo);

    $u = current_user($pdo);
    if (!$u) {
        unset($_SESSION['user_id'], $_SESSION['user_name']);
        flash('Please log in to continue.', 'warning');
        redirect(SITE_URL . '/login.php');
    }
    return $u;
}

function require_admin(): void {
    if (empty($_SESSION['admin_id'])) {
        flash('Please log in as admin.', 'warning');
        redirect(SITE_URL . '/admin/login.php');
    }
}

function is_subscribed(?array $user): bool {
    if (!$user || empty($user['subscription_end'])) return false;
    return strtotime($user['subscription_end']) > time();
}

function sub_days_left(?array $user): int {
    if (!is_subscribed($user)) return 0;
    return (int)ceil((strtotime($user['subscription_end']) - time()) / 86400);
}

function user_can_access_grade(?array $user, string $grade): bool {
    if (!$user || !is_subscribed($user)) return false;
    $plan = $user['subscription_plan'] ?? '';

    if (strpos($plan, 'all_') === 0) return true;

    if (strpos($plan, 'single_') === 0) {
        if (empty($user['subscribed_grades'])) return false;
        $grades = array_map('trim', explode(',', $user['subscribed_grades']));
        return in_array($grade, $grades, true);
    }

    return false;
}

function user_can_access_subject(?array $user, int $subject_id, string $grade): bool {
    return user_can_access_grade($user, $grade);
}

function fmt_date(?string $d): string {
    return $d ? date('d M Y, g:ia', strtotime($d)) : '—';
}

function qtext(string $s, int $len = 70): string {
    $s = trim(preg_replace('/\s+/', ' ', $s));
    return mb_strlen($s) > $len ? mb_substr($s, 0, $len) . '…' : $s;
}