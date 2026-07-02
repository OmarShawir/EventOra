<?php

namespace App\Controllers;

use App\Database\Connection;
use App\JsonResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Stripe\Stripe;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Webhook;

class PaymentController
{
    /**
     * POST /events/{id}/checkout-session
     * Creates a Stripe Checkout Session for registering/paying for a paid event.
     */
    public function checkoutSession(Request $request, Response $response, array $args): Response
    {
        $jwt = $request->getAttribute('jwt');
        $role = $jwt['role'] ?? null;
        if ($role !== 'attendee') {
            return JsonResponse::error($response, 'Only attendees can register/pay for events.', 403);
        }

        $eventId = (int) $args['id'];
        $userId = (int) $jwt['sub'];
        $pdo = Connection::get();

        // 1. Fetch Event
        $eventStmt = $pdo->prepare('SELECT * FROM events WHERE id = ? AND status = "approved"');
        $eventStmt->execute([$eventId]);
        $event = $eventStmt->fetch();

        if (!$event) {
            return JsonResponse::error($response, 'Event not found or not open for registration.', 404);
        }

        // 2. Enforce Paid Event
        $price = (float) $event['price'];
        if ($price <= 0) {
            return JsonResponse::error($response, 'This event is free. Please register directly.', 400);
        }

        // 3. Prevent Double-Registration
        $existing = $pdo->prepare('SELECT id FROM tickets WHERE event_id = ? AND user_id = ?');
        $existing->execute([$eventId, $userId]);
        if ($existing->fetch()) {
            return JsonResponse::error($response, 'You are already registered for this event.', 409);
        }

        // 4. Enforce Capacity
        $registeredStmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE event_id = ? AND status != 'cancelled'");
        $registeredStmt->execute([$eventId]);
        $registeredCount = (int) $registeredStmt->fetchColumn();

        if ($registeredCount >= (int) $event['capacity']) {
            return JsonResponse::error($response, 'This event is sold out.', 400);
        }

        // 5. Create Stripe Checkout Session
        $stripeSecret = getenv('STRIPE_SECRET_KEY') ?: $_ENV['STRIPE_SECRET_KEY'] ?? '';
        if (!$stripeSecret || $stripeSecret === 'sk_test_placeholder') {
            return JsonResponse::error($response, 'Stripe integration is not configured on the server.', 500);
        }

        Stripe::setApiKey($stripeSecret);

        $frontendUrl = rtrim(getenv('FRONTEND_URL') ?: $_ENV['FRONTEND_URL'] ?? 'http://localhost:5173', '/');

        try {
            $session = StripeSession::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'myr',
                        'product_data' => [
                            'name' => $event['title'],
                            'description' => 'Ticket for ' . $event['title'] . ' at ' . $event['venue'],
                        ],
                        'unit_amount' => (int) ($price * 100), // Stripe expects the amount in sen (MYR's smallest unit)
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => $frontendUrl . '/payment-success?session_id={CHECKOUT_SESSION_ID}&event_id=' . $eventId,
                'cancel_url' => $frontendUrl . '/events/' . $eventId,
                'metadata' => [
                    'event_id' => (string) $eventId,
                    'user_id' => (string) $userId,
                ],
            ]);

            return JsonResponse::send($response, [
                'checkoutUrl' => $session->url,
                'sessionId' => $session->id,
            ]);
        } catch (\Exception $e) {
            return JsonResponse::error($response, 'Failed to create payment session: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /webhooks/stripe
     * Stripe webhook handler to asynchronously confirm payment and issue tickets.
     */
    public function webhook(Request $request, Response $response): Response
    {
        $stripeSecret = getenv('STRIPE_SECRET_KEY') ?: $_ENV['STRIPE_SECRET_KEY'] ?? '';
        $webhookSecret = getenv('STRIPE_WEBHOOK_SECRET') ?: $_ENV['STRIPE_WEBHOOK_SECRET'] ?? '';

        if (!$stripeSecret || $stripeSecret === 'sk_test_placeholder') {
            return JsonResponse::error($response, 'Stripe integrations are not configured.', 500);
        }

        Stripe::setApiKey($stripeSecret);

        $payload = (string) $request->getBody();
        $sigHeader = $request->getHeaderLine('Stripe-Signature');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (\UnexpectedValueException $e) {
            return JsonResponse::error($response, 'Invalid payload: ' . $e->getMessage(), 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            return JsonResponse::error($response, 'Invalid signature: ' . $e->getMessage(), 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            $metadata = $session->metadata;

            $eventId = isset($metadata->event_id) ? (int) $metadata->event_id : null;
            $userId = isset($metadata->user_id) ? (int) $metadata->user_id : null;

            if ($eventId && $userId) {
                $this->issueTicketForSession($eventId, $userId, $session);
            }
        }

        return JsonResponse::send($response, ['received' => true]);
    }

    /**
     * GET /payment/verify-session
     * Called by the frontend right after Stripe redirects back to the
     * success page. Issues the ticket synchronously instead of waiting on
     * the async webhook, which can be delayed (or, if the endpoint isn't
     * registered in the Stripe dashboard, never arrive at all). The webhook
     * remains the source of truth and stays idempotent alongside this.
     */
    public function verifySession(Request $request, Response $response): Response
    {
        $jwt = $request->getAttribute('jwt');
        $userId = (int) $jwt['sub'];
        $sessionId = $request->getQueryParams()['session_id'] ?? '';

        if (!$sessionId) {
            return JsonResponse::error($response, 'session_id is required.', 400);
        }

        $stripeSecret = getenv('STRIPE_SECRET_KEY') ?: $_ENV['STRIPE_SECRET_KEY'] ?? '';
        if (!$stripeSecret || $stripeSecret === 'sk_test_placeholder') {
            return JsonResponse::error($response, 'Stripe integration is not configured on the server.', 500);
        }
        Stripe::setApiKey($stripeSecret);

        try {
            $session = StripeSession::retrieve($sessionId);
        } catch (\Exception $e) {
            return JsonResponse::error($response, 'Could not verify payment session.', 502);
        }

        if ($session->payment_status !== 'paid') {
            return JsonResponse::error($response, 'Payment not completed yet.', 402);
        }

        $metadata = $session->metadata;
        $eventId = isset($metadata->event_id) ? (int) $metadata->event_id : null;
        $sessionUserId = isset($metadata->user_id) ? (int) $metadata->user_id : null;

        // The session must belong to the caller — stops one user from
        // polling another user's session_id to read/create their ticket.
        if (!$eventId || $sessionUserId !== $userId) {
            return JsonResponse::error($response, 'Session does not match the current user.', 403);
        }

        $this->issueTicketForSession($eventId, $userId, $session);

        return JsonResponse::send($response, ['verified' => true]);
    }

    /** Shared by the webhook and verifySession so both stay idempotent and in sync. */
    private function issueTicketForSession(int $eventId, int $userId, $session): void
    {
        $pdo = Connection::get();

        // Prevent duplicates in case the webhook and verifySession both fire
        $existing = $pdo->prepare('SELECT id FROM tickets WHERE event_id = ? AND user_id = ?');
        $existing->execute([$eventId, $userId]);
        if ($existing->fetch()) {
            return;
        }

        $qrCode = $this->generateQrCode($eventId, $userId);
        $insert = $pdo->prepare(
            'INSERT INTO tickets (event_id, user_id, qr_code, status) VALUES (?, ?, ?, "confirmed")'
        );
        $insert->execute([$eventId, $userId, $qrCode]);
        $ticketId = (int) $pdo->lastInsertId();

        $amount = (float) (($session->amount_total ?? 0) / 100);
        $stripeChargeId = $session->payment_intent ?? null;
        $payStmt = $pdo->prepare(
            'INSERT INTO payments (ticket_id, amount, currency, stripe_charge_id, status) VALUES (?, ?, "MYR", ?, "completed")'
        );
        $payStmt->execute([$ticketId, $amount, $stripeChargeId]);
    }

    /**
     * GET /organiser/transactions
     * Fetches detailed payment logs for events organized by the authenticated user.
     */
    public function organiserTransactions(Request $request, Response $response): Response
    {
        $jwt = $request->getAttribute('jwt');
        $organiserId = (int) $jwt['sub'];
        $pdo = Connection::get();

        $stmt = $pdo->prepare('
            SELECT 
                p.id AS transaction_id,
                e.title AS event_title,
                e.id AS event_id,
                u.name AS attendee_name,
                u.email AS attendee_email,
                p.amount,
                p.status,
                p.created_at AS payment_date
            FROM payments p
            JOIN tickets t ON p.ticket_id = t.id
            JOIN events e ON t.event_id = e.id
            JOIN users u ON t.user_id = u.id
            WHERE e.organiser_id = ?
            ORDER BY p.created_at DESC
        ');
        $stmt->execute([$organiserId]);
        $transactions = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return JsonResponse::send($response, [
            'transactions' => $transactions
        ]);
    }

    /**
     * GET /admin/transactions
     * Fetches detailed payment logs for all events (Admin Only).
     */
    public function getAllTransactions(Request $request, Response $response): Response
    {
        $pdo = Connection::get();

        $stmt = $pdo->prepare('
            SELECT 
                p.id AS transaction_id,
                e.title AS event_title,
                e.id AS event_id,
                u.name AS attendee_name,
                u.email AS attendee_email,
                p.amount,
                p.status,
                p.created_at AS payment_date,
                org.name AS organiser_name,
                org.society AS organiser_society
            FROM payments p
            JOIN tickets t ON p.ticket_id = t.id
            JOIN events e ON t.event_id = e.id
            JOIN users u ON t.user_id = u.id
            JOIN users org ON e.organiser_id = org.id
            ORDER BY p.created_at DESC
        ');
        $stmt->execute();
        $transactions = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return JsonResponse::send($response, [
            'transactions' => $transactions
        ]);
    }

    /** Mirrors the frontend's makeQR() format: EVORA-{EVENT}-{USER}-{random}. */
    private function generateQrCode(int $eventId, int $userId): string
    {
        $random = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        return "EVORA-E{$eventId}-U{$userId}-{$random}";
    }
}
