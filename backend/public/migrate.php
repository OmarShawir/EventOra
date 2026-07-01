<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Database\Connection;
use App\Env;

// Disable output buffering to send output immediately to the browser
if (ob_get_level()) ob_end_clean();
echo "Starting database setup...<br>";
flush();

try {
    Env::load(__DIR__ . '/../.env');
    
    $host = getenv('DB_HOST') ?: 'localhost';
    $port = getenv('DB_PORT') ?: '3306';
    $dbname = getenv('DB_NAME') ?: 'eventora';
    $user = getenv('DB_USER') ?: 'root';
    
    echo "Database Parameters Loaded:<br>";
    echo "- Host: " . htmlspecialchars($host) . "<br>";
    echo "- Port: " . htmlspecialchars($port) . "<br>";
    echo "- Name: " . htmlspecialchars($dbname) . "<br>";
    echo "- User: " . htmlspecialchars($user) . "<br>";
    flush();

    echo "Attempting connection to database (with 3 second timeout)...<br>";
    flush();
    
    // Create connection with a strict timeout of 3 seconds to prevent gateway timeout hang
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $dbname);
    $pdo = new PDO($dsn, $user, getenv('DB_PASS') ?: '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 3 // 3 seconds timeout
    ]);
    
    echo "Connected successfully to database!<br>";
    flush();
    
    // 1. Run 001
    echo "Running migration 001_create_schema.sql...<br>";
    flush();
    $sql1 = file_get_contents(__DIR__ . '/../migrations/001_create_schema.sql');
    $pdo->exec($sql1);
    
    // 2. Run 002
    echo "Running migration 002_add_verification_reset.sql...<br>";
    flush();
    $sql2 = file_get_contents(__DIR__ . '/../migrations/002_add_verification_reset.sql');
    $pdo->exec($sql2);
    
    // 3. Run 003
    echo "Running migration 003_add_organiser_bank_payments.sql...<br>";
    flush();
    $sql3 = file_get_contents(__DIR__ . '/../migrations/003_add_organiser_bank_payments.sql');
    $pdo->exec($sql3);
    
    echo "Seeding default data...<br>";
    flush();
    // We include seed.php to load societies/events automatically
    require_once __DIR__ . '/../migrations/seed.php';
    
    echo "<h3>Migration and Seeding completed successfully!</h3>";
} catch (\Exception $e) {
    echo "<h3>Error running migrations:</h3><pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}
