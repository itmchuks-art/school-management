<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/db.php';

echo "<h2>Admin Login Diagnostics</h2>";

/* 1. Check admins table */
echo "<h3>1. Admins in database:</h3>";
try {
    $rows = $pdo->query('SELECT id, username, password FROM admins')->fetchAll();
    if (empty($rows)) {
        echo "<p style='color:red'><b>❌ NO ADMINS EXIST!</b> Run the SQL from Step 2.</p>";
    } else {
        echo "<table border='1' cellpadding='6'><tr><th>ID</th><th>Username</th><th>Password</th></tr>";
        foreach ($rows as $r) {
            echo "<tr><td>{$r['id']}</td><td>{$r['username']}</td><td>{$r['password']}</td></tr>";
        }
        echo "</table>";
    }
} catch (Throwable $e) {
    echo "<p style='color:red'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

/* 2. Test the login query */
echo "<h3>2. Test login query (admin / admin123):</h3>";
$st = $pdo->prepare('SELECT * FROM admins WHERE username = ? LIMIT 1');
$st->execute(['admin']);
$a = $st->fetch();

if (!$a) {
    echo "<p style='color:red'>❌ No row found with username 'admin'</p>";
} else {
    echo "<p>✅ Found admin row (id={$a['id']}, username={$a['username']})</p>";
    echo "<p>Stored password: <code>" . htmlspecialchars($a['password']) . "</code></p>";

    $typed = 'admin123';
    $match1 = ($typed === $a['password']);
    $match2 = password_verify($typed, $a['password']);
    echo "<p>Plain match with 'admin123': " . ($match1 ? "✅ YES" : "❌ NO") . "</p>";
    echo "<p>Hashed match with 'admin123': " . ($match2 ? "✅ YES" : "❌ NO") . "</p>";

    if (!$match1 && !$match2) {
        echo "<p style='color:red'><b>❌ Password does not match. Run the SQL from Step 2 to reset.</b></p>";
    }
}

/* 3. Test session */
echo "<h3>3. Session test:</h3>";
$_SESSION['test_key'] = 'test_value';
echo "<p>Session ID: " . session_id() . "</p>";
echo "<p>Session write: " . (isset($_SESSION['test_key']) ? "✅ works" : "❌ failed") . "</p>";

/* 4. Test redirect header */
echo "<h3>4. Path check:</h3>";
echo "<p>Current folder: " . __DIR__ . "</p>";
echo "<p>Includes folder exists: " . (is_dir(__DIR__ . '/includes') ? "✅ yes" : "❌ no") . "</p>";
echo "<p>db.php exists: " . (file_exists(__DIR__ . '/includes/db.php') ? "✅ yes" : "❌ no") . "</p>";
echo "<p>admin/ folder exists: " . (is_dir(__DIR__ . '/admin') ? "✅ yes" : "❌ no") . "</p>";