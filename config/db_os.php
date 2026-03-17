<?php
$host = "localhost";
$port = "5432";
$dbname = "os2025";
$user = "os2025";
$password = "MANGO123";

try {
    $conn = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname",
        $user,
        $password
    );
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
     echo "Connected successfully!";
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
