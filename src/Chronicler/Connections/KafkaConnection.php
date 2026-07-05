<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Connections;

use BlueFission\Chronicler\Storage\Event\MessageEnvelope;
use BlueFission\Chronicler\Storage\QueryBuilder;
use BlueFission\Str;
use BlueFission\Val;

final class KafkaConnection extends StorageConnection
{
    public function __construct(?ConnectionProfile $profile = null, array $config = [])
    {
        parent::__construct(Val::isNotNull($profile) ? $profile : new ConnectionProfile('kafka'), $config);
        $this->profile()->driver = 'kafka';
    }

    public function publish(MessageEnvelope $envelope): QueryBuilder
    {
        return (new QueryBuilder('kafka', 'publish', (string)$envelope->topic))
            ->parameter('message', $envelope->pack());
    }

    public function consume(string $topic, ?string $group = null, ?int $partition = null): QueryBuilder
    {
        $query = new QueryBuilder('kafka', 'consume', Str::trim($topic));
        if (Str::is($group)) {
            $query->parameter('group', Str::trim($group));
        }
        if (Val::isNotNull($partition)) {
            $query->parameter('partition', $partition);
        }

        return $query;
    }
}
