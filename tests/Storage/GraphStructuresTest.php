<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Tests\Storage;

use BlueFission\Chronicler\Storage\Graph\Graph;
use BlueFission\Chronicler\Storage\Graph\Edge;
use BlueFission\Chronicler\Storage\Graph\Node;
use BlueFission\Chronicler\Storage\Graph\Traversal;
use BlueFission\Data\Graph\Graph as BaseGraph;
use BlueFission\Data\Graph\Node as BaseNode;
use PHPUnit\Framework\TestCase;

final class GraphStructuresTest extends TestCase
{
    public function testNodeEdgeAndTraversalRepresentGraphPath(): void
    {
        $start = new Node('a', ['name' => 'Alice'], ['Person']);
        $end = new Node('b', ['name' => 'Bob'], ['Person']);
        $edge = new Edge('e1', 'KNOWS', $start, $end, ['since' => 2026]);

        $traversal = new Traversal($start);
        $traversal->step($edge, $end);

        $this->assertTrue($edge->connects($start, $end));
        $this->assertTrue($traversal->hasVisited($end));
        $this->assertSame(1, $traversal->path()->length());
        $this->assertSame('Alice', $traversal->path()->toArray()['nodes'][0]['properties']['name']);
    }

    public function testGraphObjectsExtendDevelationGraphBase(): void
    {
        $graph = new Graph();
        $node = new Node('a', ['name' => 'Alice'], ['Person']);

        $graph->addNode($node);

        $this->assertInstanceOf(BaseGraph::class, $graph);
        $this->assertInstanceOf(BaseNode::class, $node);
        $this->assertSame(['a'], $graph->shortestPath('a', 'a'));
        $this->assertArrayHasKey('a', $graph->members());
    }

    public function testRouteUsesBaseGraphPathLogic(): void
    {
        $graph = new Graph();
        $start = new Node('a', ['name' => 'Alice'], ['Person']);
        $end = new Node('b', ['name' => 'Bob'], ['Person']);

        $graph->addNode($start);
        $graph->addNode($end);
        $graph->connectEdge(new Edge('e1', 'KNOWS', $start, $end, ['weight' => 1]));

        $path = $graph->route('a', 'b');

        $this->assertInstanceOf(BaseGraph::class, $path);
        $this->assertSame(['a', 'b'], $path->shortestPath('a', 'b'));
        $this->assertSame('KNOWS', $graph->relationship($start, $end)['type']);
    }
}
