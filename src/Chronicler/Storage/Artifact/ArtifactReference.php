<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Storage\Artifact;

use BlueFission\Arr;
use BlueFission\Chronicler\Data\StoragePacket;
use BlueFission\Chronicler\Support\DevElationValues;
use BlueFission\DataTypes;
use BlueFission\Num;
use BlueFission\Obj;
use BlueFission\Str;
use BlueFission\Val;

final class ArtifactReference extends Obj
{
    use DevElationValues;

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

        $this->id = Str::trim($id);
        $this->uri = Str::trim($uri);
        $this->type = Str::trim($type);
        $this->media_type = Str::trim($mediaType);
        $this->checksum = Str::trim($checksum);
        if (Val::isNotNull($size)) {
            $this->size = (int)Num::max(0, $size);
        }
        $this->retrieval($retrieval);
        $this->meta($meta);
    }

    public static function fromArray(array $definition): self
    {
        return new self(
            (string)Arr::getPath($definition, 'id', ''),
            (string)Arr::getPath($definition, 'uri', ''),
            (string)Arr::getPath($definition, 'type', self::TYPE_GENERATED),
            (string)Arr::getPath($definition, 'media_type', ''),
            (string)Arr::getPath($definition, 'checksum', ''),
            Arr::hasKey($definition, 'size') ? (int)Arr::getPath($definition, 'size') : null,
            Arr::toArray(Arr::getPath($definition, 'retrieval', [])),
            Arr::toArray(Arr::getPath($definition, 'meta', []))
        );
    }

    public function retrieval(RetrievalMetadata|array|null $retrieval = null): ?RetrievalMetadata
    {
        if (Val::isNotNull($retrieval)) {
            $this->retrieval = $retrieval instanceof RetrievalMetadata
                ? $retrieval
                : new RetrievalMetadata(
                    (string)Arr::getPath($retrieval, 'method', ''),
                    (string)Arr::getPath($retrieval, 'uri', ''),
                    Arr::toArray(Arr::getPath($retrieval, 'headers', [])),
                    Arr::hasKey($retrieval, 'expires_at') ? (string)Arr::getPath($retrieval, 'expires_at') : null,
                    Arr::toArray(Arr::getPath($retrieval, 'hints', []))
                );
        }

        return $this->retrieval instanceof RetrievalMetadata ? $this->retrieval : null;
    }

    public function meta(?array $meta = null): array
    {
        if (Val::isNotNull($meta)) {
            $this->meta = $this->valueArray($meta);
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
        $retrieval = $this->retrieval();

        return [
            'id' => $this->id,
            'type' => $this->type,
            'uri' => $this->uri,
            'media_type' => $this->media_type,
            'checksum' => $this->checksum,
            'size' => $this->size,
            'retrieval' => $retrieval instanceof RetrievalMetadata ? $retrieval->toArray() : [],
            'meta' => $this->meta(),
        ];
    }
}
