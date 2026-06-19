<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Connections;

use BlueFission\Behavioral\Behaviors\Action;
use BlueFission\Behavioral\Behaviors\Event;
use BlueFission\Behavioral\Behaviors\Meta;
use BlueFission\Behavioral\Behaviors\State;
use BlueFission\Chronicler\Storage\QueryBuilder;
use BlueFission\Connections\Connection;
use BlueFission\DataTypes;
use BlueFission\IObj;

class StorageConnection extends Connection
{
    protected $_data = [
        'profile' => null,
    ];

    protected $_types = [
        'profile' => DataTypes::GENERIC,
    ];

    public function __construct(?ConnectionProfile $profile = null, array $config = [])
    {
        parent::__construct($config);

        $this->profile = $profile ?? new ConnectionProfile();
    }

    public function profile(?ConnectionProfile $profile = null): ConnectionProfile
    {
        if ($profile !== null) {
            $this->profile = $profile;
        }

        return $this->profile instanceof ConnectionProfile ? $this->profile : new ConnectionProfile();
    }

    public function query($query = null): IObj
    {
        $this->perform(State::PERFORMING_ACTION, new Meta(when: Action::PROCESS));
        $this->perform(State::PROCESSING);

        $this->_result = $query instanceof QueryBuilder ? $query->toArray() : $query;

        $this->perform(Event::SUCCESS, new Meta(when: Action::PROCESS, data: $this->_result));
        $this->halt(State::PROCESSING);

        return $this;
    }

    protected function _open(): void
    {
        $this->_connection = $this->profile()->toArray();
        $this->perform(Event::SUCCESS, new Meta(when: Action::CONNECT, data: $this->_connection));
    }

    protected function _close(): void
    {
        $this->_connection = null;
        $this->perform(Event::SUCCESS, new Meta(when: Action::DISCONNECT));
    }
}
