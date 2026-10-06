<?php
session_start();
echo "<h2>Session Check</h2>";
echo "<p>admin_id: <b>" . ($_SESSION['admin_id'] ?? 'NOT SET') . "</b></p>";
echo "<p>user_id: <b>" . ($_SESSION['user_id'] ?? 'NOT SET') . "</b></p>";
echo "<p>exam_type: <b>" . ($_SESSION['exam_type'] ?? 'NOT SET') . "</b></p>";
echo "<hr>";
echo "<p><a href='login.php?admin=1'>Admin Login</a></p>";
echo "<p><a href='login.php'>Student Login</a></p>";
echo "<p><a href='logout.php'>Logout</a></p>";