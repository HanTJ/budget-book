<?php

declare(strict_types=1);

namespace BudgetBook\Tests\Support;

use BudgetBook\Bootstrap\AppFactory;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

abstract class HttpTestCase extends DatabaseTestCase
{
    /** @var App<\Psr\Container\ContainerInterface|null> */
    protected App $app;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = AppFactory::create();
    }

    /**
     * @param array<string, mixed>|null $body JSON body
     */
    protected function request(string $method, string $path, ?array $body = null, ?string $authToken = null): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest($method, $path);

        if ($body !== null) {
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody((new StreamFactory())->createStream(json_encode($body, JSON_THROW_ON_ERROR)));
        }

        if ($authToken !== null) {
            $request = $request->withHeader('Authorization', 'Bearer ' . $authToken);
        }

        return $this->app->handle($request);
    }

    /**
     * @return array<string, mixed>
     */
    protected function json(ResponseInterface $response): array
    {
        $decoded = json_decode((string) $response->getBody(), true);
        self::assertIsArray($decoded);
        return $decoded;
    }
}
