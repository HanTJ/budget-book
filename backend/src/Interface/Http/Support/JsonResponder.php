<?php

declare(strict_types=1);

namespace BudgetBook\Interface\Http\Support;

use Psr\Http\Message\ResponseInterface;

final class JsonResponder
{
    /**
     * @param array<string, mixed> $payload
     */
    public static function json(ResponseInterface $response, int $status, array $payload): ResponseInterface
    {
        $response->getBody()->write((string) json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * @param array<string, mixed> $extra
     */
    public static function error(
        ResponseInterface $response,
        int $status,
        string $code,
        array $extra = [],
    ): ResponseInterface {
        return self::json($response, $status, array_merge(['error' => $code], $extra));
    }
}
