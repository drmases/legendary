<?php
// Database connection test
error_reporting(E_ALL);
ini_set('display_errors', 1);

$db_host = 'globbin.se.mysql';
$db_name = 'globbin_se';
$db_user = 'globbin_se';
$db_pass = '131313';

echo "<h2>Database Connection Test</h2>";
echo "<p>Host: $db_host</p>";
echo "<p>Database: $db_name</p>";
echo "<p>User: $db_user</p>";

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p style='color:green;font-weight:bold;'>SUCCESS! Connected to database.</p>";

    // Test query
    $result = $pdo->query("SHOW TABLES");
    $tables = $result->fetchAll(PDO::FETCH_COLUMN);
    echo "<p>Tables in database: " . (count($tables) > 0 ? implode(", ", $tables) : "None (empty database)") . "</p>";

} catch (PDOException $e) {
    echo "<p style='color:red;font-weight:bold;'>FAILED: " . $e->getMessage() . "</p>";
}
?>
