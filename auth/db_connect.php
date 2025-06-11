<?php
// Database connection details
// In a real application, use environment variables for these
$DB_HOST = getenv('DB_HOST') ?: 'localhost';
$DB_NAME = getenv('DB_NAME') ?: 'waster';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') ?: ''; // Replace with your database password if any

// Attempt to connect to the database
try {
    $pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME}", $DB_USER, $DB_PASS);
    // Set the PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // If connection fails, display an error message and exit
    // In a production environment, you might want to log this error instead of displaying it
    die("ERROR: Could not connect. " . $e->getMessage());
}

// Function to get the ID of a role by its name
function getRoleId(PDO $pdo, string $roleName): ?int {
    $stmt = $pdo->prepare("SELECT role_id FROM Roles WHERE role_name = :role_name");
    $stmt->bindParam(':role_name', $roleName, PDO::PARAM_STR);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? (int)$result['role_id'] : null;
}
?>
