<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Data;

use BlueFission\Arr;
use BlueFission\DataTypes;
use BlueFission\DevElation as Dev;
use BlueFission\Obj;
use BlueFission\Obj as DynamicObject;

final class StoragePacket extends Obj
{
    public const TYPE_EVENT = 'event';
    public const TYPE_FEATURE = 'feature';
    public const TYPE_PHENOMENON = 'phenomenon';
    public const TYPE_EVIDENCE = 'evidence';
    public const TYPE_STRUCTURE = 'structure';
    public const TYPE_OBJECT = 'object';

    protected $_data = [
        'type' => self::TYPE_EVIDENCE,
        'source' => [],
        'payload' => [],
        'provenance' => [],
        'confidence' => 1.0,
        'visibility' => 'internal',
        'authority' => [],
        'diagnostics' => [],
        'meta' => [],
    ];

    protected $_types = [
        'type' => DataTypes::STRING,
        'source' => DataTypes::ARRAY,
        'payload' => DataTypes::ARRAY,
        'provenance' => DataTypes::ARRAY,
        'confidence' => DataTypes::FLOAT,
        'visibility' => DataTypes::STRING,
        'authority' => DataTypes::ARRAY,
        'diagnostics' => DataTypes::ARRAY,
        'meta' => DataTypes::ARRAY,
    ];

    protected $_lockDataType = true;

    public function __construct(
        string $type = self::TYPE_EVIDENCE,
        array|object $payload = [],
        array $source = [],
        array $provenance = [],
        float $confidence = 1.0,
        string $visibility = 'internal',
        array $authority = [],
        array $meta = []
    ) {
        parent::__construct();

        $this->type = $type;
        $this->payload($payload);
        $this->source($source);
        $this->provenance($provenance);
        $this->confidence = max(0.0, min(1.0, $confidence));
        $this->visibility = $visibility;
        $this->authority($authority);
        $this->readonly();
        $this->meta($meta);
    }

    public static function fromStorageObject(StorageObject $object, string $type = self::TYPE_EVIDENCE): self
    {
        return new self(
            $type,
            $object->payload(),
            Arr::toArray($object->meta()['source'] ?? []),
            Arr::toArray($object->meta()['provenance'] ?? []),
            (float)($object->meta()['confidence'] ?? 1.0),
            (string)($object->meta()['visibility'] ?? 'internal'),
            Arr::toArray($object->meta()['authority'] ?? []),
            Arr::toArray($object->meta())
        );
    }

    public function source(?array $source = null): array
    {
        if ($source !== null) {
            $this->source = Arr::toArray(Dev::apply(null, $source));
        }

        return Arr::toArray($this->source);
    }

    public function payload(array|object|null $payload = null): array
    {
        if ($payload !== null) {
            $payload = Dev::apply(null, $payload);
            if (!Arr::is($payload)) {
                $object = $payload instanceof DynamicObject ? $payload : (new DynamicObject())->assign($payload);
                $payload = $object->toArray();
            }
            $this->payload = Arr::toArray($payload);
        }

        return Arr::toArray($this->payload);
    }

    public function provenance(?array $provenance = null): array
    {
        if ($provenance !== null) {
            $this->provenance = Arr::toArray(Dev::apply(null, $provenance));
        }

        return Arr::toArray($this->provenance);
    }

    public function authority(?array $authority = null): array
    {
        if ($authority !== null) {
            $this->authority = Arr::toArray(Dev::apply(null, $authority));
        }

        return Arr::toArray($this->authority);
    }

    public function readonly(): self
    {
        $authority = $this->authority();
        $authority['can_write'] = false;
        $this->authority($authority);

        return $this;
    }

    public function meta(?array $meta = null): array
    {
        if ($meta !== null) {
            $this->meta = Arr::toArray(Dev::apply(null, $meta));
        }

        return Arr::toArray($this->meta);
    }

    public function diagnostic(string $code, string $message, string $severity = 'warning'): self
    {
        $diagnostics = $this->diagnostics();
        $diagnostics[] = [
            'code' => $code,
            'message' => $message,
            'severity' => $severity,
        ];
        $this->diagnostics = $diagnostics;

        return $this;
    }

    public function diagnoseTraceability(): self
    {
        if (!Arr::isNotEmpty($this->source())) {
            $this->diagnostic('missing_source', 'Packet source metadata is not available.');
        }

        if (!Arr::isNotEmpty($this->provenance())) {
            $this->diagnostic('missing_provenance', 'Packet provenance metadata is not available.');
        }

        if (Arr::make($this->authority())->count() < 2) {
            $this->diagnostic('missing_authority', 'Packet authority metadata is not available.');
        }

        return $this;
    }

    public function diagnostics(): array
    {
        return Arr::toArray($this->diagnostics);
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'source' => $this->source(),
            'payload' => $this->payload(),
            'provenance' => $this->provenance(),
            'confidence' => (float)$this->confidence,
            'visibility' => $this->visibility,
            'authority' => $this->authority(),
            'diagnostics' => $this->diagnostics(),
            'meta' => $this->meta(),
        ];
    }
}
