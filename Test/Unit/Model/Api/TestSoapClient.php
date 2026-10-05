<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Api;

use SoapClient;
use SoapFault;

class TestSoapClient extends SoapClient
{
    public int $getLabelsCallCount = 0;
    public array $getLabelsCalls = [];

    /**
     * @param bool $shouldThrowFault
     * @param string $faultMessage
     * @throws SoapFault
     */
    public function __construct(
        private readonly bool $shouldThrowFault = false,
        private readonly string $faultMessage = 'Invalid credentials'
    ) {
        parent::__construct(null, [
            'location' => 'http://localhost',
            'uri' => 'http://localhost',
            'exceptions' => true,
        ]);
    }


    /**
     * Dummy getInternationalParams2 method for SoapClient mocking.
     *
     * @param mixed $args
     * @return mixed
     * @throws SoapFault
     */
    public function getInternationalParams2(mixed $args): mixed
    {
        if ($this->shouldThrowFault) {
            throw new SoapFault('AUTH_ERR', $this->faultMessage);
        }

        return (object) [
            'getInternationalParams2Result' => (object) [
                'params' => (object) [
                    'item' => [],
                ],
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
     * Dummy createShipment method for SoapClient mocking.
     *
     * @param mixed $args
     * @return mixed
     * @throws SoapFault
     */
    public function createShipment(mixed $args): mixed
    {
        if ($this->shouldThrowFault) {
            throw new SoapFault('CREATE_ERR', 'Error creating shipment');
        }

        return (object) [
            'createShipmentResult' => (object) [
                'shipmentNotificationNumber' => '1234567890',
                'shipmentId' => '1234567890',
                'orderStatus' => 'Nowa',
                'pieceList' => (object) [
                    'item' => [
                        (object) ['blpPieceId' => 'BLP1001'],
                        (object) ['blpPieceId' => 'BLP1002'],
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
     * Dummy getNearestServicepoints method for SoapClient mocking.
     *
     * @param mixed $args
     * @return mixed
     * @throws SoapFault
     */
    public function getNearestServicepoints(mixed $args): mixed
    {
        if ($this->shouldThrowFault) {
            throw new SoapFault('POINTS_ERR', 'Error fetching nearest service points');
        }

        return (object) [
            'getNearestServicepointsResult' => (object) [
                'points' => (object) [
                    'item' => [
                        (object) [
                            'sap' => 'POP-WAW-100',
                            'name' => 'Sklep Żabka 100',
                            'type' => 'POP',
                            'description' => 'Opis punktu 100',
                            'longitude' => '21.01',
                            'latitude' => '52.23',
                            'address' => (object) [
                                'street' => 'Marszałkowska',
                                'houseNumber' => '10',
                                'postcode' => '00-001',
                                'city' => 'Warszawa',
                            ],
                        ],
                        (object) [
                            'sap' => 'POP-WAW-101',
                            'name' => 'Sklep Żabka 101',
                            'type' => 'POP',
                            'description' => 'Opis punktu 101',
                            'longitude' => '21.02',
                            'latitude' => '52.24',
                            'address' => (object) [
                                'street' => 'Aleje Jerozolimskie',
                                'houseNumber' => '20',
                                'postcode' => '00-002',
                                'city' => 'Warszawa',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Dummy getLabels method for SoapClient mocking.
     *
     * @param mixed $args
     * @return mixed
     * @throws SoapFault
     */
    public function getLabels(mixed $args): mixed
    {
        $this->getLabelsCallCount++;
        $this->getLabelsCalls[] = $args;

        if ($this->shouldThrowFault) {
            throw new SoapFault('GET_LABELS_ERR', $this->faultMessage);
        }

        $items = [];
        $itemsToPrint = $args['itemsToPrint'] ?? [];
        if (!is_array($itemsToPrint)) {
            $itemsToPrint = [$itemsToPrint];
        }

        foreach ($itemsToPrint as $item) {
            $shipmentId = is_array($item) ? ($item['shipmentId'] ?? '1234567890') : ($item->shipmentId ?? '1234567890');
            $labelType = is_array($item) ? ($item['labelType'] ?? 'BLP') : ($item->labelType ?? 'BLP');
            $items[] = (object) [
                'shipmentId' => (string) $shipmentId,
                'labelType' => (string) $labelType,
                'labelData' => base64_encode('%PDF-1.4 dummy label for ' . $shipmentId),
                'labelMimeType' => 'application/pdf',
            ];
        }

        return (object) [
            'getLabelsResult' => (object) [
                'item' => $items,
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
