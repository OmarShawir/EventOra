<?php

namespace App;

use Psr\Http\Message\ResponseInterface as Response;

/**
 * Tiny helper so every controller returns JSON the same way, with the
 * right Content-Type and status code, instead of repeating
 * json_encode()/withHeader() boilerplate in every method.
 */
class JsonResponse
{
    public static function send(Response $response, mixed $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    public static function error(Response $response, string $message, int $status = 400, array $extra = []): Response
    {
        return self::send($response, array_merge(['error' => $message], $extra), $status);
    }
}
