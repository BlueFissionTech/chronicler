<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Storage\Evidence;

use DateTimeImmutable;
use InvalidArgumentException;

final class EvidenceRecord
{
    public const VERSION = 1;

    private const FORMS = ['raw', 'structured', 'derived'];
    private const TRUST = ['untrusted', 'observed', 'verified'];
    private const FRESHNESS = ['current', 'stale', 'unknown'];

    private function __construct(private readonly array $data)
    {
    }

    public static function capture(
        string $id,
        string $form,
        array $source,
        array $payload,
        array $provenance,
        array $parents = [],
        string $trust = 'untrusted',
        string $sensitivity = 'internal',
        string $freshness = 'unknown',
        string $recordedAt = '',
        string $scope = 'default'
    ): self {
        $data = [
            'version' => self::VERSION,
            'canonicalization' => CanonicalJson::ID,
            'id' => $id,
            'form' => $form,
            'scope' => $scope,
            'source' => $source,
            'payload' => $payload,
            'provenance' => $provenance,
            'parents' => $parents,
            'trust' => $trust,
            'sensitivity' => $sensitivity,
            'freshness' => $freshness,
            'authority' => 'none',
            'recorded_at' => $recordedAt !== '' ? $recordedAt : gmdate(DATE_RFC3339),
        ];
        $data['digest'] = CanonicalJson::digest($data);

        return self::fromArray($data);
    }

    public static function fromArray(array $data): self
    {
        self::validate($data);
        $record = new self($data);

        if (!$record->verifyDigest()) {
            throw new InvalidArgumentException('Evidence digest does not match the canonical record.');
        }

        return $record;
    }

    public function id(): string
    {
        return $this->data['id'];
    }

    public function digest(): string
    {
        return $this->data['digest'];
    }

    public function form(): string
    {
        return $this->data['form'];
    }

    public function scope(): string
    {
        return $this->data['scope'];
    }

    public function parents(): array
    {
        return $this->data['parents'];
    }

    public function verifyDigest(): bool
    {
        return hash_equals(CanonicalJson::digest($this->data), $this->digest());
    }

    public function toArray(): array
    {
        return $this->data;
    }

    private static function validate(array $data): void
    {
        $required = [
            'version', 'canonicalization', 'id', 'form', 'scope', 'source', 'payload',
            'provenance', 'parents', 'trust', 'sensitivity', 'freshness', 'authority',
            'recorded_at', 'digest',
        ];
        foreach ($required as $field) {
            if (!array_key_exists($field, $data)) {
                throw new InvalidArgumentException("Evidence record is missing {$field}.");
            }
        }

        if ($data['version'] !== self::VERSION || $data['canonicalization'] !== CanonicalJson::ID) {
            throw new InvalidArgumentException('Unsupported evidence record version or canonicalization.');
        }
        if (!is_string($data['id']) || $data['id'] === '' || !is_string($data['scope']) || $data['scope'] === '') {
            throw new InvalidArgumentException('Evidence id and scope are required.');
        }
        if (!in_array($data['form'], self::FORMS, true)) {
            throw new InvalidArgumentException('Evidence form must be raw, structured, or derived.');
        }
        if (!is_array($data['source']) || $data['source'] === [] || !is_array($data['provenance']) || $data['provenance'] === []) {
            throw new InvalidArgumentException('Evidence source and provenance are required.');
        }
        if (!is_array($data['payload']) || !is_array($data['parents'])) {
            throw new InvalidArgumentException('Evidence payload and parents must be arrays.');
        }
        if ($data['authority'] !== 'none') {
            throw new InvalidArgumentException('Evidence records cannot grant write authority.');
        }
        if (!in_array($data['trust'], self::TRUST, true) || !in_array($data['freshness'], self::FRESHNESS, true)) {
            throw new InvalidArgumentException('Evidence trust or freshness is invalid.');
        }
        if (!is_string($data['sensitivity']) || $data['sensitivity'] === '') {
            throw new InvalidArgumentException('Evidence sensitivity is required.');
        }
        if (DateTimeImmutable::createFromFormat(DATE_RFC3339, $data['recorded_at']) === false) {
            throw new InvalidArgumentException('Evidence recorded_at must be RFC3339.');
        }
        if (!is_string($data['digest']) || !preg_match('/^sha256:[a-f0-9]{64}$/', $data['digest'])) {
            throw new InvalidArgumentException('Evidence digest must be a SHA-256 digest.');
        }

        if ($data['form'] === 'raw' && $data['parents'] !== []) {
            throw new InvalidArgumentException('Raw evidence cannot have parent records.');
        }
        if ($data['form'] !== 'raw' && $data['parents'] === []) {
            throw new InvalidArgumentException('Structured and derived evidence require parent records.');
        }
        $parentIds = [];
        foreach ($data['parents'] as $parent) {
            if (!is_array($parent) || !isset($parent['id'], $parent['digest'])) {
                throw new InvalidArgumentException('Each evidence parent requires an id and digest.');
            }
            if ($parent['id'] === $data['id']) {
                throw new InvalidArgumentException('Evidence cannot parent itself.');
            }
            if (in_array($parent['id'], $parentIds, true)) {
                throw new InvalidArgumentException('Evidence parent references must be unique.');
            }
            $parentIds[] = $parent['id'];
            if (!is_string($parent['digest']) || !preg_match('/^sha256:[a-f0-9]{64}$/', $parent['digest'])) {
                throw new InvalidArgumentException('Parent digest must be a SHA-256 digest.');
            }
        }
    }
}
