<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\EventController;
use App\Controllers\FeedbackController;
use App\Controllers\PaymentController;
use App\Controllers\TicketController;
use App\Controllers\UploadController;
use App\Env;
use App\Middleware\CorsMiddleware;
use App\Middleware\JwtAuthMiddleware;
use App\Middleware\RoleMiddleware;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

Env::load(__DIR__ . '/../.env');

$app = AppFactory::create();

// CORS must run on every request, including preflight OPTIONS calls the
// browser sends before any POST/PATCH from the Vue frontend.
$app->add(new CorsMiddleware());
$app->options('/{routes:.+}', function ($request, $response) {
    return $response;
});

$app->addBodyParsingMiddleware();
$app->addErrorMiddleware(true, true, true);

// ── Public routes (no auth required) ────────────────────────────────────────
$app->post('/auth/register', [AuthController::class, 'register']);
$app->post('/auth/login', [AuthController::class, 'login']);
$app->post('/auth/google', [AuthController::class, 'googleLogin']);
$app->get('/auth/verify-email', [AuthController::class, 'verifyEmail']);
$app->post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
$app->post('/auth/reset-password', [AuthController::class, 'resetPassword']);

$app->get('/events', [EventController::class, 'index']);
$app->post('/webhooks/stripe', [PaymentController::class, 'webhook']);

// ── Organiser-only routes ────────────────────────────────────────────────────
// NOTE: /events/mine and /events/pending must be registered BEFORE the
// dynamic /events/{id} route below — FastRoute matches static segments
// first only if they're defined first; otherwise "mine"/"pending" get
// captured as the {id} parameter instead ("shadowed" route error).
$app->group('', function ($group) {
    $group->get('/events/mine', [EventController::class, 'mine']);
    $group->get('/events/{id}/participants', [EventController::class, 'participants']);
    $group->post('/events', [EventController::class, 'store']);
    $group->patch('/events/{id}', [EventController::class, 'update']);
    $group->post('/events/{id}/cancel', [EventController::class, 'cancel']);
    $group->post('/checkin', [TicketController::class, 'checkIn']);
    $group->post('/upload', [UploadController::class, 'upload']);
    
    // Organiser Bank & Transaction logs
    $group->get('/organiser/bank', [AuthController::class, 'getBankDetails']);
    $group->patch('/organiser/bank', [AuthController::class, 'updateBankDetails']);
    $group->get('/organiser/transactions', [PaymentController::class, 'organiserTransactions']);
})
    ->add(RoleMiddleware::only('organiser'))
    ->add(new JwtAuthMiddleware());

// ── Admin-only routes ─────────────────────────────────────────────────────────
$app->group('', function ($group) {
    $group->get('/events/pending', [EventController::class, 'pending']);
    $group->post('/events/{id}/approve', [EventController::class, 'approve']);
    $group->post('/events/{id}/reject', [EventController::class, 'reject']);
    $group->get('/admin/organisers', [AuthController::class, 'getOrganisers']);
    $group->get('/admin/transactions', [PaymentController::class, 'getAllTransactions']);
})
    ->add(RoleMiddleware::only('admin'))
    ->add(new JwtAuthMiddleware());

// Dynamic /events/{id} routes registered last so the static routes above
// (mine, pending) are matched first.
$app->get('/events/{id}', [EventController::class, 'show']);
$app->get('/events/{id}/feedback', [FeedbackController::class, 'index']);

// ── Authenticated routes (any logged-in role) ───────────────────────────────
$app->group('', function ($group) {
    $group->get('/auth/me', [AuthController::class, 'me']);
    $group->post('/events/{id}/register', [TicketController::class, 'register']);
    $group->post('/events/{id}/checkout-session', [PaymentController::class, 'checkoutSession']);
    $group->post('/events/{id}/feedback', [FeedbackController::class, 'store']);
})->add(new JwtAuthMiddleware());

// ── Attendee-only routes ─────────────────────────────────────────────────────
$app->group('', function ($group) {
    $group->get('/tickets/me', [TicketController::class, 'mine']);
})
    ->add(RoleMiddleware::only('attendee'))
    ->add(new JwtAuthMiddleware());

$app->run();
