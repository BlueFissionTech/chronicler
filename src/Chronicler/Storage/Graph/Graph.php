<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Storage\Graph;

use BlueFission\Arr;
use BlueFission\Data\Graph\Graph as BaseGraph;
use BlueFission\Data\Graph\Node as BaseNode;
use BlueFission\Prototypes\Domain;
use BlueFission\Prototypes\Proto;

class Graph extends BaseGraph
{
    use Proto;
    use Domain;

    public function addNode(BaseNode $node): void
    {
        parent::addNode($node);

        $this->addMember($node, $node->getName());
    }

    public function node(string $id): ?Node
    {
        $node = parent::node($id);

        return $node instanceof Node ? $node : null;
    }

    public function connectEdge(Edge $edge): void
    {
        $attributes = $edge->properties();
        $attributes['id'] = $edge->id;
        $attributes['type'] = $edge->type;

        $this->connect((string)$edge->from, (string)$edge->to, $attributes, (bool)$edge->directed);
    }

    public function relationship(string|Node $from, string|Node $to): ?array
    {
        $from = $from instanceof Node ? $from->getName() : $from;
        $to = $to instanceof Node ? $to->getName() : $to;

        return $this->edgeAttributes($from, $to);
    }

    public function route(string|Node $start, string|Node $end, ?callable $fitnessFunction = null): Path
    {
        $start = $start instanceof Node ? $start->getName() : $start;
        $end = $end instanceof Node ? $end->getName() : $end;
        $path = new Path($this->directed);
        $nodeNames = $this->shortestPath($start, $end, $fitnessFunction);

        foreach ($nodeNames as $index => $nodeName) {
            $node = $this->node((string)$nodeName) ?? new Node((string)$nodeName);
            $path->addNode($node);

            $next = $nodeNames[$index + 1] ?? null;
            if ($next === null) {
                continue;
            }

            $attributes = Arr::toArray($this->edgeAttributes((string)$nodeName, (string)$next) ?? []);
            $path->addEdge(new Edge(
                (string)($attributes['id'] ?? $nodeName . ':' . $next),
                (string)($attributes['type'] ?? 'RELATED_TO'),
                (string)$nodeName,
                (string)$next,
                $attributes,
                (bool)$this->directed
            ));
        }

        return $path;
    }

    protected function ensureNode(string $id): BaseNode
    {
        $node = $this->node($id);
        if ($node instanceof Node) {
            return $node;
        }

        $node = new Node($id);
        $this->addNode($node);

        return $node;
    }
}
