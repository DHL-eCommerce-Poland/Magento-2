<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Config\Backend;

use Ideo\Dhl\Model\Config\Backend\CountryRate;
use Ideo\Dhl\Model\MethodCatalog;
use Magento\Config\Model\Config\Backend\File\RequestData\RequestDataInterface;
use Magento\Directory\Model\ResourceModel\Country\Collection as CountryCollection;
use Magento\Directory\Model\ResourceModel\Country\CollectionFactory as CountryCollectionFactory;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Io\File as FileIo;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\MediaStorage\Model\File\UploaderFactory;
use PHPUnit\Framework\TestCase;

class CountryRateTest extends TestCase
{
    /**
     * @var CountryCollectionFactory
     */
    private $countryCollectionFactoryStub;

    /**
     * @var ResourceConnection
     */
    private $resourceConnectionStub;

    /**
     * @var WriterInterface
     */
    private $configWriterStub;

    /**
     * @var TypeListInterface
     */
    private $cacheTypeListStub;

    /**
     * @var FileIo
     */
    private $fileIoStub;

    /**
     * @var CountryRate
     */
    private $backendModel;

    protected function setUp(): void
    {
        $countryCollectionStub = $this->createStub(CountryCollection::class);
        $countryCollectionStub->method('getAllIds')
            ->willReturn(['DE', 'CZ', 'SK', 'AT', 'FR', 'PL', 'NL', 'BE', 'GB', 'ES', 'IT']);

        $this->countryCollectionFactoryStub = $this->createStub(CountryCollectionFactory::class);
        $this->countryCollectionFactoryStub->method('create')
            ->willReturn($countryCollectionStub);

        $connectionStub = $this->createStub(AdapterInterface::class);
        $this->resourceConnectionStub = $this->createStub(ResourceConnection::class);
        $this->resourceConnectionStub->method('getConnection')->willReturn($connectionStub);
        $this->resourceConnectionStub->method('getTableName')->willReturn('dhlpoland_country_rate');

        $this->configWriterStub = $this->createStub(WriterInterface::class);
        $this->cacheTypeListStub = $this->createStub(TypeListInterface::class);
        $this->fileIoStub = $this->createStub(FileIo::class);

        $this->backendModel = $this->createBackendModel();
    }

    private function createBackendModel(
        ?ResourceConnection $resourceConnection = null,
        ?WriterInterface $configWriter = null,
        ?TypeListInterface $cacheTypeList = null
    ): CountryRate {
        $context = $this->createStub(Context::class);
        $eventDispatcher = $this->createStub(\Magento\Framework\Event\ManagerInterface::class);
        $context->method('getEventDispatcher')->willReturn($eventDispatcher);
        $registry = $this->createStub(Registry::class);
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $uploaderFactory = $this->createStub(UploaderFactory::class);
        $requestData = $this->createStub(RequestDataInterface::class);
        $filesystem = $this->createStub(Filesystem::class);
        $methodCatalog = new MethodCatalog();

        return new CountryRate(
            $context,
            $registry,
            $scopeConfig,
            $cacheTypeList ?? $this->cacheTypeListStub,
            $uploaderFactory,
            $requestData,
            $filesystem,
            $resourceConnection ?? $this->resourceConnectionStub,
            $methodCatalog,
            $this->countryCollectionFactoryStub,
            $configWriter ?? $this->configWriterStub,
            $this->fileIoStub
        );
    }

    public function testParseCsvThrowsExceptionWhenFileIsEmpty(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('CSV file is empty.');

        $this->backendModel->parseCsvContent("   \n\n");
    }

    public function testParseCsvThrowsExceptionWhenHeaderStructureIsInvalid(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Invalid CSV header structure.');

        $this->backendModel->parseCsvContent("Something,Else\nDE,35.00");
    }

    public function testParseCsvParsesStandardEnglishHeaderWithCommas(): void
    {
        $csv = "Method Code,Country,Price\n" .
            "connect,DE,35.00\n" .
            "connect,CZ,25.00\n" .
            "connect_pallet,DE,614.00";

        $rows = $this->backendModel->parseCsvContent($csv);

        $this->assertCount(3, $rows);
        $this->assertSame([
            'method_code' => 'connect',
            'country_id' => 'DE',
            'price' => 35.0,
        ], $rows[0]);
        $this->assertSame([
            'method_code' => 'connect',
            'country_id' => 'CZ',
            'price' => 25.0,
        ], $rows[1]);
        $this->assertSame([
            'method_code' => 'connect_pallet',
            'country_id' => 'DE',
            'price' => 614.0,
        ], $rows[2]);
    }

    public function testParseCsvStripsUtf8BomAtBeginning(): void
    {
        $csv = "\xEF\xBB\xBFMethod Code,Country,Price\nconnect,DE,35.00";

        $rows = $this->backendModel->parseCsvContent($csv);

        $this->assertCount(1, $rows);
        $this->assertSame('DE', $rows[0]['country_id']);
        $this->assertSame(35.0, $rows[0]['price']);
    }

    public function testParseCsvDetectsSemicolonDelimiter(): void
    {
        $csv = "Method Code;Country;Price\nconnect;FR;45.00";

        $rows = $this->backendModel->parseCsvContent($csv);

        $this->assertCount(1, $rows);
        $this->assertSame('FR', $rows[0]['country_id']);
        $this->assertSame(45.0, $rows[0]['price']);
    }

    public function testParseCsvDefaultsMethodToConnectWhenMethodColumnOmitted(): void
    {
        $csv = "Country,Price\nDE,35.00\nSK,27.00";

        $rows = $this->backendModel->parseCsvContent($csv);

        $this->assertCount(2, $rows);
        $this->assertSame('connect', $rows[0]['method_code']);
        $this->assertSame('DE', $rows[0]['country_id']);
        $this->assertSame(35.0, $rows[0]['price']);
        $this->assertSame('connect', $rows[1]['method_code']);
        $this->assertSame('SK', $rows[1]['country_id']);
    }

    public function testParseCsvThrowsExceptionForInvalidCountryCode(): void
    {
        $csv = "Method Code,Country,Price\nconnect,UK,35.00";

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Invalid country code "UK" on line 2.');

        $this->backendModel->parseCsvContent($csv);
    }

    public function testParseCsvThrowsExceptionForUnsupportedMethodCode(): void
    {
        $csv = "Method Code,Country,Price\nunknown_service,DE,35.00";

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unsupported method code "unknown_service" on line 2.');

        $this->backendModel->parseCsvContent($csv);
    }

    public function testParseCsvThrowsExceptionForInvalidPrice(): void
    {
        $csv = "Method Code,Country,Price\nconnect,DE,-15.00";

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Invalid price value "-15.00" on line 2.');

        $this->backendModel->parseCsvContent($csv);
    }

    public function testSaveCountryRatesDeletesExistingAndInsertsMultiple(): void
    {
        $rows = [
            ['method_code' => 'connect', 'country_id' => 'DE', 'price' => 35.0],
            ['method_code' => 'connect', 'country_id' => 'CZ', 'price' => 25.0],
            ['method_code' => 'connect_pallet', 'country_id' => 'DE', 'price' => 614.0],
        ];

        $connectionMock = $this->createMock(AdapterInterface::class);
        $resourceConnectionStub = $this->createStub(ResourceConnection::class);
        $resourceConnectionStub->method('getConnection')->willReturn($connectionMock);
        $resourceConnectionStub->method('getTableName')->willReturn('dhlpoland_country_rate');

        $connectionMock->expects($this->once())->method('beginTransaction');
        $connectionMock->expects($this->once())->method('commit');

        $connectionMock->expects($this->once())
            ->method('delete')
            ->with(
                'dhlpoland_country_rate',
                [
                    'website_id = ?' => 0,
                    'method_code IN (?)' => ['connect', 'connect_pallet'],
                ]
            );

        $connectionMock->expects($this->once())
            ->method('insertMultiple')
            ->with(
                'dhlpoland_country_rate',
                [
                    ['website_id' => 0, 'method_code' => 'connect', 'country_id' => 'DE', 'price' => 35.0],
                    ['website_id' => 0, 'method_code' => 'connect', 'country_id' => 'CZ', 'price' => 25.0],
                    ['website_id' => 0, 'method_code' => 'connect_pallet', 'country_id' => 'DE', 'price' => 614.0],
                ]
            );

        $backendModel = $this->createBackendModel(resourceConnection: $resourceConnectionStub);
        $backendModel->saveCountryRates(0, $rows);
    }

    public function testSyncConfigSpecificCountriesSavesConfigPerMethod(): void
    {
        $rows = [
            ['method_code' => 'connect', 'country_id' => 'DE', 'price' => 35.0],
            ['method_code' => 'connect', 'country_id' => 'CZ', 'price' => 25.0],
            ['method_code' => 'connect_pallet', 'country_id' => 'FR', 'price' => 614.0],
        ];

        $configWriterMock = $this->createMock(WriterInterface::class);
        $configWriterMock->expects($this->exactly(4))
            ->method('save');

        $backendModel = $this->createBackendModel(configWriter: $configWriterMock);
        $backendModel->syncConfigSpecificCountries($rows);
    }

    public function testClearConfigSpecificCountriesDeletesUniqueMethodPathsFromDefaultScope(): void
    {
        $deletedConfigs = [];
        $configWriterMock = $this->createMock(WriterInterface::class);
        $configWriterMock->expects($this->exactly(4))
            ->method('delete')
            ->willReturnCallback(
                static function (string $path, string $scope, int $scopeId) use (&$deletedConfigs): void {
                    $deletedConfigs[] = [$path, $scope, $scopeId];
                }
            );

        $cacheTypeListMock = $this->createMock(TypeListInterface::class);
        $cacheTypeListMock->expects($this->once())
            ->method('cleanType')
            ->with('config');

        $backendModel = $this->createBackendModel(
            configWriter: $configWriterMock,
            cacheTypeList: $cacheTypeListMock
        );
        $backendModel->clearConfigSpecificCountries(['connect', 'connect', 'connect_pallet']);

        $this->assertSame(
            [
                ['carriers/dhlparcel/method_connect_sallowspecific', ScopeConfigInterface::SCOPE_TYPE_DEFAULT, 0],
                ['carriers/dhlparcel/method_connect_specificcountry', ScopeConfigInterface::SCOPE_TYPE_DEFAULT, 0],
                [
                    'carriers/dhlparcel/method_connect_pallet_sallowspecific',
                    ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                    0,
                ],
                [
                    'carriers/dhlparcel/method_connect_pallet_specificcountry',
                    ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                    0,
                ],
            ],
            $deletedConfigs
        );
    }

    public function testClearConfigSpecificCountriesDoesNothingWhenNoMethodsAreProvided(): void
    {
        $configWriterMock = $this->createMock(WriterInterface::class);
        $configWriterMock->expects($this->never())->method('delete');

        $cacheTypeListMock = $this->createMock(TypeListInterface::class);
        $cacheTypeListMock->expects($this->never())->method('cleanType');

        $backendModel = $this->createBackendModel(
            configWriter: $configWriterMock,
            cacheTypeList: $cacheTypeListMock
        );
        $backendModel->clearConfigSpecificCountries([]);
    }

    public function testAfterSaveCleansConfigCache(): void
    {
        $cacheTypeListMock = $this->createMock(TypeListInterface::class);
        $cacheTypeListMock->expects($this->once())
            ->method('cleanType')
            ->with('config');

        $backendModel = $this->createBackendModel(cacheTypeList: $cacheTypeListMock);
        $backendModel->afterSave();
    }

    public function testAfterSaveSyncsImportedRowsIfPresent(): void
    {
        $cacheTypeListStub = $this->createStub(TypeListInterface::class);
        $configWriterMock = $this->createMock(WriterInterface::class);
        $configWriterMock->expects($this->exactly(2))
            ->method('save');

        $backendModel = $this->createBackendModel(
            cacheTypeList: $cacheTypeListStub,
            configWriter: $configWriterMock
        );

        $reflection = new \ReflectionClass($backendModel);
        $prop = $reflection->getProperty('importedRows');
        $prop->setValue($backendModel, [
            ['method_code' => 'connect', 'country_id' => 'US', 'price' => 150.0],
        ]);

        $backendModel->afterSave();

        $this->assertEmpty($prop->getValue($backendModel));
    }
}
