<?php
/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Huslab\Elasticsearch\Tests;

use Huslab\Elasticsearch\ElasticsearchServiceProvider;
use Huslab\Elasticsearch\Interfaces\ConnectionInterface;
use Huslab\Elasticsearch\Tests\Traits\ResolvesConnections;
use Orchestra\Testbench\TestCase;

class ConnectionTest extends TestCase
{
    use ResolvesConnections;

    public function testIndexReturnsNewQueryOnIndex(): void
    {
        /** @var ConnectionInterface $connection */
        $connection = $this->app->make(ConnectionInterface::class);
        $query = $connection->index('foo');

        self::assertSame('foo', $query->getIndex());
    }

    protected function getEnvironmentSetUp($app): void
    {
        $this->registerResolver($app);
    }

    protected function getPackageProviders($app): array
    {
        return [
            ElasticsearchServiceProvider::class,
        ];
    }
}
