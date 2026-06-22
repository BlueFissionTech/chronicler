<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Storage\Event;

use BlueFission\Arr;
use BlueFission\DataTypes;
use BlueFission\DevElation as Dev;
use BlueFission\Obj;

final class MessageEnvelope extends Obj
{
    protected $_data = [
        'topic' => '',
        'partition_key' => '',
        'partition' => null,
        'headers' => [],
        'timestamp' => '',
        'payload' => null,
    ];

    protected $_types = [
        'topic' => DataTypes::STRING,
        'partition_key' => DataTypes::STRING,
        'partition' => DataTypes::INTEGER,
        'headers' => DataTypes::ARRAY,
        'timestamp' => DataTypes::STRING,
    ];

    protected $_lockDataType = true;

    public function __construct(string $topic = '', mixed $payload = null, string $partitionKey = '', array $headers = [])
    {
        parent::__construct();

        $this->timestamp = gmdate('c');
        if ($topic !== '') {
            $this->topic = $topic;
        }
        if ($partitionKey !== '') {
            $this->partition_key = $partitionKey;
        }
        $this->headers($headers);
        $this->payload($payload);
    }

    public static function fromArray(array $message): self
    {
        $envelope = new self(
            (string)($message['topic'] ?? ''),
            $message['payload'] ?? null,
            (string)($message['partition_key'] ?? ''),
            Arr::toArray($message['headers'] ?? [])
        );

        if (isset($message['timestamp'])) {
            $envelope->timestamp = (string)$message['timestamp'];
        }
        if (isset($message['partition'])) {
            $envelope->partition = (int)$message['partition'];
        }

        return $envelope;
    }

    public function header(string $name, string $value): self
    {
        $headers = $this->headers();
        $headers[$name] = $value;
        $this->headers($headers);

        return $this;
    }

    public function headers(?array $headers = null): array
    {
        if ($headers !== null) {
            $this->headers = Arr::toArray($headers);
        }

        return Arr::toArray($this->headers);
    }

    public function payload(mixed $payload = null): mixed
    {
        if ($payload !== null) {
            $this->payload = Dev::apply(null, $payload);
        }

        return $this->payload;
    }

    public function pack(): array
    {
        return [
            'topic' => $this->topic,
            'partition_key' => $this->partition_key,
            'partition' => $this->partition,
            'headers' => $this->headers(),
            'timestamp' => $this->timestamp,
            'payload' => Dev::apply(null, $this->payload),
        ];
    }
}
