<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Tests\Connections;

use BlueFission\Chronicler\Connections\GraphQLConnection;
use BlueFission\Chronicler\Connections\KafkaConnection;
use BlueFission\Chronicler\Connections\Neo4jConnection;
use BlueFission\Chronicler\Data\GraphQL\FieldNode;
use BlueFission\Chronicler\Storage\Event\MessageEnvelope;
use BlueFission\Chronicler\Storage\Graph\Node;
use PHPUnit\Framework\TestCase;

final class ConnectionScaffoldTest extends TestCase
{
    public function testKafkaConnectionBuildsPublishQueryWithoutNetwork(): void
    {
        $query = (new KafkaConnection())->publish(new MessageEnvelope('orders', ['id' => 1], 'order-1'));

        $this->assertSame('kafka', $query->toArray()['driver']);
        $this->assertSame('publish', $query->toArray()['operation']);
        $this->assertSame('orders', $query->toArray()['target']);
    }

    public function testNeo4jConnectionBuildsNodeQueryWithoutNetwork(): void
    {
        $query = (new Neo4jConnection())->createNode(new Node('a', ['name' => 'Alice'], ['Person']));

        $this->assertSame('neo4j', $query->toArray()['driver']);
        $this->assertSame('create_node', $query->toArray()['operation']);
        $this->assertSame('Alice', $query->toArray()['parameters']['node']['properties']['name']);
    }

    public function testGraphQLConnectionBuildsRequestQueryWithoutNetwork(): void
    {
        $field = (new FieldNode('viewer'))->select('id');
        $query = (new GraphQLConnection())->request($field);

        $this->assertSame('graphql', $query->toArray()['driver']);
        $this->assertStringContainsString('viewer', $query->toArray()['parameters']['query']);
    }
}
