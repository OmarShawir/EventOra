<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Env;

Env::load(__DIR__ . '/../.env');

// This endpoint runs schema DDL and wipes/reseeds data — it must never be
// reachable without the secret set in MIGRATE_SECRET. If that env var isn't
// configured at all, refuse to run rather than silently allowing anyone who
// finds this URL to reset the database. Checked before anything else so
// the DB parameters printed below never leak to an unauthenticated caller.
$migrateSecret = getenv('MIGRATE_SECRET') ?: '';
$providedKey = $_GET['key'] ?? '';
if ($migrateSecret === '' || !hash_equals($migrateSecret, (string) $providedKey)) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

// Disable output buffering to send output immediately to the browser
if (ob_get_level()) ob_end_clean();
echo "Starting database setup...<br>";
flush();

try {
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

    // Migrations must be safe to re-run against a DB that's already had some
    // or all of them applied (e.g. a prior partial run). MySQL's "IF NOT
    // EXISTS" clause for ADD COLUMN/INDEX needs 8.0.29+, which this server
    // doesn't have, so instead we run each migration file and swallow the
    // specific "already exists" MySQL error codes: 1050 (table exists),
    // 1060 (duplicate column), 1061 (duplicate key/index name). Anything
    // else still aborts the whole request.
    $runMigration = function (string $name) use ($pdo) {
        echo "Running migration {$name}...<br>";
        flush();
        $sql = file_get_contents(__DIR__ . '/../migrations/' . $name);

        // Strip `-- comment` lines before splitting on ';' — these files'
        // doc comments describing the schema contain semicolons of their
        // own (e.g. "...cancelled; "draft" and "ongoing" are left out..."),
        // which would otherwise be mistaken for statement terminators.
        $sql = preg_replace('/--[^\n]*/', '', $sql);

        // Run statement-by-statement (not the whole file in one exec()) so
        // that one already-applied ALTER TABLE doesn't cause a later,
        // still-needed statement in the same file to be skipped too.
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        $alreadyAppliedCodes = [1050, 1060, 1061];
        foreach ($statements as $statement) {
            try {
                $pdo->exec($statement);
            } catch (\PDOException $e) {
                if (in_array((int) $e->errorInfo[1], $alreadyAppliedCodes, true)) {
                    echo "  (already applied, skipping: " . htmlspecialchars($e->getMessage()) . ")<br>";
                    flush();
                    continue;
                }
                throw $e;
            }
        }
    };

    $runMigration('001_create_schema.sql');
    $runMigration('002_add_verification_reset.sql');
    $runMigration('003_add_organiser_bank_payments.sql');

    echo "Seeding default data...<br>";
    flush();
    // We include seed.php to load societies/events automatically
    require_once __DIR__ . '/../migrations/seed.php';
    
    echo "<h3>Migration and Seeding completed successfully!</h3>";
} catch (\Exception $e) {
    echo "<h3>Error running migrations:</h3><pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}
