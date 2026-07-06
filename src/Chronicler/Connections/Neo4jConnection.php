<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Connections;

use BlueFission\Chronicler\Storage\Graph\Node;
use BlueFission\Chronicler\Storage\QueryBuilder;
use BlueFission\Str;
use BlueFission\Val;

final class Neo4jConnection extends StorageConnection
{
    public function __construct(?ConnectionProfile $profile = null, array $config = [])
    {
        parent::__construct(Val::isNotNull($profile) ? $profile : new ConnectionProfile('neo4j'), $config);
        $this->profile()->driver = 'neo4j';
    }

    public function match(string $pattern): QueryBuilder
    {
        return (new QueryBuilder('neo4j', 'match', 'graph'))->clause('pattern', Str::trim($pattern));
    }

    public function createNode(Node $node): QueryBuilder
    {
        return (new QueryBuilder('neo4j', 'create_node', 'graph'))->parameter('node', $node->toArray());
    }
}
