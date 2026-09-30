<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Storage\Evidence;

use JsonException;

final class CanonicalJson
{
    public const ID = 'chronicler-evidence-v1';

    /**
     * @throws JsonException
     */
    public static function encode(array $value): string
    {
        return json_encode(
            self::normalize($value),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
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
