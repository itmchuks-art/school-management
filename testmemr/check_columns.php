<?php
require_once __DIR__ . '/includes/db.php';

header('Content-Type: text/html; charset=utf-8');
echo "<h2>Current columns in 'users' table</h2><pre>";
$cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll();
foreach ($cols as $c) {
    echo $c['Field'] . " (" . $c['Type'] . ")\n";
}
echo "</pre>";

echo "<h2>Current columns in 'payments' table</h2><pre>";
$cols = $pdo->query("SHOW COLUMNS FROM payments")->fetchAll();
foreach ($cols as $c) {
    echo $c['Field'] . " (" . $c['Type'] . ")\n";
}
echo "</pre>";

echo "<p><b>Do you see 'subscription_plan' and 'subscribed_grades' in the users table? Do you see 'subscribed_grades' in the payments table?</b></p>";