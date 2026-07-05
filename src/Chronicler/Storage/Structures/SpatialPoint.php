<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Storage\Structures;

use BlueFission\Arr;
use BlueFission\DataTypes;
use BlueFission\Num;
use BlueFission\Obj;
use BlueFission\Val;
use InvalidArgumentException;

final class SpatialPoint extends Obj
{
    protected $_data = [
        'latitude' => 0.0,
        'longitude' => 0.0,
        'altitude' => null,
        'srid' => 4326,
    ];

    protected $_types = [
        'latitude' => DataTypes::FLOAT,
        'longitude' => DataTypes::FLOAT,
        'altitude' => DataTypes::FLOAT,
        'srid' => DataTypes::INTEGER,
    ];

    protected $_lockDataType = true;

    public function __construct(float $latitude, float $longitude, ?float $altitude = null, int $srid = 4326)
    {
        parent::__construct();

        $this->assertRange($latitude, $longitude);
        $this->latitude = $latitude;
        $this->longitude = $longitude;
        if (Val::isNotNull($altitude)) {
            $this->altitude = $altitude;
        }
        $this->srid = $srid;
    }

    public function distanceTo(self $point): float
    {
        $earthRadius = 6371000.0;
        $lat1 = Num::deg2rad((float)$this->latitude);
        $lat2 = Num::deg2rad((float)$point->latitude);
        $deltaLat = Num::deg2rad((float)$point->latitude - (float)$this->latitude);
        $deltaLon = Num::deg2rad((float)$point->longitude - (float)$this->longitude);

        $deltaLatSin = Num::make($deltaLat)->divide(2)->sin()->val();
        $deltaLonSin = Num::make($deltaLon)->divide(2)->sin()->val();
        $a = Num::make($deltaLatSin)->pow(2)->val()
            + Num::cos($lat1) * Num::cos($lat2) * Num::make($deltaLonSin)->pow(2)->val();
        $c = Num::make(Num::sqrt($a))->atan2(Num::sqrt(1 - $a))->multiply(2)->val();

        return $earthRadius * $c;
    }

    public function toGeoJson(): array
    {
        $coordinates = Arr::make([(float)$this->longitude, (float)$this->latitude]);
        if (Val::isNotNull($this->altitude)) {
            $coordinates->push((float)$this->altitude);
        }

        return [
            'type' => 'Point',
            'coordinates' => $coordinates->val(),
            'srid' => (int)$this->srid,
        ];
    }

    public function toArray(): array
    {
        return [
            'latitude' => (float)$this->latitude,
            'longitude' => (float)$this->longitude,
            'altitude' => Val::isNull($this->altitude) ? null : (float)$this->altitude,
            'srid' => (int)$this->srid,
        ];
    }

    private function assertRange(float $latitude, float $longitude): void
    {
        if ($latitude < -90 || $latitude > 90) {
            throw new InvalidArgumentException('Latitude must be between -90 and 90.');
        }
        if ($longitude < -180 || $longitude > 180) {
            throw new InvalidArgumentException('Longitude must be between -180 and 180.');
        }
    }
}
