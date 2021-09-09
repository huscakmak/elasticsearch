<?php

declare(strict_types=1);

namespace Huslab\Elasticsearch;

use ArrayObject;
use Elasticsearch\Client;
use Huslab\Elasticsearch\Interfaces\ConnectionInterface;
use TypeError;

use function array_unique;
use function count;
use function is_array;
use function is_string;

/**
 * Class Index
 *
 * @package Huslab\Elasticsearch\Query
 */
class Index
{
    private const PARAM_ALIASES = 'aliases';

    private const PARAM_BODY = 'body';

    private const PARAM_CLIENT = 'client';

    private const PARAM_CLIENT_IGNORE = 'ignore';

    private const PARAM_INDEX = 'index';

    private const PARAM_MAPPINGS = 'mappings';

    private const PARAM_SETTINGS = 'settings';

    private const PARAM_SETTINGS_NUMBER_OF_REPLICAS = 'number_of_replicas';

    private const PARAM_SETTINGS_NUMBER_OF_SHARDS = 'number_of_shards';

    /**
     * Native elasticsearch client instance
     *
     * @var ConnectionInterface
     * @deprecated Will be made private in the next major release. Use the
     *             method accessor instead.
     * @see        Index::getConnection()
     */
    public $connection;

    /**
     * Ignored HTTP errors
     *
     * @var array
     * @deprecated Will be made private in the next major release. Use the
     *             method accessor instead.
     * @see        Index::ignore()
     */
    public $ignores = [];

    /**
     * Index name
     *
     * @var string
     * @deprecated Will be made private in the next major release. Use the
     *             method accessor instead.
     * @see        Index::getName()
     */
    public $name;

    /**
     * Index create callback
     *
     * @var callable|null
     * @deprecated Will be made private in the next major release.
     */
    public $callback;

    /**
     * The number of shards the index shall be configured with.
     *
     * @var int
     * @deprecated Will be made private in the next major release. Use the
     *             method accessor instead.
     * @see        Index::shards()
     */
    public $shards = 5;

    /**
     * The number of replicas the index shall be configured with.
     *
     * @var int
     * @deprecated Will be made private in the next major release. Use the
     *             method accessor instead.
     * @see        Index::replicas()
     */
    public $replicas = 0;

    /**
     * Mappings the index shall be configured with.
     *
     * @var array
     * @deprecated Will be made private in the next major release. Use the
     *             method accessor instead.
     * @see        Index::mapping()
     */
    public $mappings = [];

    /**
     * Aliases the index shall be configured with.
     *
     * @var array<string, array<string, mixed>|string|ArrayObject>
     */
    protected $aliases = [];

    /**
     * Creates a new index instance.
     *
     * @param string        $name     Name of the index to create.
     * @param callable|null $callback Callback to configure the index before it
     *                                is created. This allows to add additional
     *                                options like shards, replicas or mappings.
     */
    public function __construct(string $name, ?callable $callback = null)
    {
        $this->name = $name;
        $this->callback = $callback;
    }

    /**
     * Retrieves the name of the new index.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * The number of primary shards that an index should have. Defaults to `1`.
     * This setting can only be set at index creation time. It cannot be changed
     * on a closed index.
     *
     * The number of shards are limited to 1024 per index. This limitation is a
     * safety limit to prevent accidental creation of indices that can
     * destabilize a cluster due to resource allocation. The limit can be
     * modified by specifying
     * `export ES_JAVA_OPTS="-Des.index.max_number_of_shards=128"` system
     * property on every node that is part of the cluster.
     *
     * @param int $shards Number of shards to configure.
     *
     * @return $this
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/master/index-modules.html#index-number-of-shards
     */
    public function shards(int $shards): self
    {
        $this->shards = $shards;

        return $this;
    }

    /**
     * The number of replicas each primary shard has. Defaults to `1`.
     *
     * @param int $replicas Number of replicas to configure.
     *
     * @return $this
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/master/index-modules.html#index-number-of-replicas
     */
    public function replicas(int $replicas): self
    {
        $this->replicas = $replicas;

        return $this;
    }

    /**
     * An index alias is a secondary name used to refer to one or more existing
     * indices. Most Elasticsearch APIs accept an index alias in place of
     * an index.
     *
     * APIs in Elasticsearch accept an index name when working against a
     * specific index, and several indices when applicable. The index aliases
     * API allows aliasing an index with a name, with all APIs automatically
     * converting the alias name to the actual index name. An alias can also be
     * mapped to more than one index, and when specifying it, the alias will
     * automatically expand to the aliased indices. An alias can also be
     * associated with a filter that will automatically be applied when
     * searching, and routing values. An alias cannot have the same name as
     * an index.
     *
     * @param string                        $alias   Name of the alias to add.
     * @param array|ArrayObject|string|null $options Options to pass to
     *                                               the alias.
     *
     * @return $this
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/master/indices-aliases.html
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/indices-create-index.html#create-index-aliases
     */
    public function alias(string $alias, $options = null): self
    {
        if (
            $options !== null &&
            ! is_string($options) &&
            ! is_array($options)
        ) {
            throw new TypeError(
                'Alias options may be passed as an array, a string ' .
                'routing key, or literal null.'
            );
        }

        $this->aliases[$alias] = $options ?? new ArrayObject();

        return $this;
    }

    /**
     * Configures the client to ignore bad HTTP requests.
     *
     * @param int ...$statusCodes HTTP Status codes to ignore.
     *
     * @return $this
     */
    public function ignore(int ...$statusCodes): self
    {
        $this->ignores = array_unique($statusCodes);

        return $this;
    }

    /**
     * Checks whether an index exists.
     *
     * @return bool
     */
    public function exists(): bool
    {
        return $this
            ->getConnection()
            ->getClient()
            ->indices()
            ->exists([
                'index' => $this->name,
            ]);
    }

    /**
     * Creates a new index
     *
     * @return array
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/indices-create-index.html
     */
    public function create(): array
    {
        $configuratorCallback = $this->callback;

        // By passing a callback, users have the possibility to optionally set
        // index configuration in a single, fluent command.
        // This API is a little unfortunate, so we should refactor that in the
        // next major release.
        if ($configuratorCallback) {
            $configuratorCallback($this);
        }

        $params = [
            self::PARAM_INDEX => $this->name,
            self::PARAM_BODY => [
                self::PARAM_SETTINGS => [
                    self::PARAM_SETTINGS_NUMBER_OF_SHARDS => $this->shards,
                    self::PARAM_SETTINGS_NUMBER_OF_REPLICAS => $this->replicas,
                ],
            ],
        ];

        if (count($this->ignores) > 0) {
            $params[self::PARAM_CLIENT] = [
                self::PARAM_CLIENT_IGNORE => $this->ignores,
            ];
        }

        if (count($this->aliases) > 0) {
            $params[self::PARAM_BODY][self::PARAM_ALIASES] = $this->aliases;
        }

        if (count($this->mappings) > 0) {
            $params[self::PARAM_BODY][self::PARAM_MAPPINGS] = $this->mappings;
        }

        return $this
            ->getConnection()
            ->getClient()
            ->indices()
            ->create($params);
    }

    /**
     * Deletes an existing index.
     *
     * @return array
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/indices-delete-index.html
     */
    public function drop(): array
    {
        return $this
            ->getConnection()
            ->getClient()
            ->indices()
            ->delete([
                self::PARAM_INDEX => $this->name,
                self::PARAM_CLIENT => [
                    self::PARAM_CLIENT_IGNORE => $this->ignores,
                ],
            ]);
    }

    /**
     * Sets the fields mappings.
     *
     * @param array $mappings
     *
     * @return $this
     */
    public function mapping(array $mappings = []): self
    {
        $this->mappings = $mappings;

        return $this;
    }

    /**
     * Retrieves the Elasticsearch  client instance.
     *
     * @return Client
     * @internal
     */
    public function getClient(): Client
    {
        return $this->getConnection()->getClient();
    }

    /**
     * Retrieves the active connection.
     *
     * @return ConnectionInterface
     * @internal
     */
    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }

    /**
     * Sets the active connection on the index.
     *
     * @param ConnectionInterface $connection
     *
     * @internal
     */
    public function setConnection(ConnectionInterface $connection): void
    {
        $this->connection = $connection;
    }
}


