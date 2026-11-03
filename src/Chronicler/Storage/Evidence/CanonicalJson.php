<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Storage\Evidence;

use InvalidArgumentException;
use JsonException;

final class CanonicalJson
{
    public const ID = 'chronicler-evidence-v1';

    private const FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION;

    /**
     * Detach PHP references without coercing accepted scalar types or key order.
     * Native copying/JSON validation is an intentional immutable wire boundary:
     * mutable objects, serializers, and application value filters cannot run here.
     *
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    public static function snapshot(array $value): array
    {
        $snapshot = self::copyArray($value, 0);
        // Validate UTF-8 and JSON depth as well as the native types checked below.
        json_encode($snapshot, self::FLAGS);

        return $snapshot;
    }

    /**
     * @throws JsonException
     */
    public static function encode(array $value): string
    {
        return json_encode(
            self::normalize(self::snapshot($value)),
            self::FLAGS
        );
    }

    /**
     * @throws JsonException
     */
    public static function digest(array $record): string
    {
        unset($record['digest']);

        return 'sha256:' . hash('sha256', self::encode($record));
    }

    private static function copyArray(array $value, int $depth): array
    {
        // Bound recursion before traversing cyclic or excessively nested arrays.
        if ($depth >= 512) {
            throw new InvalidArgumentException('Evidence JSON nesting exceeds the supported depth.');
        }

        $snapshot = [];
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $snapshot[$key] = self::copyArray($item, $depth + 1);
                continue;
            }
            if ($item !== null && !is_bool($item) && !is_int($item)
                && !is_float($item) && !is_string($item)
            ) {
                throw new InvalidArgumentException('Evidence accepts native JSON values, not objects or resources.');
            }
            if (is_float($item) && !is_finite($item)) {
                throw new InvalidArgumentException('Evidence JSON numbers must be finite.');
            }
            // A fresh assignment, without &, detaches scalar reference containers.
            $snapshot[$key] = $item;
        }

        return $snapshot;
    }

    private static function normalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (!array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        foreach ($value as $key => $item) {
            $value[$key] = self::normalize($item);
        }

        return $value;
    }
}
