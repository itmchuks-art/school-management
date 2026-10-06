<?php
require_once __DIR__ . '/includes/db.php';
echo "<h2>Session Test</h2>";
echo "<p>Session ID: <code>" . session_id() . "</code></p>";
echo "<p>admin_id: <code>" . ($_SESSION['admin_id'] ?? 'NOT SET') . "</code></p>";
echo "<p>user_id: <code>" . ($_SESSION['user_id'] ?? 'NOT SET') . "</code></p>";
echo "<p><a href='login.php?admin=1'>Go log in as admin</a></p>";
echo "<p>After logging in, <a href='session_test.php'>click here again</a>.</p>";