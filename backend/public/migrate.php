<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Database\Connection;
use App\Env;

try {
    Env::load(__DIR__ . '/../.env');
    $pdo = Connection::get();
    
    echo "Connecting to database...<br>";
    
    // 1. Run 001
    echo "Running migration 001_create_schema.sql...<br>";
    $sql1 = file_get_contents(__DIR__ . '/../migrations/001_create_schema.sql');
    $pdo->exec($sql1);
    
    // 2. Run 002
    echo "Running migration 002_add_verification_reset.sql...<br>";
    $sql2 = file_get_contents(__DIR__ . '/../migrations/002_add_verification_reset.sql');
    $pdo->exec($sql2);
    
    // 3. Run 003
    echo "Running migration 003_add_organiser_bank_payments.sql...<br>";
    $sql3 = file_get_contents(__DIR__ . '/../migrations/003_add_organiser_bank_payments.sql');
    $pdo->exec($sql3);
    
    echo "Seeding default data...<br>";
    // We include seed.php to load societies/events automatically
    require_once __DIR__ . '/../migrations/seed.php';
    
    echo "<h3>Migration and Seeding completed successfully!</h3>";
} catch (\Exception $e) {
    echo "<h3>Error running migrations:</h3><pre>" . $e->getMessage() . "</pre>";
}
