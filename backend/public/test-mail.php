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

$apiKey = getenv('RESEND_API_KEY') ?: '';
echo "RESEND_API_KEY " . ($apiKey ? "set (length " . strlen($apiKey) . ", prefix " . htmlspecialchars(substr($apiKey, 0, 3)) . ")" : "(unset)") . "<br>";
echo "MAIL_FROM=" . htmlspecialchars(getenv('MAIL_FROM') ?: '(unset — defaults to onboarding@resend.dev)') . "<br><br>";

try {
    Mailer::sendVerification($to, 'Test User', 'https://example.com/verify?token=test');
    echo "SUCCESS: mail sent to " . htmlspecialchars($to);
} catch (\Throwable $e) {
    echo "FAILED: " . get_class($e) . ": " . htmlspecialchars($e->getMessage());
}
