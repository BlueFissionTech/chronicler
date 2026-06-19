<?php

declare(strict_types=1);

namespace BlueFission\Chronicler\Storage\Structures;

use BlueFission\DataTypes;
use BlueFission\Obj;
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
        if ($altitude !== null) {
            $this->altitude = $altitude;
        }
        $this->srid = $srid;
    }

    public function distanceTo(self $point): float
    {
        $earthRadius = 6371000.0;
        $lat1 = deg2rad((float)$this->latitude);
        $lat2 = deg2rad((float)$point->latitude);
        $deltaLat = deg2rad((float)$point->latitude - (float)$this->latitude);
        $deltaLon = deg2rad((float)$point->longitude - (float)$this->longitude);

        $a = sin($deltaLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($deltaLon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    public function toGeoJson(): array
    {
        $coordinates = [(float)$this->longitude, (float)$this->latitude];
        if ($this->altitude !== null) {
            $coordinates[] = (float)$this->altitude;
        }

        return [
            'type' => 'Point',
            'coordinates' => $coordinates,
            'srid' => (int)$this->srid,
        ];
    }

    public function toArray(): array
    {
        return [
            'latitude' => (float)$this->latitude,
            'longitude' => (float)$this->longitude,
            'altitude' => $this->altitude === null ? null : (float)$this->altitude,
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
