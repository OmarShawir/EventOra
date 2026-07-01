<?php

namespace App\Controllers;

use App\Database\Connection;
use App\JsonResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class TicketController
{
    /** GET /tickets/me — the logged-in attendee's tickets, with event details joined in. */
    public function mine(Request $request, Response $response): Response
    {
        $jwt = $request->getAttribute('jwt');
        $pdo = Connection::get();

        $stmt = $pdo->prepare(
            'SELECT t.*, e.title, e.venue, e.starts_at, e.ends_at, e.status AS event_status, s.name AS society_name
             FROM tickets t
             JOIN events e ON e.id = t.event_id
             JOIN societies s ON s.id = e.society_id
             WHERE t.user_id = ?
             ORDER BY e.starts_at DESC'
        );
        $stmt->execute([$jwt['sub']]);

        $tickets = array_map([$this, 'present'], $stmt->fetchAll());

        return JsonResponse::send($response, $tickets);
    }

    /**
     * POST /events/{id}/register
     * Issues a ticket for the logged-in user. Enforces capacity and
     * prevents double-registration (also backstopped by the
     * uniq_ticket_event_user constraint at the DB level).
     */
    public function register(Request $request, Response $response, array $args): Response
    {
        $jwt = $request->getAttribute('jwt');
        
        // Only attendees can register for events
        $role = $jwt['role'] ?? null;
        if ($role !== 'attendee') {
            return JsonResponse::error($response, 'Only attendees can register for events.', 403);
        }

        $eventId = (int) $args['id'];
        $userId = (int) $jwt['sub'];
        $pdo = Connection::get();

        $event = $pdo->prepare('SELECT * FROM events WHERE id = ? AND status = "approved"');
        $event->execute([$eventId]);
        $event = $event->fetch();

        if (!$event) {
            return JsonResponse::error($response, 'Event not found or not open for registration.', 404);
        }

        $existing = $pdo->prepare('SELECT id FROM tickets WHERE event_id = ? AND user_id = ?');
        $existing->execute([$eventId, $userId]);
        if ($existing->fetch()) {
            return JsonResponse::error($response, 'You are already registered for this event.', 409);
        }

        $registeredStmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE event_id = ? AND status != 'cancelled'");
        $registeredStmt->execute([$eventId]);
        $registeredCount = (int) $registeredStmt->fetchColumn();

        $status = $registeredCount >= (int) $event['capacity'] ? 'waitlisted' : 'confirmed';
        $qrCode = $this->generateQrCode($eventId, $userId);

        $insert = $pdo->prepare(
            'INSERT INTO tickets (event_id, user_id, qr_code, status) VALUES (?, ?, ?, ?)'
        );
        $insert->execute([$eventId, $userId, $qrCode, $status]);
        $ticketId = (int) $pdo->lastInsertId();

        return JsonResponse::send($response, $this->fetchPresented($pdo, $ticketId), 201);
    }

    /**
     * POST /checkin
     * Body: { qrCode }
     * Restricted to role=organiser via RoleMiddleware. Records the
     * check-in and stamps checked_in_by with the scanning organiser's
     * user id (audit trail, per the ER diagram's CHECKIN entity).
     */
    public function checkIn(Request $request, Response $response): Response
    {
        $jwt = $request->getAttribute('jwt');
        $data = (array) ($request->getParsedBody() ?? []);
        $qrCode = trim((string) ($data['qrCode'] ?? ''));

        if (!$qrCode) {
            return JsonResponse::error($response, 'qrCode is required.', 422);
        }

        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'SELECT t.*, e.title AS event_title
             FROM tickets t JOIN events e ON e.id = t.event_id
             WHERE t.qr_code = ?'
        );
        $stmt->execute([$qrCode]);
        $ticket = $stmt->fetch();

        if (!$ticket) {
            return JsonResponse::error($response, 'QR code not found. Please verify the ticket.', 404);
        }
        if ($ticket['status'] === 'checked_in') {
            return JsonResponse::error($response, 'Already checked in!', 409, ['ticket' => $this->present($ticket)]);
        }
        if ($ticket['status'] === 'cancelled') {
            return JsonResponse::error($response, 'This ticket has been cancelled.', 409);
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE tickets SET status = 'checked_in' WHERE id = ?")->execute([$ticket['id']]);
            $pdo->prepare('INSERT INTO checkins (ticket_id, checked_in_by) VALUES (?, ?)')
                ->execute([$ticket['id'], $jwt['sub']]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return JsonResponse::error($response, 'Check-in failed. Please try again.', 500);
        }

        return JsonResponse::send($response, [
            'ok' => true,
            'message' => 'Check-in successful!',
            'eventTitle' => $ticket['event_title'],
        ]);
    }

    private function fetchPresented(\PDO $pdo, int $ticketId): array
    {
        $stmt = $pdo->prepare(
            'SELECT t.*, e.title, e.venue, e.starts_at, e.ends_at, e.status AS event_status, s.name AS society_name
             FROM tickets t
             JOIN events e ON e.id = t.event_id
             JOIN societies s ON s.id = e.society_id
             WHERE t.id = ?'
        );
        $stmt->execute([$ticketId]);
        return $this->present($stmt->fetch());
    }

    private function present(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'eventId' => (string) $row['event_id'],
            'userId' => (string) $row['user_id'],
            'qrCode' => $row['qr_code'],
            'status' => $row['status'],
            'issuedAt' => date('d M Y', strtotime($row['issued_at'])),
            'event' => isset($row['title']) ? [
                'title' => $row['title'],
                'venue' => $row['venue'],
                'date' => date('D, j M Y', strtotime($row['starts_at'])),
                'time' => date('g:i A', strtotime($row['starts_at'])),
                'status' => $row['event_status'] === 'pending_approval' ? 'pending' : $row['event_status'],
                'societyName' => $row['society_name'],
            ] : null,
        ];
    }

    /** Mirrors the frontend's makeQR() format: EVORA-{EVENT}-{USER}-{random}. */
    private function generateQrCode(int $eventId, int $userId): string
    {
        $random = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        return "EVORA-E{$eventId}-U{$userId}-{$random}";
    }
}
