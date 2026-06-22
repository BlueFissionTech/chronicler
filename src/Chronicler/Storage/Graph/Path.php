<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Storage\Graph;

use BlueFission\Arr;
use BlueFission\Collections\Collection;
use BlueFission\Data\Graph\Node as BaseNode;

final class Path extends Graph
{
    /** @var array<int, Node> */
    private array $routeNodes = [];

    /** @var array<int, Edge> */
    private array $routeEdges = [];

    public function __construct(bool $directed = true)
    {
        parent::__construct([], $directed);
    }

    public function addNode(BaseNode $node): void
    {
        parent::addNode($node);

        if ($node instanceof Node) {
            $this->routeNodes[] = $node;
        }
    }

    public function addEdge(Edge $edge): self
    {
        $this->connectEdge($edge);
        $this->routeEdges[] = $edge;

        return $this;
    }

    /** @return array<int, Node> */
    public function nodes(): array
    {
        return $this->routeNodes;
    }

    /** @return array<int, Edge> */
    public function edges(): array
    {
        return $this->routeEdges;
    }

    public function length(): int
    {
        return Arr::make($this->edges())->count();
    }

    public function toArray(): array
    {
        return [
            'nodes' => (new Collection($this->nodes()))
                ->map(static fn (Node $node): array => $node->toArray())
                ->toArray(),
            'edges' => (new Collection($this->edges()))
                ->map(static fn (Edge $edge): array => $edge->toArray())
                ->toArray(),
        ];
    }
}
