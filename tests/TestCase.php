<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        $connection = $_ENV['DB_CONNECTION'] ?? $_SERVER['DB_CONNECTION'] ?? getenv('DB_CONNECTION') ?: null;
        $database = $_ENV['DB_DATABASE'] ?? $_SERVER['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: null;

        if (in_array($connection, ['mysql', 'mariadb'], true) && ! preg_match('/_test$/', (string) $database)) {
            throw new \RuntimeException('Sécurité : les tests MySQL exigent une base dont le nom se termine par _test.');
        }

        parent::setUp();
    }
}
