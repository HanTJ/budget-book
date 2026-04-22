<?php

declare(strict_types=1);

namespace BudgetBook\Tests\Support;

use BudgetBook\Infrastructure\Database\ConnectionFactory;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\TestCase;

abstract class DatabaseTestCase extends TestCase
{
    protected Capsule $db;

    protected function setUp(): void
    {
        parent::setUp();

        ConnectionFactory::reset();
        $this->db = ConnectionFactory::boot([
            'database' => $_ENV['DB_TEST_DATABASE'] ?? 'budget_book_test',
        ]);

        $this->db->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $connection = $this->db->getConnection();
        if ($connection->transactionLevel() > 0) {
            $connection->rollBack();
        }

        ConnectionFactory::reset();

        parent::tearDown();
    }
}
