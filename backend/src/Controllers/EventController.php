<?php

namespace App\Controllers;

use App\Database\Connection;
use App\JsonResponse;
use App\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class EventController
{
    /**
     * GET /events
     * Public listing — only approved/completed events are visible here,
     * matching the proposal's "Events pending admin approval are not
     * visible on this page" requirement. Organisers/admins fetch their
     * own pending events through dedicated routes instead.
     */
    public function index(Request $request, Response $response): Response
    {
        $pdo = Connection::get();
        $stmt = $pdo->query(
            "SELECT e.*, s.name AS society_name
             FROM events e
             JOIN societies s ON s.id = e.society_id
             WHERE e.status IN ('approved', 'completed')
             ORDER BY e.starts_at ASC"
        );
        $events = array_map([$this, 'present'], $stmt->fetchAll());

        return JsonResponse::send($response, $events);
    }

    /** GET /events/{id} */
    public function show(Request $request, Response $response, array $args): Response
    {
        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'SELECT e.*, s.name AS society_name
             FROM events e
             JOIN societies s ON s.id = e.society_id
             WHERE e.id = ?'
        );
        $stmt->execute([$args['id']]);
        $event = $stmt->fetch();

        if (!$event) {
            return JsonResponse::error($response, 'Event not found.', 404);
        }

        return JsonResponse::send($response, $this->present($event));
    }

    /**
     * GET /events/pending
     * Faculty admin's approval queue. Restricted to role=admin via
     * RoleMiddleware in routes.php.
     */
    public function pending(Request $request, Response $response): Response
    {
        $pdo = Connection::get();
        $stmt = $pdo->query(
            "SELECT e.*, s.name AS society_name
             FROM events e
             JOIN societies s ON s.id = e.society_id
             WHERE e.status = 'pending_approval'
             ORDER BY e.created_at ASC"
        );
        $events = array_map([$this, 'present'], $stmt->fetchAll());

        return JsonResponse::send($response, $events);
    }

    /**
     * GET /events/mine
     * Organiser's own events across all statuses, restricted to
     * role=organiser via RoleMiddleware.
     */
    public function mine(Request $request, Response $response): Response
    {
        $jwt = $request->getAttribute('jwt');
        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'SELECT e.*, s.name AS society_name
             FROM events e
             JOIN societies s ON s.id = e.society_id
             WHERE e.organiser_id = ?
             ORDER BY e.starts_at DESC'
        );
        $stmt->execute([$jwt['sub']]);
        $events = array_map([$this, 'present'], $stmt->fetchAll());

        return JsonResponse::send($response, $events);
    }

    /**
     * POST /events
     * Creates a new event with status=pending_approval. Restricted to
     * role=organiser. The organiser's society is resolved from their
     * user record (users.society), matching how the frontend's
     * OrganiserDashboard derives societyName from auth.user.society.
     */
    public function store(Request $request, Response $response): Response
    {
        $jwt = $request->getAttribute('jwt');
        $data = (array) ($request->getParsedBody() ?? []);

        $errors = Validator::check($data, [
            'title' => 'required|minlen:3',
            'description' => 'required|minlen:10',
            'category' => 'required|in:Academic,Sports,Cultural,Religious,Workshop,Career',
            'venue' => 'required',
            'startsAt' => 'required',
            'endsAt' => 'required',
            'capacity' => 'required|int|min:1',
            'price' => 'int|min:0',
        ]);
        if ($errors) {
            @file_put_contents(__DIR__ . '/../../scratch/error.log', "Validation failed: " . json_encode($errors) . "\n", FILE_APPEND);
            return JsonResponse::error($response, 'Validation failed.', 422, ['fields' => $errors]);
        }

        try {
            $pdo = Connection::get();

            $societyStmt = $pdo->prepare('SELECT id FROM societies WHERE name = ?');
            $societyStmt->execute([$jwt['name'] ? $this->organiserSociety($pdo, (int) $jwt['sub']) : null]);
            $societyRow = $societyStmt->fetch();

            if (!$societyRow) {
                @file_put_contents(__DIR__ . '/../../scratch/error.log', "No society found for user ID: " . $jwt['sub'] . "\n", FILE_APPEND);
                return JsonResponse::error($response, 'Your account is not linked to a society.', 422);
            }

            $stmt = $pdo->prepare(
                'INSERT INTO events
                    (society_id, title, description, category, venue, starts_at, ends_at, capacity, price, status, image_url, organiser_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "pending_approval", ?, ?)'
            );
            $stmt->execute([
                $societyRow['id'],
                $data['title'],
                $data['description'],
                $data['category'],
                $data['venue'],
                $data['startsAt'],
                $data['endsAt'],
                $data['capacity'],
                $data['price'] ?? 0,
                $data['imageUrl'] ?? null,
                $jwt['sub'],
            ]);

            return $this->show($request, $response, ['id' => $pdo->lastInsertId()]);
        } catch (\Exception $e) {
            @file_put_contents(__DIR__ . '/../../scratch/error.log', "DB Error: " . $e->getMessage() . "\n", FILE_APPEND);
            return JsonResponse::error($response, $e->getMessage(), 500);
        }
    }

    /**
     * PATCH /events/{id}
     * Organiser edits their own event. Ownership is checked — an
     * organiser cannot edit another society's event even with a valid
     * JWT, per the "no role able to access another role's sensitive
     * data" requirement.
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $jwt = $request->getAttribute('jwt');
        $pdo = Connection::get();

        $owns = $this->assertOwnership($pdo, (int) $args['id'], (int) $jwt['sub'], $response);
        if ($owns instanceof Response) {
            return $owns;
        }

        $data = (array) ($request->getParsedBody() ?? []);
        $fields = [];
        $values = [];

        foreach (['title', 'description', 'category', 'venue', 'starts_at', 'ends_at', 'capacity', 'price', 'image_url'] as $col) {
            $key = lcfirst(str_replace('_', '', ucwords($col, '_'))); // snake_case -> camelCase from JSON body
            if (array_key_exists($key, $data)) {
                $fields[] = "{$col} = ?";
                $values[] = $data[$key];
            }
        }

        if ($fields) {
            $values[] = $args['id'];
            $pdo->prepare('UPDATE events SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($values);
        }

        return $this->show($request, $response, $args);
    }

    /** POST /events/{id}/approve — admin only */
    public function approve(Request $request, Response $response, array $args): Response
    {
        $pdo = Connection::get();
        $pdo->prepare("UPDATE events SET status = 'approved' WHERE id = ?")->execute([$args['id']]);
        return $this->show($request, $response, $args);
    }

    /** POST /events/{id}/reject — admin only */
    public function reject(Request $request, Response $response, array $args): Response
    {
        $data = (array) ($request->getParsedBody() ?? []);
        $pdo = Connection::get();
        $pdo->prepare("UPDATE events SET status = 'cancelled', rejection_reason = ? WHERE id = ?")
            ->execute([$data['reason'] ?? null, $args['id']]);
        return $this->show($request, $response, $args);
    }

    /** POST /events/{id}/cancel — organiser only, must own the event */
    public function cancel(Request $request, Response $response, array $args): Response
    {
        $jwt = $request->getAttribute('jwt');
        $pdo = Connection::get();

        $owns = $this->assertOwnership($pdo, (int) $args['id'], (int) $jwt['sub'], $response);
        if ($owns instanceof Response) {
            return $owns;
        }

        $pdo->prepare("UPDATE events SET status = 'cancelled' WHERE id = ?")->execute([$args['id']]);
        return $this->show($request, $response, $args);
    }

    /** GET /events/{id}/participants — organiser only, must own the event */
    public function participants(Request $request, Response $response, array $args): Response
    {
        $jwt = $request->getAttribute('jwt');
        $pdo = Connection::get();

        $owns = $this->assertOwnership($pdo, (int) $args['id'], (int) $jwt['sub'], $response);
        if ($owns instanceof Response) {
            return $owns;
        }

        $stmt = $pdo->prepare(
            "SELECT 
                u.name,
                u.matric_no,
                t.status,
                c.checked_in_at
             FROM tickets t
             JOIN users u ON u.id = t.user_id
             LEFT JOIN checkins c ON c.ticket_id = t.id
             WHERE t.event_id = ? AND t.status != 'cancelled'
             ORDER BY u.name ASC"
        );
        $stmt->execute([$args['id']]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $participants = array_map(function ($row) {
            return [
                'name' => $row['name'],
                'matricNo' => $row['matric_no'] ?? '—',
                'status' => $row['status'],
                'checkedInAt' => $row['checked_in_at'] ? date('Y-m-d H:i:s', strtotime($row['checked_in_at'])) : '—'
            ];
        }, $rows);

        return JsonResponse::send($response, $participants);
    }

    private function organiserSociety(\PDO $pdo, int $userId): ?string
    {
        $stmt = $pdo->prepare('SELECT society FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        return $stmt->fetchColumn() ?: null;
    }

    /** Returns null if the user owns the event, or a 403 Response otherwise. */
    private function assertOwnership(\PDO $pdo, int $eventId, int $userId, Response $response): ?Response
    {
        $stmt = $pdo->prepare('SELECT organiser_id FROM events WHERE id = ?');
        $stmt->execute([$eventId]);
        $ownerId = $stmt->fetchColumn();

        if ($ownerId === false) {
            return JsonResponse::error($response, 'Event not found.', 404);
        }
        if ((int) $ownerId !== $userId) {
            return JsonResponse::error($response, 'You do not own this event.', 403);
        }
        return null;
    }

    /** Maps a DB row (snake_case) to the camelCase shape the Vue frontend expects. */
    private function present(array $row): array
    {
        $spotsLeft = max(0, (int) $row['capacity'] - (int) ($row['registered_count'] ?? $this->registeredCount((int) $row['id'])));

        return [
            'id' => (string) $row['id'],
            'societyId' => (string) $row['society_id'],
            'societyName' => $row['society_name'],
            'title' => $row['title'],
            'description' => $row['description'],
            'category' => $row['category'],
            'venue' => $row['venue'],
            'date' => date('D, j M Y', strtotime($row['starts_at'])),
            'time' => date('g:i A', strtotime($row['starts_at'])),
            'endsAt' => date('g:i A', strtotime($row['ends_at'])),
            'capacity' => (int) $row['capacity'],
            'spotsLeft' => $spotsLeft,
            'price' => (float) $row['price'],
            'status' => $this->mapStatusOut($row['status']),
            'imageUrl' => $row['image_url'],
            'organiserName' => null, // resolved client-side from societyName today; see TODO below
        ];
    }

    private function registeredCount(int $eventId): int
    {
        static $pdo = null;
        $pdo ??= Connection::get();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE event_id = ? AND status != 'cancelled'");
        $stmt->execute([$eventId]);
        return (int) $stmt->fetchColumn();
    }

    private function mapStatusOut(string $dbStatus): string
    {
        return match ($dbStatus) {
            'pending_approval' => 'pending',
            default => $dbStatus,
        };
    }
}
