<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/db.php';

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Setup</title>";
echo "<style>body{font-family:system-ui,sans-serif;background:#f5f7ff;padding:20px;line-height:1.6}";
echo ".box{max-width:720px;margin:auto;background:#fff;padding:24px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.08)}";
echo "h1{color:#1e40af}code{background:#f3f4f6;padding:2px 6px;border-radius:4px}";
echo ".ok{color:#059669;font-weight:600}.err{color:#dc2626;font-weight:600}";
echo "table{width:100%;border-collapse:collapse;margin:12px 0}";
echo "th,td{padding:8px;border-bottom:1px solid #e5e7eb;text-align:left;font-size:14px}";
echo "th{background:#f9fafb}";
echo "</style></head><body><div class='box'>";
echo "<h1>🔧 Test Me Mr — Database Setup</h1>";

/* ---------- STEP 1: Create tables if missing ---------- */

$tables = [];

$tables['users'] = "CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  phone VARCHAR(20) NOT NULL,
  class_level VARCHAR(20) NOT NULL,
  subscription_end DATETIME DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$tables['subjects'] = "CREATE TABLE IF NOT EXISTS subjects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  class_level VARCHAR(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$tables['questions'] = "CREATE TABLE IF NOT EXISTS questions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  subject_id INT NOT NULL,
  class_level VARCHAR(20) NOT NULL,
  exam_type VARCHAR(20) DEFAULT 'NAT1',
  year VARCHAR(10) DEFAULT NULL,
  question LONGTEXT NOT NULL,
  option_a TEXT, option_b TEXT, option_c TEXT, option_d TEXT,
  correct_index INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$tables['attempts'] = "CREATE TABLE IF NOT EXISTS attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  subject_id INT NOT NULL,
  score INT NOT NULL,
  total INT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$tables['payments'] = "CREATE TABLE IF NOT EXISTS payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  plan VARCHAR(20) NOT NULL,
  amount INT NOT NULL,
  method VARCHAR(20) NOT NULL,
  reference VARCHAR(100) NOT NULL,
  screenshot VARCHAR(255) DEFAULT NULL,
  status VARCHAR(20) DEFAULT 'pending',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$tables['admins'] = "CREATE TABLE IF NOT EXISTS admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

echo "<h2>1. Creating tables…</h2><table><tr><th>Table</th><th>Result</th></tr>";
foreach ($tables as $name => $sql) {
    try {
        $pdo->exec($sql);
        echo "<tr><td><code>$name</code></td><td class='ok'>✅ OK</td></tr>";
    } catch (Throwable $e) {
        echo "<tr><td><code>$name</code></td><td class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</td></tr>";
    }
}
echo "</table>";

/* ---------- STEP 2: Seed default admin ---------- */

echo "<h2>2. Default admin account…</h2>";
$has = $pdo->query("SELECT COUNT(*) FROM admins WHERE username='admin'")->fetchColumn();
if (!$has) {
    $pdo->prepare("INSERT INTO admins (username, password) VALUES ('admin', 'admin123')")->execute();
    echo "<p class='ok'>✅ Created admin / admin123</p>";
} else {
    echo "<p class='ok'>✅ admin already exists</p>";
}

/* ---------- STEP 3: Seed subjects if empty ---------- */

echo "<h2>3. Default subjects…</h2>";
$count = (int)$pdo->query("SELECT COUNT(*) FROM subjects")->fetchColumn();
if ($count === 0) {
    $defaults = [
        ['English','Grade 7'],['Maths','Grade 7'],['SES','Grade 7'],['Science','Grade 7'],
        ['English','Grade 8'],['Maths','Grade 8'],['SES','Grade 8'],['Science','Grade 8'],
        ['English','Grade 9'],['Maths','Grade 9'],['SES','Grade 9'],['Science','Grade 9'],
    ];
    $ins = $pdo->prepare("INSERT INTO subjects (name, class_level) VALUES (?,?)");
    foreach ($defaults as $d) $ins->execute($d);
    echo "<p class='ok'>✅ Inserted " . count($defaults) . " subjects</p>";
} else {
    echo "<p class='ok'>✅ $count subjects already present</p>";
}

/* ---------- STEP 4: Verify users table is writable ---------- */

echo "<h2>4. Test insert into <code>users</code>…</h2>";
try {
    $pdo->prepare("INSERT INTO users (name, phone, class_level) VALUES ('__test__', '0000000', 'Grade 7')")->execute();
    $tid = (int)$pdo->lastInsertId();
    $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$tid]);
    echo "<p class='ok'>✅ Insert + delete works. Table is healthy.</p>";
} catch (Throwable $e) {
    echo "<p class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</p>";
}

/* ---------- STEP 5: Table counts ---------- */

echo "<h2>5. Current data</h2><table><tr><th>Table</th><th>Rows</th></tr>";
foreach (['users','subjects','questions','attempts','payments','admins'] as $t) {
    try {
        $c = (int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        echo "<tr><td><code>$t</code></td><td>$c</td></tr>";
    } catch (Throwable $e) {
        echo "<tr><td><code>$t</code></td><td class='err'>error</td></tr>";
    }
}
echo "</table>";

echo "<h2 style='color:#059669'>🎉 Setup complete!</h2>";
echo "<p><b>Next:</b></p>";
echo "<ol>";
echo "<li>Test registration: <a href='register.php'>register.php</a></li>";
echo "<li>Test admin login: <a href='login.php?admin=1'>login.php?admin=1</a> (admin / admin123)</li>";
echo "</ol>";
echo "<p style='background:#fef3c7;padding:12px;border-radius:8px;margin-top:20px'>";
echo "<b>⚠️ Delete this file when you're done:</b> <code>setup.php</code>";
echo "</p>";
echo "</div></body></html>";