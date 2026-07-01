<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Psr\Http\Server\MiddlewareInterface;
use Slim\Psr7\Response as SlimResponse;

/**
 * Restricts a route to one or more roles. Must run AFTER JwtAuthMiddleware
 * (relies on the "jwt" request attribute it sets). Implements the PR1
 * proposal's requirement that "no role able to access another role's
 * sensitive data or functions" — e.g. RoleMiddleware::only('admin') on the
 * event approval routes.
 */
class RoleMiddleware implements MiddlewareInterface
{
    /** @param string[] $allowedRoles */
    private function __construct(private array $allowedRoles)
    {
    }

    public static function only(string ...$roles): self
    {
        return new self($roles);
    }

    public function process(Request $request, Handler $handler): Response
    {
        $jwt = $request->getAttribute('jwt');
        $role = $jwt['role'] ?? null;

        if (!in_array($role, $this->allowedRoles, true)) {
            $response = new SlimResponse(403);
            $response->getBody()->write(json_encode([
                'error' => 'You do not have permission to access this resource.',
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        return $handler->handle($request);
    }
}
