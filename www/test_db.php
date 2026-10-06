<?php
$host = 'database';
$user = 'root';
$pass = 'tiger';
$db   = 'docker';

echo "<h2>Testing MariaDB Connection (egov-lamp)</h2>";

$conn = @new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    echo "<p style='color:red;'>❌ Connection failed: " . $conn->connect_error . "</p>";
} else {
    echo "<p style='color:green;'>✔ Connected successfully to MariaDB (" . $conn->server_info . ")!</p>";
    $conn->close();
}
