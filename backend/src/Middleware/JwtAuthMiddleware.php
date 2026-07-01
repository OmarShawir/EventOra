<?php

namespace App\Middleware;

use App\Auth\JwtHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Psr\Http\Server\MiddlewareInterface;
use Slim\Psr7\Response as SlimResponse;

/**
 * Verifies the Authorization: Bearer <token> header on every route it's
 * attached to. On success, the decoded JWT payload (user id, role, email,
 * name) is attached to the request as the "jwt" attribute, retrievable in
 * any controller via $request->getAttribute('jwt').
 *
 * Use RoleMiddleware afterwards (or in addition) on routes that should be
 * restricted to a specific role (e.g. only 'admin' can hit the approval
 * endpoints).
 */
class JwtAuthMiddleware implements MiddlewareInterface
{
    public function process(Request $request, Handler $handler): Response
    {
        $header = $request->getHeaderLine('Authorization');

        if (!$header || !str_starts_with($header, 'Bearer ')) {
            return $this->unauthorized('Missing or malformed Authorization header.');
        }

        $token = substr($header, 7);

        try {
            $payload = JwtHelper::verify($token);
        } catch (\Throwable $e) {
            return $this->unauthorized('Invalid or expired token.');
        }

        $request = $request->withAttribute('jwt', $payload);

        return $handler->handle($request);
    }

    private function unauthorized(string $message): Response
    {
        $response = new SlimResponse(401);
        $response->getBody()->write(json_encode(['error' => $message]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
