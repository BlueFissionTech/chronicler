<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Connections;

use BlueFission\Arr;
use BlueFission\DataTypes;
use BlueFission\Obj;

final class ConnectionProfile extends Obj
{
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

        if ($driver !== '') {
            $this->driver = $driver;
        }
        foreach ($config as $key => $value) {
            $this->{$key} = $value;
        }
    }

    public function options(?array $options = null): array
    {
        if ($options !== null) {
            $this->options = Arr::toArray($options);
        }

        return Arr::toArray($this->options);
    }
}
