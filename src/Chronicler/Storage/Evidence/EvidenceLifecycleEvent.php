<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Storage\Evidence;

use DateTimeImmutable;
use InvalidArgumentException;

final class EvidenceLifecycleEvent
{
    public const VERSION = 1;

    private const TYPES = ['appended', 'quarantined', 'invalidated', 'withdrawn'];

    private function __construct(private readonly array $data)
    {
    }

    public static function create(
        EvidenceRecord $record,
        string $type,
        string $reason,
        string $actor,
        int $sequence,
        string $occurredAt = ''
    ): self {
        $data = [
            'version' => self::VERSION,
            'canonicalization' => CanonicalJson::ID,
            'record_id' => $record->id(),
            'record_digest' => $record->digest(),
            'type' => $type,
            'reason' => $reason,
            'actor' => $actor,
            'occurred_at' => $occurredAt !== '' ? $occurredAt : gmdate(DATE_RFC3339),
            'sequence' => $sequence,
        ];
        $data['id'] = 'sha256:' . hash('sha256', CanonicalJson::encode($data));
        $data['digest'] = CanonicalJson::digest($data);

        return self::fromArray($data);
    }

    public static function fromArray(array $data): self
    {
        self::validate($data);
        if (!hash_equals(CanonicalJson::digest($data), $data['digest'])) {
            throw new InvalidArgumentException('Lifecycle event digest does not match the canonical event.');
        }

        return new self($data);
    }

    public function version(): int
    {
        return $this->data['version'];
    }

    public function canonicalization(): string
    {
        return $this->data['canonicalization'];
    }

    public function id(): string
    {
        return $this->data['id'];
    }

    public function digest(): string
    {
        return $this->data['digest'];
    }

    public function recordId(): string
    {
        return $this->data['record_id'];
    }

    public function recordDigest(): string
    {
        return $this->data['record_digest'];
    }

    public function type(): string
    {
        return $this->data['type'];
    }

    public function reason(): string
    {
        return $this->data['reason'];
    }

    public function actor(): string
    {
        return $this->data['actor'];
    }

    public function occurredAt(): string
    {
        return $this->data['occurred_at'];
    }

    public function sequence(): int
    {
        return $this->data['sequence'];
    }

    public function toArray(): array
    {
        return $this->data;
    }

    private static function validate(array $data): void
    {
        $required = [
            'version', 'canonicalization', 'id', 'record_id', 'record_digest',
            'type', 'reason', 'actor', 'occurred_at', 'sequence', 'digest',
        ];
        foreach ($required as $field) {
            if (!array_key_exists($field, $data)) {
                throw new InvalidArgumentException("Lifecycle event is missing {$field}.");
            }
        }
        if ($data['version'] !== self::VERSION || $data['canonicalization'] !== CanonicalJson::ID) {
            throw new InvalidArgumentException('Unsupported lifecycle event version or canonicalization.');
        }
        foreach (['record_id', 'type', 'reason', 'actor'] as $field) {
            if (!is_string($data[$field]) || $data[$field] === '') {
                throw new InvalidArgumentException("Lifecycle event {$field} is required.");
            }
        }
        if (!in_array($data['type'], self::TYPES, true)) {
            throw new InvalidArgumentException('Lifecycle event type is invalid.');
        }
        foreach (['id', 'record_digest', 'digest'] as $field) {
            if (!is_string($data[$field]) || !preg_match('/^sha256:[a-f0-9]{64}$/', $data[$field])) {
                throw new InvalidArgumentException("Lifecycle event {$field} must be a SHA-256 digest.");
            }
        }
        if (!is_int($data['sequence']) || $data['sequence'] < 1) {
            throw new InvalidArgumentException('Lifecycle event sequence must be positive.');
        }
        if (!is_string($data['occurred_at']) || DateTimeImmutable::createFromFormat(DATE_RFC3339, $data['occurred_at']) === false) {
            throw new InvalidArgumentException('Lifecycle event occurred_at must be RFC3339.');
        }
    }
}
