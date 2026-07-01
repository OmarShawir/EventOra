<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Env;
use App\Mailer;

Env::load(__DIR__ . '/../.env');

// Temporary diagnostic endpoint — same secret as migrate.php so it isn't
// left open to the public. Delete once the SMTP issue is resolved.
$migrateSecret = getenv('MIGRATE_SECRET') ?: '';
$providedKey = $_GET['key'] ?? '';
if ($migrateSecret === '' || !hash_equals($migrateSecret, (string) $providedKey)) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$to = $_GET['to'] ?? '';
if (!$to) {
    echo 'Pass ?to=you@example.com';
    exit;
}

echo "EMAIL_HOST=" . htmlspecialchars(getenv('EMAIL_HOST') ?: '(unset)') . "<br>";
echo "EMAIL_PORT=" . htmlspecialchars(getenv('EMAIL_PORT') ?: '(unset)') . "<br>";
echo "EMAIL_USER=" . htmlspecialchars(getenv('EMAIL_USER') ?: '(unset)') . "<br>";
echo "EMAIL_PASS length=" . strlen((string) getenv('EMAIL_PASS')) . "<br><br>";

// Raw connectivity check on both common SMTP ports, independent of Mailer,
// to isolate "outbound port blocked at the network level" from "SMTP/auth
// error" — auth failures only happen after a successful TCP connect.
$host = getenv('EMAIL_HOST') ?: 'smtp.gmail.com';
foreach ([465 => 'ssl://', 587 => 'tcp://', 25 => 'tcp://'] as $port => $proto) {
    $start = microtime(true);
    $sock = @stream_socket_client("{$proto}{$host}:{$port}", $errno, $errstr, 5);
    $ms = round((microtime(true) - $start) * 1000);
    if ($sock) {
        echo "Port {$port}: CONNECTED in {$ms}ms<br>";
        fclose($sock);
    } else {
        echo "Port {$port}: FAILED [{$errno}] {$errstr} ({$ms}ms)<br>";
    }
}
echo "<br>";

try {
    Mailer::sendVerification($to, 'Test User', 'https://example.com/verify?token=test');
    echo "SUCCESS: mail sent to " . htmlspecialchars($to);
} catch (\Throwable $e) {
    echo "FAILED: " . get_class($e) . ": " . htmlspecialchars($e->getMessage());
}
