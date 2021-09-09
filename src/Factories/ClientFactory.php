<?php

declare(strict_types=1);

namespace Huslab\Elasticsearch\Factories;

use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use Elasticsearch\Common\Exceptions\InvalidArgumentException;
use Huslab\Elasticsearch\Interfaces\ClientFactoryInterface;
use Psr\Log\LoggerInterface;

class ClientFactory implements ClientFactoryInterface
{
    /**
     * @inheritDoc
     */
    public function createClient(
        array $hosts,
        ?LoggerInterface $logger = null,
        ?callable $handler = null
    ): Client {
        $builder = new ClientBuilder();
        $builder->setHosts($hosts);

        if ($logger) {
            try {
                $builder->setLogger($logger);
            } catch (InvalidArgumentException $exception) {
                // Bogus exception impossible with the type hint specified
            }
        }

        if ($handler) {
            $builder->setHandler($handler);
        }

        return $builder->build();
    }
}
