<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Login Check</title>";
echo "<style>body{font-family:system-ui;background:#f5f7ff;padding:20px;line-height:1.7}";
echo ".box{max-width:800px;margin:auto;background:#fff;padding:24px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.08)}";
echo "h1{color:#1e40af}h2{color:#0C1E7F;border-bottom:2px solid #e5e7eb;padding-bottom:6px}";
echo "code{background:#f3f4f6;padding:2px 6px;border-radius:4px;font-size:13px}";
echo ".ok{color:#059669;font-weight:600}.err{color:#dc2626;font-weight:600}.warn{color:#d97706;font-weight:600}";
echo "</style></head><body><div class='box'>";
echo "<h1>🔍 Login System Check</h1>";

/* ========== 1. PHP ========== */
echo "<h2>1. PHP</h2>";
echo "<p>PHP version: <code>" . PHP_VERSION . "</code> ";
if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
    echo "<span class='ok'>✅</span></p>";
} else {
    echo "<span class='err'>❌ Need PHP 8+</span></p>";
}

/* ========== 2. Session ========== */
echo "<h2>2. Session</h2>";
$_SESSION['test_write'] = 'hello_' . time();
echo "<p>Session ID: <code>" . session_id() . "</code></p>";
echo "<p>Session write: ";
if (isset($_SESSION['test_write'])) {
    echo "<span class='ok'>✅ works</span></p>";
} else {
    echo "<span class='err'>❌ failed — sessions not saving!</span></p>";
}
echo "<p>Session save path: <code>" . session_save_path() . "</code></p>";
if (!is_writable(session_save_path() ?: sys_get_temp_dir())) {
    echo "<p class='warn'>⚠️ Session folder is NOT writable — this is likely the problem!</p>";
}

/* ========== 3. Database ========== */
echo "<h2>3. Database</h2>";
try {
    require_once __DIR__ . '/includes/db.php';
    echo "<p class='ok'>✅ Connected</p>";
} catch (Throwable $e) {
    echo "<p class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div></body></html>";
    exit;
}

/* ========== 4. Admin table ========== */
echo "<h2>4. Admin account</h2>";
try {
    $rows = $pdo->query("SELECT id, username, password FROM admins")->fetchAll();
    if (empty($rows)) {
        echo "<p class='err'>❌ NO ADMINS exist. Run the SQL below.</p>";
        echo "<p>Open phpMyAdmin and run:<br>";
        echo "<code>INSERT INTO admins (username, password) VALUES ('admin', 'admin123');</code></p>";
    } else {
        echo "<table style='width:100%;border-collapse:collapse;margin:8px 0'>";
        echo "<tr style='background:#f9fafb'><th style='padding:8px;text-align:left'>ID</th><th style='padding:8px;text-align:left'>Username</th><th style='padding:8px;text-align:left'>Password</th></tr>";
        foreach ($rows as $r) {
            echo "<tr><td style='padding:8px;border-bottom:1px solid #e5e7eb'>{$r['id']}</td>";
            echo "<td style='padding:8px;border-bottom:1px solid #e5e7eb'><code>" . htmlspecialchars($r['username']) . "</code></td>";
            echo "<td style='padding:8px;border-bottom:1px solid #e5e7eb'><code>" . htmlspecialchars($r['password']) . "</code></td></tr>";
        }
        echo "</table>";

        /* Test the exact login query */
        $st = $pdo->prepare('SELECT * FROM admins WHERE username = ? LIMIT 1');
        $st->execute(['admin']);
        $a = $st->fetch();
        if ($a) {
            $matchPlain = ('admin123' === $a['password']);
            $matchHash  = password_verify('admin123', $a['password']);
            echo "<p>Login test (admin / admin123):</p>";
            echo "<ul>";
            echo "<li>Plain text match: " . ($matchPlain ? "<span class='ok'>✅ YES</span>" : "<span class='err'>❌ NO</span>") . "</li>";
            echo "<li>Hashed match: " . ($matchHash ? "<span class='ok'>✅ YES</span>" : "<span class='err'>❌ NO</span>") . "</li>";
            echo "</ul>";
            if (!$matchPlain && !$matchHash) {
                echo "<p class='err'>⚠️ Password does not match. Reset it:<br>";
                echo "<code>UPDATE admins SET password='admin123' WHERE username='admin';</code></p>";
            } else {
                echo "<p class='ok'>✅ Login should work with admin / admin123</p>";
            }
        }
    }
} catch (Throwable $e) {
    echo "<p class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</div></body></html>";