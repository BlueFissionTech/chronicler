<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Storage\Graph;

use BlueFission\Arr;
use BlueFission\DataTypes;
use BlueFission\Obj;

final class Traversal extends Obj
{
    protected $_data = [
        'start' => null,
        'visited' => [],
        'path' => null,
    ];

    protected $_types = [
        'visited' => DataTypes::ARRAY,
    ];

    protected $_lockDataType = true;

    public function __construct(?Node $start = null)
    {
        parent::__construct();

        $this->path = new Path();
        if ($start) {
            $this->start($start);
        }
    }

    public function start(Node $node): self
    {
        $this->start = $node;
        $this->path()->addNode($node);
        $this->visit($node);

        return $this;
    }

    public function step(Edge $edge, Node $node): self
    {
        $this->path()->addEdge($edge);
        $this->path()->addNode($node);
        $this->visit($node);

        return $this;
    }

    public function visit(Node $node): self
    {
        $visited = $this->visited();
        $visited[$node->id] = true;
        $this->visited = $visited;

        return $this;
    }

    public function hasVisited(Node|string $node): bool
    {
        $id = $node instanceof Node ? $node->id : $node;

        return Arr::hasKey($this->visited(), $id);
    }

    public function path(): Path
    {
        return $this->path instanceof Path ? $this->path : new Path();
    }

    public function visited(): array
    {
        return Arr::toArray($this->visited);
    }
}
