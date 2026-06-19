<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Connections;

use BlueFission\Chronicler\Storage\Event\MessageEnvelope;
use BlueFission\Chronicler\Storage\QueryBuilder;

final class KafkaConnection extends StorageConnection
{
    public function __construct(?ConnectionProfile $profile = null, array $config = [])
    {
        parent::__construct($profile ?? new ConnectionProfile('kafka'), $config);
        $this->profile()->driver = 'kafka';
    }

    public function publish(MessageEnvelope $envelope): QueryBuilder
    {
        return (new QueryBuilder('kafka', 'publish', (string)$envelope->topic))
            ->parameter('message', $envelope->pack());
    }

    public function consume(string $topic, ?string $group = null, ?int $partition = null): QueryBuilder
    {
        $query = new QueryBuilder('kafka', 'consume', $topic);
        if ($group !== null) {
            $query->parameter('group', $group);
        }
        if ($partition !== null) {
            $query->parameter('partition', $partition);
        }

        return $query;
    }
}
