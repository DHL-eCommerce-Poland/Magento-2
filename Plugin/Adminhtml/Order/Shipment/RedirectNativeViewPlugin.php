<?php

declare(strict_types=1);

namespace Ideo\Dhl\Plugin\Adminhtml\Order\Shipment;

use Ideo\Dhl\Api\ShipmentDataRepositoryInterface;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Sales\Controller\Adminhtml\Shipment\View as NativeShipmentViewController;
use Psr\Log\LoggerInterface;
use Throwable;

class RedirectNativeViewPlugin
{
    /**
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param UrlInterface $urlBuilder
     * @param ShipmentDataRepositoryInterface $shipmentDataRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly ResponseInterface $response,
        private readonly UrlInterface $urlBuilder,
        private readonly ShipmentDataRepositoryInterface $shipmentDataRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Redirect to DHL shipment view if native shipment has linked DHL shipment entity.
     *
     * @param NativeShipmentViewController $subject
     * @param callable $proceed
     *
     * @return mixed
     */
    public function aroundExecute(NativeShipmentViewController $subject, callable $proceed): mixed
    {
        $magentoShipmentId = (int)$this->request->getParam('shipment_id');
        if ($magentoShipmentId > 0) {
            try {
                $dhlShipment = $this->shipmentDataRepository->getByMagentoShipmentId($magentoShipmentId);
                if ($dhlShipment && $dhlShipment->getEntityId()) {
                    $dhlEditUrl = $this->urlBuilder->getUrl(
                        'dhlpoland/shipment/edit',
                        ['entity_id' => $dhlShipment->getEntityId()]
                    );
                    $this->response->setRedirect($dhlEditUrl);
                    return null;
                }
            } catch (Throwable $exception) {
                $this->logger->error(
                    sprintf(
                        'Error redirecting native shipment view controller for shipment #%d: %s',
                        $magentoShipmentId,
                        $exception->getMessage()
                    ),
                    ['exception' => $exception]
                );
            }
        }

        return $proceed();
    }
}
