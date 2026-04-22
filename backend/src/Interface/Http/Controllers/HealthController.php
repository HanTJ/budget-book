<?php

declare(strict_types=1);

namespace BudgetBook\Interface\Http\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class HealthController
{
    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = [
            'status' => 'ok',
            'time' => gmdate('c'),
        ];

        $response->getBody()->write((string) json_encode($payload, JSON_THROW_ON_ERROR));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
