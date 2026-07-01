<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Psr\Http\Server\MiddlewareInterface;

/**
 * Allows the Vue frontend (running on a different origin during local dev
 * — Vite on :5173 vs this API on :8080) to call the API. Reads the
 * whitelist from CORS_ALLOWED_ORIGINS in .env so the deployed Vercel URL
 * can be added later without touching code.
 */
class CorsMiddleware implements MiddlewareInterface
{
    public function process(Request $request, Handler $handler): Response
    {
        $origin = $request->getHeaderLine('Origin');
        $allowed = array_map('trim', explode(',', getenv('CORS_ALLOWED_ORIGINS') ?: ''));

        $response = $handler->handle($request);

        if ($origin && in_array($origin, $allowed, true)) {
            $response = $response
                ->withHeader('Access-Control-Allow-Origin', $origin)
                ->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        return $response
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
    }
}
