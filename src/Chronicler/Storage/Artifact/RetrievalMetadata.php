<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Storage\Artifact;

use BlueFission\Arr;
use BlueFission\Chronicler\Support\DevElationValues;
use BlueFission\DataTypes;
use BlueFission\Obj;
use BlueFission\Str;
use BlueFission\Val;

final class RetrievalMetadata extends Obj
{
    use DevElationValues;

    protected $_data = [
        'method' => '',
        'uri' => '',
        'headers' => [],
        'expires_at' => null,
        'hints' => [],
    ];

    protected $_types = [
        'method' => DataTypes::STRING,
        'uri' => DataTypes::STRING,
        'headers' => DataTypes::ARRAY,
        'expires_at' => DataTypes::STRING,
        'hints' => DataTypes::ARRAY,
    ];

    protected $_lockDataType = true;

    public function __construct(string $method = '', string $uri = '', array $headers = [], ?string $expiresAt = null, array $hints = [])
    {
        parent::__construct();

        $this->method = Str::trim($method);
        $this->uri = Str::trim($uri);
        $this->headers($headers);
        if (Val::isNotNull($expiresAt)) {
            $this->expires_at = Str::trim($expiresAt);
        }
        $this->hints($hints);
    }

    public function headers(?array $headers = null): array
    {
        if (Val::isNotNull($headers)) {
            $this->headers = $this->valueArray($headers);
        }

        return Arr::toArray($this->headers);
    }

    public function hints(?array $hints = null): array
    {
        if (Val::isNotNull($hints)) {
            $this->hints = $this->valueArray($hints);
        }

        return Arr::toArray($this->hints);
    }

    public function toArray(): array
    {
        return [
            'method' => $this->method,
            'uri' => $this->uri,
            'headers' => $this->headers(),
            'expires_at' => $this->expires_at,
            'hints' => $this->hints(),
        ];
    }
}
