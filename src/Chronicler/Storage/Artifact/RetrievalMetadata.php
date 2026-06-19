<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Storage\Artifact;

use BlueFission\Arr;
use BlueFission\DataTypes;
use BlueFission\DevElation as Dev;
use BlueFission\Obj;

final class RetrievalMetadata extends Obj
{
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

        $this->method = $method;
        $this->uri = $uri;
        $this->headers($headers);
        if ($expiresAt !== null) {
            $this->expires_at = $expiresAt;
        }
        $this->hints($hints);
    }

    public function headers(?array $headers = null): array
    {
        if ($headers !== null) {
            $this->headers = Arr::toArray(Dev::apply(null, $headers));
        }

        return Arr::toArray($this->headers);
    }

    public function hints(?array $hints = null): array
    {
        if ($hints !== null) {
            $this->hints = Arr::toArray(Dev::apply(null, $hints));
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
