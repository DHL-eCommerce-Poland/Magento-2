<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Api;

use SoapClient;
use SoapFault;

class TestSoapClient extends SoapClient
{
    /**
     * @param bool $shouldThrowFault
     */
    public function __construct(
        private readonly bool $shouldThrowFault = false
    ) {
        parent::__construct(null, [
            'location' => 'http://localhost',
            'uri' => 'http://localhost',
            'exceptions' => true,
        ]);
    }

    /**
     * Dummy getPostalCodeServices method for SoapClient mocking.
     *
     * @param mixed $args
     * @return mixed
     * @throws SoapFault
     */
    public function getPostalCodeServices(mixed $args): mixed
    {
        if ($this->shouldThrowFault) {
            throw new SoapFault('AUTH_ERR', 'Invalid credentials');
        }

        return (object) [
            'getPostalCodeServicesResult' => (object) [
                'drPickupFrom' => '10:00',
                'drPickupTo' => '16:00',
                'exPickupFrom' => '10:00',
                'exPickupTo' => '15:00',
                'pickupOnSaturday' => true,
            ],
        ];
    }

    /**
     * Dummy bookCourier method for SoapClient mocking.
     *
     * @param mixed $args
     * @return mixed
     * @throws SoapFault
     */
    public function bookCourier(mixed $args): mixed
    {
        if ($this->shouldThrowFault) {
            throw new SoapFault('BOOK_ERR', 'Error booking courier');
        }

        return (object) [
            'bookCourierResult' => (object) [
                'id' => 'ORDER_98765',
            ],
        ];
    }

    /**
     * Dummy cancelCourierBooking method for SoapClient mocking.
     *
     * @param mixed $args
     * @return mixed
     * @throws SoapFault
     */
    public function cancelCourierBooking(mixed $args): mixed
    {
        if ($this->shouldThrowFault) {
            throw new SoapFault('CANCEL_ERR', 'Error cancelling courier booking');
        }

        return (object) [
            'cancelCourierBookingResult' => (object) [
                'item' => [
                    (object) [
                        'id' => 'ORDER_98765',
                        'result' => true,
                    ],
                ],
            ],
        ];
    }

    /**
     * Dummy createShipments method for SoapClient mocking.
     *
     * @param mixed $args
     * @return mixed
     * @throws SoapFault
     */
    public function createShipments(mixed $args): mixed
    {
        if ($this->shouldThrowFault) {
            throw new SoapFault('CREATE_ERR', 'Error creating shipment');
        }

        return (object) [
            'createShipmentsResult' => (object) [
                'item' => [
                    (object) [
                        'shipmentId' => '1234567890',
                        'orderStatus' => 'Nowa',
                        'pieceList' => (object) [
                            'item' => [
                                (object) ['blpPieceId' => 'BLP1001'],
                                (object) ['blpPieceId' => 'BLP1002'],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Dummy deleteShipments method for SoapClient mocking.
     *
     * @param mixed $args
     * @return mixed
     * @throws SoapFault
     */
    public function deleteShipments(mixed $args): mixed
    {
        if ($this->shouldThrowFault) {
            throw new SoapFault('DELETE_ERR', 'Error deleting shipment');
        }

        return (object) [
            'deleteShipmentsResult' => (object) [
                'item' => [
                    (object) [
                        'shipmentIdentification' => '1234567890',
                        'result' => true,
                    ],
                ],
            ],
        ];
    }

    /**
     * Dummy getTrackAndTraceInfo method for SoapClient mocking.
     *
     * @param mixed $args
     * @return mixed
     * @throws SoapFault
     */
    public function getTrackAndTraceInfo(mixed $args): mixed
    {
        if ($this->shouldThrowFault) {
            throw new SoapFault('TRACK_ERR', 'Error fetching tracking info');
        }

        return (object) [
            'getTrackAndTraceInfoResult' => (object) [
                'shipmentId' => '1234567890',
                'receivedBy' => 'Jan Kowalski',
                'events' => (object) [
                    'item' => [
                        (object) [
                            'status' => 'DOR',
                            'description' => 'Doręczono',
                            'terminal' => 'Warszawa',
                            'timestamp' => '2026-08-28 14:00:00',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Dummy getShipmentScan method for SoapClient mocking.
     *
     * @param mixed $args
     * @return mixed
     * @throws SoapFault
     */
    public function getShipmentScan(mixed $args): mixed
    {
        if ($this->shouldThrowFault) {
            throw new SoapFault('SCAN_ERR', 'Error fetching shipment scan');
        }

        return (object) [
            'getShipmentScanResult' => (object) [
                'scanData' => base64_encode('dummy_scan_pdf_content'),
                'scanMimeType' => 'application/pdf',
            ],
        ];
    }

    /**
     * Dummy getEpod method for SoapClient mocking.
     *
     * @param mixed $args
     * @return mixed
     * @throws SoapFault
     */
    public function getEpod(mixed $args): mixed
    {
        if ($this->shouldThrowFault) {
            throw new SoapFault('EPOD_ERR', 'Error fetching ePOD');
        }

        return (object) [
            'getEpodResult' => (object) [
                'scanData' => base64_encode('dummy_epod_pdf_content'),
                'scanMimeType' => 'application/pdf',
            ],
        ];
    }

    /**
     * Dummy getPnp method for SoapClient mocking.
     *
     * @param mixed $args
     * @return mixed
     * @throws SoapFault
     */
    public function getPnp(mixed $args): mixed
    {
        if ($this->shouldThrowFault) {
            throw new SoapFault('PNP_ERR', 'Error fetching PNP report');
        }

        return (object) [
            'getPnpResult' => (object) [
                'fileName' => 'PNP_2026-09-01.pdf',
                'fileData' => base64_encode('dummy_pnp_pdf_content'),
                'fileMimeType' => 'application/pdf',
            ],
        ];
    }

    /**
     * Dummy createShipmentReturn method for SoapClient mocking.
     *
     * @param mixed $args
     *
     * @return mixed
     * @throws SoapFault
     */
    public function createShipmentReturn(mixed $args): mixed
    {
        if ($this->shouldThrowFault) {
            throw new SoapFault('RETURN_ERR', 'Error creating return shipment');
        }

        return (object) [
            'createShipmentReturnResult' => (object) [
                'shipmentNotificationNumber' => 'RET987654321',
                'waybill' => 'RET987654321',
                'label' => (object) [
                    'labelContent' => base64_encode('%PDF-1.4 Return Label Data'),
                    'labelMimeType' => 'application/pdf',
                ],
            ],
        ];
    }

    /**
     * Dummy __getLastRequest method.
     *
     * @return string
     */
    public function __getLastRequest(): string
    {
        return '<xml><password>secret</password></xml>';
    }

    /**
     * Dummy __getLastResponse method.
     *
     * @return string
     */
    public function __getLastResponse(): string
    {
        return '<xml>response</xml>';
    }
}
