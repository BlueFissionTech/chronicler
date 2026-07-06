<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Connections;

use BlueFission\Chronicler\Data\GraphQL\FieldNode;
use BlueFission\Chronicler\Data\GraphQL\SelectionSet;
use BlueFission\Chronicler\Storage\QueryBuilder;
use BlueFission\Str;
use BlueFission\Val;

final class GraphQLConnection extends StorageConnection
{
    public function __construct(?ConnectionProfile $profile = null, array $config = [])
    {
        parent::__construct(Val::isNotNull($profile) ? $profile : new ConnectionProfile('graphql'), $config);
        $this->profile()->driver = 'graphql';
    }

    public function request(FieldNode|SelectionSet $selection, string $operation = 'query'): QueryBuilder
    {
        $operation = Str::trim($operation);
        $body = Str::make($operation)->append(' ')->append($selection->toGraphQL())->val();

        return (new QueryBuilder('graphql', $operation, 'endpoint'))->parameter('query', $body);
    }
}
