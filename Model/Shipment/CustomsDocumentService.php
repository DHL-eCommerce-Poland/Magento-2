<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Model\Shipment;

use DhlPoland\Dhl\Model\Api\CreateShipmentResult;
use Magento\Framework\App\ResourceConnection;

class CustomsDocumentService
{
    public const TYPE_CN23 = 'cn23';
    public const TYPE_PROFORMA = 'fv_proforma';
    public const TABLE_NAME = 'dhlpoland_shipment_customs_document';

    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * Persist customs documents (CN23, Proforma) returned by createShipment API response.
     *
     * @param int $shipmentEntityId Database entity ID of the shipment
     * @param CreateShipmentResult $result API creation result containing potential customs data
     *
     * @return void
     */
    public function saveDocuments(int $shipmentEntityId, CreateShipmentResult $result): void
    {
        if (!$result->hasCustomsDocuments() || $shipmentEntityId <= 0) {
            return;
        }

        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName(self::TABLE_NAME);

        // Delete any existing documents for this entity to prevent duplicates on recreation
        $connection->delete($tableName, ['shipment_entity_id = ?' => $shipmentEntityId]);

        $rowsToInsert = [];

        $cn23Base64 = $result->getCn23Content();
        if (!empty($cn23Base64)) {
            // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
            $cn23Binary = base64_decode($cn23Base64);
            $rowsToInsert[] = [
                'shipment_entity_id' => $shipmentEntityId,
                'document_type' => self::TYPE_CN23,
                'document_number' => null,
                'mime_type' => $result->getCn23MimeType() ?: 'application/pdf',
                'content' => $cn23Binary,
            ];
        }

        $proformaBase64 = $result->getFvProformaContent();
        if (!empty($proformaBase64)) {
            // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
            $proformaBinary = base64_decode($proformaBase64);
            $rowsToInsert[] = [
                'shipment_entity_id' => $shipmentEntityId,
                'document_type' => self::TYPE_PROFORMA,
                'document_number' => $result->getFvProformaNumber(),
                'mime_type' => $result->getFvProformaMimeType() ?: 'application/pdf',
                'content' => $proformaBinary,
            ];
        }

        if (!empty($rowsToInsert)) {
            $connection->insertMultiple($tableName, $rowsToInsert);
        }
    }

    /**
     * Retrieve customs documents for a single shipment entity.
     *
     * @param int $shipmentEntityId
     *
     * @return array
     */
    public function getDocumentsForShipment(int $shipmentEntityId): array
    {
        $documents = $this->getDocumentsForShipments([$shipmentEntityId]);
        return $documents[$shipmentEntityId] ?? [];
    }

    /**
     * Retrieve customs documents for multiple shipment entity IDs.
     *
     * @param int[] $shipmentEntityIds
     *
     * @return array
     */
    public function getDocumentsForShipments(array $shipmentEntityIds): array
    {
        $entityIds = array_values(array_unique(array_filter(array_map('intval', $shipmentEntityIds))));
        if (empty($entityIds)) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName(self::TABLE_NAME);

        $select = $connection->select()
            ->from($tableName)
            ->where('shipment_entity_id IN (?)', $entityIds)
            ->order('document_id ASC');

        $rows = $connection->fetchAll($select);
        $grouped = [];

        foreach ($rows as $row) {
            $entityId = (int) $row['shipment_entity_id'];
            if (!isset($grouped[$entityId])) {
                $grouped[$entityId] = [];
            }
            $grouped[$entityId][] = [
                'document_id' => (int) ($row['document_id'] ?? 0),
                'document_type' => (string) ($row['document_type'] ?? ''),
                'document_number' => isset($row['document_number']) ? (string) $row['document_number'] : null,
                'mime_type' => (string) ($row['mime_type'] ?? 'application/pdf'),
                'content' => $row['content'] ?? '',
            ];
        }

        return $grouped;
    }
}
