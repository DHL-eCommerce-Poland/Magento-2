<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model;

/**
 * Static DHL Parcel services available to the carrier.
 */
class MethodCatalog
{
    /**
     * Return the supported DHL Parcel services.
     *
     * @return array
     */
    public function getMethods(): array
    {
        return [
            ['code' => 'parcel_polska', 'title' => 'DHL Parcel Polska'],
            ['code' => 'premium', 'title' => 'DHL Parcel Premium'],
            ['code' => '9', 'title' => 'DHL Parcel 9'],
            ['code' => '12', 'title' => 'DHL Parcel 12'],
            ['code' => 'return_polska', 'title' => 'DHL Parcel Return Polska'],
            ['code' => 'economy', 'title' => 'DHL Parcel Economy'],
            ['code' => 'max_paczka', 'title' => 'DHL Parcel Max Paczka'],
            ['code' => 'max_polpaleta', 'title' => 'DHL Parcel Max Półpaleta'],
            ['code' => 'max_paleta', 'title' => 'DHL Parcel Max Paleta'],
            ['code' => 'connect', 'title' => 'DHL Parcel Connect'],
            ['code' => 'international', 'title' => 'DHL Parcel International'],
            ['code' => 'connect_plus', 'title' => 'DHL Parcel Connect Plus'],
            ['code' => 'return_connect', 'title' => 'DHL Parcel Return Connect'],
            [
                'code' => 'connect_polpaleta',
                'title' => 'DHL Parcel Connect Pallet - Półpaleta',
            ],
            [
                'code' => 'connect_pallet',
                'title' => 'DHL Parcel Connect Pallet - Paleta',
            ],
        ];
    }
}
