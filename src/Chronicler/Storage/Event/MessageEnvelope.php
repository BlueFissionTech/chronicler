<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Storage\Event;

use BlueFission\Arr;
use BlueFission\Chronicler\Support\DevElationValues;
use BlueFission\Date;
use BlueFission\DataTypes;
use BlueFission\Obj;
use BlueFission\Str;
use BlueFission\Val;

final class MessageEnvelope extends Obj
{
    use DevElationValues;

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

        $this->timestamp = Date::now()->format('c')->val();
        if (Str::isNotEmpty($topic)) {
            $this->topic = Str::trim($topic);
        }
        if (Str::isNotEmpty($partitionKey)) {
            $this->partition_key = Str::trim($partitionKey);
        }
        $this->headers($headers);
        $this->payload($payload);
    }

    public static function fromArray(array $message): self
    {
        $envelope = new self(
            (string)Arr::getPath($message, 'topic', ''),
            Arr::getPath($message, 'payload'),
            (string)Arr::getPath($message, 'partition_key', ''),
            Arr::toArray(Arr::getPath($message, 'headers', []))
        );

        if (Arr::hasKey($message, 'timestamp')) {
            $envelope->timestamp = (string)Arr::getPath($message, 'timestamp');
        }
        if (Arr::hasKey($message, 'partition')) {
            $envelope->partition = (int)Arr::getPath($message, 'partition');
        }

        return $envelope;
    }

    public function header(string $name, string $value): self
    {
        $this->headers($this->assignArrayValue($this->headers(), Str::trim($name), Str::trim($value)));

        return $this;
    }

    public function headers(?array $headers = null): array
    {
        if (Val::isNotNull($headers)) {
            $this->headers = $this->valueArray($headers);
        }

        return Arr::toArray($this->headers);
    }

    public function payload(mixed $payload = null): mixed
    {
        if (Val::isNotNull($payload)) {
            $this->payload = $this->applyValue($payload);
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
            'payload' => $this->applyValue($this->payload),
        ];
    }
}
