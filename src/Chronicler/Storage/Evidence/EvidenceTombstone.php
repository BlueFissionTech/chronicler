<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Storage\Evidence;

use DateTimeImmutable;
use InvalidArgumentException;

final class EvidenceTombstone
{
    public const VERSION = 1;

    private function __construct(private readonly array $data)
    {
    }

    public static function create(
        string $id,
        EvidenceRecord $target,
        string $reason,
        string $issuer,
        int $sequence,
        string $idempotencyKey,
        string $effectiveAt = '',
        ?string $supersedes = null,
        ?array $replacement = null
    ): self {
        $data = [
            'version' => self::VERSION,
            'canonicalization' => CanonicalJson::ID,
            'id' => $id,
            'target_id' => $target->id(),
            'target_digest' => $target->digest(),
            'scope' => $target->scope(),
            'reason' => $reason,
            'issuer' => $issuer,
            'effective_at' => $effectiveAt !== '' ? $effectiveAt : gmdate(DATE_RFC3339),
            'sequence' => $sequence,
            'idempotency_key' => $idempotencyKey,
            'supersedes' => $supersedes,
            'replacement' => $replacement,
        ];
        $data['digest'] = CanonicalJson::digest($data);

        return self::fromArray($data);
    }

    public static function fromArray(array $data): self
    {
        $data = CanonicalJson::snapshot($data);
        self::validate($data);
        if (!hash_equals(CanonicalJson::digest($data), $data['digest'])) {
            throw new InvalidArgumentException('Tombstone digest does not match the canonical record.');
        }

        return new self($data);
    }

    public function id(): string
    {
        return $this->data['id'];
    }

    public function targetId(): string
    {
        return $this->data['target_id'];
    }

    public function version(): int
    {
        return $this->data['version'];
    }

    public function canonicalization(): string
    {
        return $this->data['canonicalization'];
    }

    public function digest(): string
    {
        return $this->data['digest'];
    }

    public function scope(): string
    {
        return $this->data['scope'];
    }

    public function reason(): string
    {
        return $this->data['reason'];
    }

    public function issuer(): string
    {
        return $this->data['issuer'];
    }

    public function effectiveAt(): string
    {
        return $this->data['effective_at'];
    }

    public function supersedes(): ?string
    {
        return $this->data['supersedes'];
    }

    public function replacement(): ?array
    {
        return $this->data['replacement'];
    }

    public function targetDigest(): string
    {
        return $this->data['target_digest'];
    }

    public function sequence(): int
    {
        return $this->data['sequence'];
    }

    public function idempotencyKey(): string
    {
        return $this->data['idempotency_key'];
    }

    public function appliesTo(EvidenceRecord $record): bool
    {
        return $record->id() === $this->targetId()
            && $record->digest() === $this->targetDigest()
            && hash_equals(CanonicalJson::encode(['scope' => $record->scope()]), CanonicalJson::encode(['scope' => $this->data['scope']]));
    }

    public function toArray(): array
    {
        return $this->data;
    }

    private static function validate(array $data): void
    {
        $required = [
            'version', 'canonicalization', 'id', 'target_id', 'target_digest', 'scope',
            'reason', 'issuer', 'effective_at', 'sequence', 'idempotency_key',
            'supersedes', 'replacement', 'digest',
        ];
        foreach ($required as $field) {
            if (!array_key_exists($field, $data)) {
                throw new InvalidArgumentException("Tombstone is missing {$field}.");
            }
        }

        foreach (['id', 'target_id', 'scope', 'reason', 'issuer', 'idempotency_key'] as $field) {
            if (!is_string($data[$field]) || $data[$field] === '') {
                throw new InvalidArgumentException("Tombstone {$field} is required.");
            }
        }
        if ($data['version'] !== self::VERSION || $data['canonicalization'] !== CanonicalJson::ID) {
            throw new InvalidArgumentException('Unsupported tombstone version or canonicalization.');
        }
        if (!is_string($data['target_digest']) || !preg_match('/^sha256:[a-f0-9]{64}$/', $data['target_digest'])) {
            throw new InvalidArgumentException('Tombstone target digest must be a SHA-256 digest.');
        }
        if (!is_int($data['sequence']) || $data['sequence'] < 1) {
            throw new InvalidArgumentException('Tombstone sequence must be positive.');
        }
        if ($data['supersedes'] !== null && (!is_string($data['supersedes']) || $data['supersedes'] === '')) {
            throw new InvalidArgumentException('Tombstone supersedes must be a non-empty id when provided.');
        }
        if ($data['replacement'] !== null) {
            if (!is_array($data['replacement']) || !isset($data['replacement']['id'], $data['replacement']['digest'])) {
                throw new InvalidArgumentException('Tombstone replacement requires an id and digest.');
            }
            if (!is_string($data['replacement']['id']) || $data['replacement']['id'] === '') {
                throw new InvalidArgumentException('Replacement id must be a non-empty string.');
            }
            if (!is_string($data['replacement']['digest']) || !preg_match('/^sha256:[a-f0-9]{64}$/', $data['replacement']['digest'])) {
                throw new InvalidArgumentException('Replacement digest must be a SHA-256 digest.');
            }
        }
        if (!is_string($data['effective_at']) || DateTimeImmutable::createFromFormat(DATE_RFC3339, $data['effective_at']) === false) {
            throw new InvalidArgumentException('Tombstone effective_at must be RFC3339.');
        }
        if (!is_string($data['digest']) || !preg_match('/^sha256:[a-f0-9]{64}$/', $data['digest'])) {
            throw new InvalidArgumentException('Tombstone digest must be a SHA-256 digest.');
        }
    }
}
