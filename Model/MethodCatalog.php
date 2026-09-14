<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model;

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
            ['code' => 'parcel_9', 'title' => 'DHL Parcel 9'],
            ['code' => 'parcel_12', 'title' => 'DHL Parcel 12'],
            ['code' => 'economy', 'title' => 'DHL Parcel Economy'],
            ['code' => 'max_paleta', 'title' => 'DHL Parcel Max Paleta'],
            ['code' => 'connect', 'title' => 'DHL Parcel Connect'],
            ['code' => 'connect_parcel_shop', 'title' => 'DHL Parcel Connect Parcel Shop'],
            ['code' => 'connect_packstation', 'title' => 'DHL Parcel Connect Packstation'],
            ['code' => 'connect_plus', 'title' => 'DHL Parcel Connect Plus'],
            [
                'code' => 'connect_pallet',
                'title' => 'DHL Parcel Connect Pallet - Paleta',
            ],
        ];
    }
}
