<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Data;

use BlueFission\Arr;
use BlueFission\Chronicler\Support\DevElationValues;
use BlueFission\DataTypes;
use BlueFission\Num;
use BlueFission\Obj;
use BlueFission\Str;
use BlueFission\Val;

final class StoragePacket extends Obj
{
    use DevElationValues;

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

        $this->type = Str::trim($type);
        $this->payload($payload);
        $this->source($source);
        $this->provenance($provenance);
        $this->confidence = Num::max(0.0, Num::min(1.0, $confidence));
        $this->visibility = Str::trim($visibility);
        $this->authority($authority);
        $this->readonly();
        $this->meta($meta);
    }

    public static function fromStorageObject(StorageObject $object, string $type = self::TYPE_EVIDENCE): self
    {
        $meta = $object->meta();

        return new self(
            $type,
            $object->payload(),
            Arr::toArray(Arr::getPath($meta, 'source', [])),
            Arr::toArray(Arr::getPath($meta, 'provenance', [])),
            (float)Arr::getPath($meta, 'confidence', 1.0),
            (string)Arr::getPath($meta, 'visibility', 'internal'),
            Arr::toArray(Arr::getPath($meta, 'authority', [])),
            Arr::toArray($meta)
        );
    }

    public function source(?array $source = null): array
    {
        if (Val::isNotNull($source)) {
            $this->source = $this->valueArray($source);
        }

        return Arr::toArray($this->source);
    }

    public function payload(array|object|null $payload = null): array
    {
        if (Val::isNotNull($payload)) {
            $payload = $this->applyValue($payload);
            if (!Arr::is($payload)) {
                $object = $payload instanceof Obj ? $payload : (new Obj())->assign($payload);
                $payload = $object->toArray();
            }
            $this->payload = $this->valueArray($payload);
        }

        return Arr::toArray($this->payload);
    }

    public function provenance(?array $provenance = null): array
    {
        if (Val::isNotNull($provenance)) {
            $this->provenance = $this->valueArray($provenance);
        }

        return Arr::toArray($this->provenance);
    }

    public function authority(?array $authority = null): array
    {
        if (Val::isNotNull($authority)) {
            $this->authority = $this->valueArray($authority);
        }

        return Arr::toArray($this->authority);
    }

    public function readonly(): self
    {
        $authority = $this->authority();
        $authority = $this->assignArrayValue($authority, 'can_write', false);
        $this->authority($authority);

        return $this;
    }

    public function meta(?array $meta = null): array
    {
        if (Val::isNotNull($meta)) {
            $this->meta = $this->valueArray($meta);
        }

        return Arr::toArray($this->meta);
    }

    public function diagnostic(string $code, string $message, string $severity = 'warning'): self
    {
        $this->diagnostics = $this->appendArrayValue($this->diagnostics(), [
            'code' => $code,
            'message' => $message,
            'severity' => $severity,
        ]);

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

        if (Arr::count($this->authority()) < 2) {
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
