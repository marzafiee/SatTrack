<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "Testing database connection...<br><br>";

$host = 'sql105.infinityfree.com';
$user = 'if0_40796132';
$pass = 'CVs6HGofxv53b'; // Get this from control panel
$db = 'if0_40796132_sattrack'; // Replace with EXACT database name from control panel

echo "Host: $host<br>";
echo "User: $user<br>";
echo "Database: $db<br><br>";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
}

echo "✅ Database connected successfully!<br>";
echo "Now testing if tables exist...<br>";

$result = $conn->query("SHOW TABLES");
if ($result) {
    echo "<br>Tables in database:<br>";
    while($row = $result->fetch_array()) {
        echo "- " . $row[0] . "<br>";
    }
}

$conn->close();
?>