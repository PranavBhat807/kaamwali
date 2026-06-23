<?php
// Prevent direct access to db.php
if (basename($_SERVER['SCRIPT_FILENAME']) == 'db.php') {
    header("HTTP/1.1 403 Forbidden");
    exit("Access denied.");
}

$host = 'localhost';
$username = 'root';
$password = '';
$dbname = 'kaamwali_db';

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Database Connection failed: " . $conn->connect_error);
}

// Start PHP session globally if not started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
