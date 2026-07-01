<?php

namespace App\Controllers;

use App\Database\Connection;
use App\JsonResponse;
use App\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class FeedbackController
{
    /** GET /events/{id}/feedback — public, used by SocietyDetail/AdminPanel/OrganiserDashboard. */
    public function index(Request $request, Response $response, array $args): Response
    {
        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'SELECT f.*, u.name AS user_name
             FROM feedback f JOIN users u ON u.id = f.user_id
             WHERE f.event_id = ?
             ORDER BY f.created_at DESC'
        );
        $stmt->execute([$args['id']]);

        $rows = array_map([$this, 'present'], $stmt->fetchAll());

        return JsonResponse::send($response, $rows);
    }

    /**
     * POST /events/{id}/feedback
     * Body: { rating, comment }
     * Per the ER diagram: only a user with a checked_in ticket for this
     * event may submit feedback — enforced here, not just client-side.
     */
    public function store(Request $request, Response $response, array $args): Response
    {
        $jwt = $request->getAttribute('jwt');
        $eventId = (int) $args['id'];
        $userId = (int) $jwt['sub'];
        $data = (array) ($request->getParsedBody() ?? []);

        $errors = Validator::check($data, [
            'rating' => 'required|int|min:1',
        ]);
        if ($errors || (int) ($data['rating'] ?? 0) > 5) {
            return JsonResponse::error($response, 'Rating must be between 1 and 5.', 422, ['fields' => $errors]);
        }

        $pdo = Connection::get();

        $checkedIn = $pdo->prepare(
            "SELECT 1 FROM tickets WHERE event_id = ? AND user_id = ? AND status = 'checked_in'"
        );
        $checkedIn->execute([$eventId, $userId]);
        if (!$checkedIn->fetch()) {
            return JsonResponse::error(
                $response,
                'Only attendees who checked in to this event can leave feedback.',
                403
            );
        }

        $existing = $pdo->prepare('SELECT id FROM feedback WHERE event_id = ? AND user_id = ?');
        $existing->execute([$eventId, $userId]);
        if ($existing->fetch()) {
            return JsonResponse::error($response, 'You have already submitted feedback for this event.', 409);
        }

        $insert = $pdo->prepare(
            'INSERT INTO feedback (event_id, user_id, rating, comment) VALUES (?, ?, ?, ?)'
        );
        $insert->execute([$eventId, $userId, (int) $data['rating'], $data['comment'] ?? null]);

        return JsonResponse::send($response, ['ok' => true], 201);
    }

    private function present(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'eventId' => (string) $row['event_id'],
            'userId' => (string) $row['user_id'],
            'userName' => $row['user_name'],
            'rating' => (int) $row['rating'],
            'comment' => $row['comment'],
            'submittedAt' => date('Y-m-d', strtotime($row['created_at'])),
        ];
    }
}
