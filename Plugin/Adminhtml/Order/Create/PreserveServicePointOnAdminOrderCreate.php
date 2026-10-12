<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Plugin\Adminhtml\Order\Create;

use Exception;
use DhlPoland\Dhl\Model\Carrier;
use DhlPoland\Dhl\Model\MethodCatalog;
use Magento\Sales\Model\AdminOrder\Create as AdminOrderCreate;
use Magento\Sales\Model\Order;

/**
 * Plugin to handle DHL service point details on quote shipping address during admin order creation and reorder.
 */
class PreserveServicePointOnAdminOrderCreate
{
    private const SERVICE_POINT_KEYS = [
        'dhl_service_point_id',
        'dhl_service_point_name',
        'dhl_service_point_street',
        'dhl_service_point_postcode',
        'dhl_service_point_city',
        'dhl_is_packstation',
        'dhl_is_postfiliale',
        'dhl_postnummer',
    ];

    /**
     * @param MethodCatalog $methodCatalog
     */
    public function __construct(
        private readonly MethodCatalog $methodCatalog
    ) {
    }

    /**
     * Merge top-level order service point POST data into shipping address.
     *
     * Updates existing quote shipping address without wiping standard address fields.
     *
     * @param AdminOrderCreate $subject
     * @param mixed $data
     *
     * @return array
     * @throws Exception
     */
    public function beforeImportPostData(AdminOrderCreate $subject, mixed $data): array
    {
        if (is_array($data)) {
            $hasPostPoint = false;
            $pointData = [];

            foreach (self::SERVICE_POINT_KEYS as $key) {
                if (array_key_exists($key, $data) && $data[$key] !== null) {
                    $pointData[$key] = $data[$key];
                    $hasPostPoint = true;
                }
            }

            if ($hasPostPoint) {
                if (isset($data['shipping_address']) && is_array($data['shipping_address'])) {
                    foreach ($pointData as $key => $val) {
                        $data['shipping_address'][$key] = $val;
                    }
                }

                $quote = $subject->getQuote();
                $quoteAddress = $quote ? $quote->getShippingAddress() : null;
                if ($quoteAddress) {
                    foreach ($pointData as $key => $val) {
                        $quoteAddress->setData($key, $val);
                    }
                    if ($quoteAddress->getId()) {
                        $quoteAddress->save();
                    }
                }
            }
        }

        return [$data];
    }

    /**
     * Copy service point details from order shipping address to quote shipping address after initFromOrder.
     *
     * @param AdminOrderCreate $subject
     * @param AdminOrderCreate $result
     * @param Order $order
     *
     * @return AdminOrderCreate
     * @throws Exception
     */
    public function afterInitFromOrder(
        AdminOrderCreate $subject,
        AdminOrderCreate $result,
        Order $order
    ): AdminOrderCreate {
        $orderShippingAddress = $order->getShippingAddress();
        $quoteShippingAddress = $subject->getQuote()->getShippingAddress();

        if ($orderShippingAddress && $quoteShippingAddress) {
            $hasData = false;
            foreach (self::SERVICE_POINT_KEYS as $key) {
                $value = $orderShippingAddress->getData($key);
                if ($value !== null && $value !== '') {
                    $quoteShippingAddress->setData($key, $value);
                    $hasData = true;
                }
            }

            if ($hasData && $quoteShippingAddress->getId()) {
                $quoteShippingAddress->save();
            }
        }

        return $result;
    }

    /**
     * Preserve or set service point attributes when setShippingAddress replaces quote shipping address.
     *
     * @param AdminOrderCreate $subject
     * @param callable $proceed
     * @param mixed $address
     *
     * @return AdminOrderCreate
     */
    public function aroundSetShippingAddress(
        AdminOrderCreate $subject,
        callable $proceed,
        mixed $address
    ): AdminOrderCreate {
        $oldShippingAddress = $subject->getQuote()->getShippingAddress();

        $existingPointData = [];
        if ($oldShippingAddress) {
            foreach (self::SERVICE_POINT_KEYS as $key) {
                $val = $oldShippingAddress->getData($key);
                if ($val !== null && $val !== '') {
                    $existingPointData[$key] = $val;
                }
            }
        }

        $result = $proceed($address);

        $newShippingAddress = $subject->getQuote()->getShippingAddress();
        if ($newShippingAddress) {
            $shippingMethod = (string) ($newShippingAddress->getShippingMethod()
                ?: $subject->getQuote()->getShippingAddress()->getShippingMethod());
            $isDhlSp = $this->isDhlServicePointMethod($shippingMethod);

            $hasPostedPoint = is_array($address) && !empty($address['dhl_service_point_id']);

            if ($hasPostedPoint && $isDhlSp) {
                foreach (self::SERVICE_POINT_KEYS as $key) {
                    if (array_key_exists($key, $address)) {
                        $newShippingAddress->setData($key, $address[$key]);
                    }
                }
                if ($newShippingAddress->getId()) {
                    $newShippingAddress->save();
                }
            } elseif (!$isDhlSp) {
                $hasExisting = false;
                foreach (self::SERVICE_POINT_KEYS as $key) {
                    if ($newShippingAddress->getData($key) !== null) {
                        $newShippingAddress->setData($key, null);
                        $hasExisting = true;
                    }
                }
                if ($hasExisting && $newShippingAddress->getId()) {
                    $newShippingAddress->save();
                }
            } elseif (!empty($existingPointData)) {
                $newPointId = $newShippingAddress->getData('dhl_service_point_id');
                if (empty($newPointId)) {
                    foreach ($existingPointData as $key => $val) {
                        $newShippingAddress->setData($key, $val);
                    }
                    if ($newShippingAddress->getId()) {
                        $newShippingAddress->save();
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Ensure quote shipping address service point data is saved before order creation.
     *
     * @param AdminOrderCreate $subject
     *
     * @return void
     */
    public function beforeCreateOrder(AdminOrderCreate $subject): void
    {
        $quoteShippingAddress = $subject->getQuote()->getShippingAddress();
        if ($quoteShippingAddress) {
            $shippingMethod = (string) $quoteShippingAddress->getShippingMethod();
            if (!$this->isDhlServicePointMethod($shippingMethod)) {
                foreach (self::SERVICE_POINT_KEYS as $key) {
                    $quoteShippingAddress->setData($key, null);
                }
            }
            if ($quoteShippingAddress->getId()) {
                $quoteShippingAddress->save();
            }
        }
    }

    /**
     * Check if a shipping method code represents a DHL Service Point method.
     *
     * @param string|null $shippingMethod
     *
     * @return bool
     */
    private function isDhlServicePointMethod(?string $shippingMethod): bool
    {
        if ($shippingMethod === null || trim($shippingMethod) === '') {
            return false;
        }

        $code = strtolower(trim($shippingMethod));
        if (str_starts_with($code, Carrier::CODE_PREFIX)) {
            $code = substr($code, strlen(Carrier::CODE_PREFIX));
        }

        return $this->methodCatalog->isServicePointMethod($code);
    }
}
