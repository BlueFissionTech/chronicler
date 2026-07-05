<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Connections;

use BlueFission\Arr;
use BlueFission\Chronicler\Support\DevElationValues;
use BlueFission\DataTypes;
use BlueFission\Obj;
use BlueFission\Str;
use BlueFission\Val;

final class ConnectionProfile extends Obj
{
    use DevElationValues;

    protected $_data = [
        'driver' => '',
        'host' => '',
        'port' => null,
        'database' => '',
        'username' => '',
        'options' => [],
    ];

    protected $_types = [
        'driver' => DataTypes::STRING,
        'host' => DataTypes::STRING,
        'port' => DataTypes::INTEGER,
        'database' => DataTypes::STRING,
        'username' => DataTypes::STRING,
        'options' => DataTypes::ARRAY,
    ];

    protected $_lockDataType = true;

    public function __construct(string $driver = '', array $config = [])
    {
        parent::__construct();

        if (Str::isNotEmpty($driver)) {
            $this->driver = Str::trim($driver);
        }
        Arr::make($config)->each(function (mixed $value, mixed $key): void {
            $this->{$key} = $value;
        });
    }

    public function options(?array $options = null): array
    {
        if (Val::isNotNull($options)) {
            $this->options = $this->valueArray($options);
        }

        return Arr::toArray($this->options);
    }
}
