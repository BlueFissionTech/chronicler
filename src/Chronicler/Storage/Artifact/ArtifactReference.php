<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Storage\Artifact;

use BlueFission\Arr;
use BlueFission\Chronicler\Data\StoragePacket;
use BlueFission\DataTypes;
use BlueFission\DevElation as Dev;
use BlueFission\Obj;

final class ArtifactReference extends Obj
{
    public const TYPE_MEDIA = 'media';
    public const TYPE_DOCUMENT = 'document';
    public const TYPE_DATASET = 'dataset';
    public const TYPE_GENERATED = 'generated';

    protected $_data = [
        'id' => '',
        'type' => self::TYPE_GENERATED,
        'uri' => '',
        'media_type' => '',
        'checksum' => '',
        'size' => null,
        'retrieval' => null,
        'meta' => [],
    ];

    protected $_types = [
        'id' => DataTypes::STRING,
        'type' => DataTypes::STRING,
        'uri' => DataTypes::STRING,
        'media_type' => DataTypes::STRING,
        'checksum' => DataTypes::STRING,
        'size' => DataTypes::INTEGER,
        'retrieval' => DataTypes::GENERIC,
        'meta' => DataTypes::ARRAY,
    ];

    protected $_lockDataType = true;

    public function __construct(
        string $id,
        string $uri,
        string $type = self::TYPE_GENERATED,
        string $mediaType = '',
        string $checksum = '',
        ?int $size = null,
        RetrievalMetadata|array|null $retrieval = null,
        array $meta = []
    ) {
        parent::__construct();

        $this->id = $id;
        $this->uri = $uri;
        $this->type = $type;
        $this->media_type = $mediaType;
        $this->checksum = $checksum;
        if ($size !== null) {
            $this->size = $size;
        }
        $this->retrieval($retrieval);
        $this->meta($meta);
    }

    public static function fromArray(array $definition): self
    {
        return new self(
            (string)($definition['id'] ?? ''),
            (string)($definition['uri'] ?? ''),
            (string)($definition['type'] ?? self::TYPE_GENERATED),
            (string)($definition['media_type'] ?? ''),
            (string)($definition['checksum'] ?? ''),
            isset($definition['size']) ? (int)$definition['size'] : null,
            Arr::toArray($definition['retrieval'] ?? []),
            Arr::toArray($definition['meta'] ?? [])
        );
    }

    public function retrieval(RetrievalMetadata|array|null $retrieval = null): ?RetrievalMetadata
    {
        if ($retrieval !== null) {
            $this->retrieval = $retrieval instanceof RetrievalMetadata
                ? $retrieval
                : new RetrievalMetadata(
                    (string)($retrieval['method'] ?? ''),
                    (string)($retrieval['uri'] ?? ''),
                    Arr::toArray($retrieval['headers'] ?? []),
                    isset($retrieval['expires_at']) ? (string)$retrieval['expires_at'] : null,
                    Arr::toArray($retrieval['hints'] ?? [])
                );
        }

        return $this->retrieval instanceof RetrievalMetadata ? $this->retrieval : null;
    }

    public function meta(?array $meta = null): array
    {
        if ($meta !== null) {
            $this->meta = Arr::toArray(Dev::apply(null, $meta));
        }

        return Arr::toArray($this->meta);
    }

    public function toPacket(array $source = [], array $provenance = [], float $confidence = 1.0, string $visibility = 'internal'): StoragePacket
    {
        return new StoragePacket(
            StoragePacket::TYPE_OBJECT,
            ['artifact' => $this->toArray()],
            $source,
            $provenance,
            $confidence,
            $visibility,
            ['scope' => 'artifact_reference']
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'uri' => $this->uri,
            'media_type' => $this->media_type,
            'checksum' => $this->checksum,
            'size' => $this->size,
            'retrieval' => $this->retrieval()?->toArray() ?? [],
            'meta' => $this->meta(),
        ];
    }
}
