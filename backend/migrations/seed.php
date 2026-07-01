<?php

/**
 * Seeds the eventora database with demo data — the same events and
 * societies the Vue frontend ships as mock JSON (src/mock/events.json and
 * src/mock/societies.json), copied here as seed_events.json /
 * seed_societies.json so both sides show identical data during the PR3
 * demo, whether or not the frontend has switched over to live API calls
 * yet.
 *
 * Usage:
 *   php migrations/seed.php
 *
 * Safe to re-run — it truncates existing data first.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Env;

// Reuse the existing $pdo connection when required from migrate.php;
// otherwise load .env and connect standalone (php migrations/seed.php).
if (!isset($pdo)) {
    Env::load(__DIR__ . '/../.env');
    $pdo = \App\Database\Connection::get();
}

$isWeb = (php_sapi_name() !== 'cli');
$nl = $isWeb ? "<br>\n" : "\n";

echo "Connected to database.{$nl}";

// ── Reset tables (children first, to respect FK constraints) ───────────────
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (['checkins', 'feedback', 'tickets', 'events', 'societies', 'users'] as $table) {
    $pdo->exec("TRUNCATE TABLE {$table}");
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
echo "Cleared existing data.{$nl}";

// ── Demo users (one per role, matching the frontend's DEMO_USERS) ──────────
$demoPasswordHash = password_hash('password123', PASSWORD_BCRYPT);

$insertUser = $pdo->prepare(
    'INSERT INTO users (name, email, password_hash, role, society) VALUES (?, ?, ?, ?, ?)'
);

$insertUser->execute(['Ahmad Syafiq', 'attendee@utm.my', $demoPasswordHash, 'attendee', null]);
$attendeeId = (int) $pdo->lastInsertId();

$insertUser->execute(['Ahmad Faris', 'organiser@utm.my', $demoPasswordHash, 'organiser', 'IEEE UTM Student Branch']);
$organiserId = (int) $pdo->lastInsertId();

$insertUser->execute(['Prof. Dr. Razali', 'admin@utm.my', $demoPasswordHash, 'admin', null]);
$adminId = (int) $pdo->lastInsertId();

echo "Seeded 3 demo users (password for all: password123).{$nl}";

// ── Societies ────────────────────────────────────────────────────────────
$societiesJson = json_decode(file_get_contents(__DIR__ . '/seed_societies.json'), true);

$insertSociety = $pdo->prepare(
    'INSERT INTO societies (name, faculty, advisor_id, description, members, founded, cover_url, logo_color)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);

$societyIds = []; // name => id
foreach ($societiesJson as $name => $meta) {
    $insertSociety->execute([
        $name,
        $meta['faculty'],
        $adminId, // demo: every society advised by the one demo admin
        $meta['desc'],
        $meta['members'],
        $meta['founded'],
        $meta['coverUrl'],
        $meta['logoColor'],
    ]);
    $societyIds[$name] = (int) $pdo->lastInsertId();
}
echo 'Seeded ' . count($societyIds) . " societies.{$nl}";

// ── Events ───────────────────────────────────────────────────────────────
$eventsJson = json_decode(file_get_contents(__DIR__ . '/seed_events.json'), true);

$insertEvent = $pdo->prepare(
    'INSERT INTO events (society_id, title, description, category, venue, starts_at, ends_at, capacity, price, status, image_url, organiser_id)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

// Frontend statuses (approved | pending | completed | cancelled) map to
// the DB enum (approved | pending_approval | completed | cancelled).
$statusMap = [
    'approved' => 'approved',
    'pending' => 'pending_approval',
    'completed' => 'completed',
    'cancelled' => 'cancelled',
];

$eventCount = 0;
foreach ($eventsJson as $ev) {
    $societyId = $societyIds[$ev['societyName']] ?? null;
    if ($societyId === null) {
        echo "  WARNING: skipping '{$ev['title']}' — unknown society '{$ev['societyName']}'{$nl}";
        continue;
    }

    // The frontend's date/time fields are display strings (e.g. "Sat, 21 Jun
    // 2026", "9:00 AM"), not real datetimes. For seed purposes we fall back
    // to a placeholder date when parsing fails — replace with proper
    // DATETIME values once the team enters events through the real
    // Organiser Dashboard form instead of this seed script.
    $startsAt = date('Y-m-d H:i:s', strtotime($ev['date'] . ' ' . $ev['time']) ?: time());
    $endsAt = date('Y-m-d H:i:s', strtotime($ev['date'] . ' ' . $ev['endsAt']) ?: time());

    $insertEvent->execute([
        $societyId,
        $ev['title'],
        $ev['description'],
        $ev['category'],
        $ev['venue'],
        $startsAt,
        $endsAt,
        $ev['capacity'],
        $ev['price'],
        $statusMap[$ev['status']] ?? 'pending_approval',
        $ev['imageUrl'] ?? null,
        $organiserId, // demo: every event owned by the one demo organiser
    ]);
    $eventCount++;
}
echo "Seeded {$eventCount} events.{$nl}";

echo "{$nl}Done. Demo login credentials (password for all: password123):{$nl}";
echo "  attendee@utm.my{$nl}";
echo "  organiser@utm.my{$nl}";
echo "  admin@utm.my{$nl}";
