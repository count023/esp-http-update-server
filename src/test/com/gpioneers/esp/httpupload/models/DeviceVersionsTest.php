<?php

namespace com\gpioneers\esp\httpupload\models;

use com\gpioneers\esp\httpupload\models\Device;
use com\gpioneers\esp\httpupload\models\Devices;
use com\gpioneers\esp\httpupload\models\DeviceVersion;
use com\gpioneers\esp\httpupload\models\DeviceVersions;


class DeviceVersionsTest extends \PHPUnit_Framework_TestCase {

    private $logger;

    protected function setup() {
        $loggerHandler = new \Monolog\Handler\TestHandler();
        $this->logger = new \Monolog\Logger('testLogger', array($loggerHandler));
    }

    protected function tearDown() {
        $this->logger = null;
        unset($this->logger);
    }

    /**
     * @before
     */
    public static function createDataDirTest() {
        if (is_dir(DATA_DIR)) { // cleanup from a previous failed test run is needed!
            self::removeDataDirTest();
        }
        mkdir(DATA_DIR, 0775);
    }

    /**
     * @after
     */
    public static function removeDataDirTest() {
        system('rm -rf ' . DATA_DIR);
    }

    /**
     * @test
     */
    public function getAll() {

        // create mac-address-folder with valid info-file
        mkdir(DATA_DIR . '11:11:11:11:11:11', 0775);
        $fileHandle = fopen(DATA_DIR . '11:11:11:11:11:11/info.json', 'w');
        fwrite($fileHandle, '{"type":"type","info":"info"}');
        fclose($fileHandle);

        // create file in version directory -> have to be skipped!
        $fileHandle = fopen(DATA_DIR . '11:11:11:11:11:11/to-be-ignored.txt', 'w');
        fwrite($fileHandle, 'to be ignored!');
        fclose($fileHandle);

        // create empty folder named not like an valid version -> have to be skipped
        mkdir(DATA_DIR . '11:11:11:11:11:11/invalidVersionDir', 0775);

        // create empty version-folder -> have to be skipped
        mkdir(DATA_DIR . '1.0', 0775);

        // create version-folder with valid content
        mkdir(DATA_DIR . '11:11:11:11:11:11/1.1', 0775);
        $fileHandle = fopen(DATA_DIR . '11:11:11:11:11:11/1.1/info.json', 'w');
        fwrite($fileHandle, '{"softwareName":"softwareName","description":"description"}');
        fclose($fileHandle);

        // create version-folder with valid content
        mkdir(DATA_DIR . '11:11:11:11:11:11/1.2', 0775);
        $fileHandle = fopen(DATA_DIR . '11:11:11:11:11:11/1.2/info.json', 'w');
        fwrite($fileHandle, '{"softwareName":"softwareName","description":"description"}');
        fclose($fileHandle);
        $fileHandle = fopen(DATA_DIR . '11:11:11:11:11:11/1.2/image.bin', 'w');
        fwrite($fileHandle, 'binary data');
        fclose($fileHandle);


        // expects one DeviceVersion to be returned
        $devicesRepository = new Devices($this->logger);
        $deviceVersionsRepository = new DeviceVersions($this->logger);

        $deviceVersions = $deviceVersionsRepository->getAll($devicesRepository->load('11:11:11:11:11:11'));

        $this->assertEquals(1, count($deviceVersions));
        $this->assertEquals('1.2', $deviceVersions[0]->getVersion());
        $this->assertEquals('softwareName', $deviceVersions[0]->getSoftwareName());
        $this->assertEquals('description', $deviceVersions[0]->getDescription());
    }

    /**
     * @test
     */
    public function save() {

        $devicesRepository = new Devices($this->logger);

        $device = $devicesRepository->load('22:22:22:22:22:22');
        $device->setType('type');
        $device->setInfo('info');
        $success = $devicesRepository->save($device);

        $deviceVersionsRepository = new DeviceVersions($this->logger);

        $deviceVersion = $deviceVersionsRepository->load($device, '2.2');
        $deviceVersion->setSoftwareName('softwareName');
        $deviceVersion->setDescription('description');
        $uploadedFileMock = $this->getMockBuilder('\Slim\Http\UploadedFile')->disableOriginalConstructor()->getMock();
        $uploadedFileMock->expects($this->once())->method('getError')->willReturn(UPLOAD_ERR_OK);
        $uploadedFileMock->expects($this->exactly(1))->method('getSize')->willReturn(11);
        $uploadedFileMock->expects($this->once())->method('moveTo')->will($this->returnCallback(function () {
            $fileHandle = fopen(DATA_DIR . '22:22:22:22:22:22/2.2/image.bin', 'w');
            fwrite($fileHandle, 'binary data');
            fclose($fileHandle);
        }));
        $success = $deviceVersionsRepository->save($device, $deviceVersion, $uploadedFileMock);

        $this->assertTrue($success);

        $this->assertTrue(is_dir(DATA_DIR . '22:22:22:22:22:22/2.2'));
        $this->assertTrue(is_file(DATA_DIR . '22:22:22:22:22:22/2.2/info.json'));
        $this->assertTrue(is_file(DATA_DIR . '22:22:22:22:22:22/2.2/image.bin'));

        $fileHandle = fopen(DATA_DIR . '22:22:22:22:22:22/2.2/info.json', 'r');
        $infoFileContent = fread($fileHandle, filesize(DATA_DIR . '22:22:22:22:22:22/2.2/info.json'));
        $this->assertEquals('{"softwareName":"softwareName","description":"description"}', $infoFileContent);
    }

    /**
     * @test
     * @expectedException Exception
     * @expectedExceptionMessage Uploaded file has error: 4
     */
    public function saveUploadFileError() {

        $devicesRepository = new Devices($this->logger);

        $device = $devicesRepository->load('33:33:33:33:33:33');
        $device->setType('type');
        $device->setInfo('info');
        $success = $devicesRepository->save($device);

        $deviceVersionsRepository = new DeviceVersions($this->logger);

        $deviceVersion = $deviceVersionsRepository->load($device, '3.3');
        $deviceVersion->setSoftwareName('softwareName');
        $deviceVersion->setDescription('description');
        $uploadedFileMock = $this->getMockBuilder('\Slim\Http\UploadedFile')->disableOriginalConstructor()->getMock();
        $uploadedFileMock->expects($this->exactly(2))->method('getError')->willReturn(UPLOAD_ERR_NO_FILE);
        $uploadedFileMock->expects($this->exactly(1))->method('getSize')->willReturn(11);
        $uploadedFileMock->expects($this->never())->method('moveTo')->will($this->returnCallback(function () {
            $fileHandle = fopen(DATA_DIR . '22:22:22:22:22:22/2.2/image.bin', 'w');
            fwrite($fileHandle, 'binary data');
            fclose($fileHandle);
        }));

        $success = $deviceVersionsRepository = $deviceVersionsRepository->save($device, $deviceVersion, $uploadedFileMock);
    }

    /**
     * @test
     */
    public function update() {

        mkdir(DATA_DIR . '22:22:22:22:22:22', 0775);
        $fileHandle = fopen(DATA_DIR . '22:22:22:22:22:22/info.json', 'w');
        fwrite($fileHandle, '{"type":"type","info":"info"}');
        fclose($fileHandle);

        // create version-folder with valid content
        mkdir(DATA_DIR . '22:22:22:22:22:22/2.2', 0775);
        $fileHandle = fopen(DATA_DIR . '22:22:22:22:22:22/2.2/info.json', 'w');
        fwrite($fileHandle, '{"softwareName":"softwareName","description":"description"}');
        fclose($fileHandle);

        $fileHandle = fopen(DATA_DIR . '22:22:22:22:22:22/2.2/image.bin', 'w');
        fwrite($fileHandle, 'binary data');
        fclose($fileHandle);

        $devicesRepository = new Devices($this->logger);
        $device = $devicesRepository->load('22:22:22:22:22:22');
        $deviceVersionsRepository = new DeviceVersions($this->logger);

        $currentDeviceVersion = $deviceVersionsRepository->load($device, '2.2');
        $newDeviceVersion = $deviceVersionsRepository->load($device, '3.3');
        $newDeviceVersion->setSoftwareName('newSoftwareName');
        $newDeviceVersion->setDescription('newDescription');

        $uploadedFileMock = $this->getMockBuilder('\Slim\Http\UploadedFile')->disableOriginalConstructor()->getMock();
        $uploadedFileMock->expects($this->exactly(2))->method('getSize')->willReturn(11);
        $uploadedFileMock->expects($this->once())->method('getError')->willReturn(UPLOAD_ERR_OK);
        $uploadedFileMock->expects($this->once())->method('moveTo')->will($this->returnCallback(function () {
            $fileHandle = fopen(DATA_DIR . '22:22:22:22:22:22/3.3/image.bin', 'w');
            fwrite($fileHandle, 'binary data');
            fclose($fileHandle);
        }));

        $success = $deviceVersionsRepository->update($device, $currentDeviceVersion, $newDeviceVersion, $uploadedFileMock);

        $fileHandle = fopen(DATA_DIR . '22:22:22:22:22:22/3.3/info.json', 'r');
        $infoFileContent = fread($fileHandle, filesize(DATA_DIR . '22:22:22:22:22:22/3.3/info.json'));
        $this->assertEquals('{"softwareName":"newSoftwareName","description":"newDescription"}', $infoFileContent);

    }

    // @TODO: test with empty file update!

    /**
     * @test
     */
    public function load() {

        // create mac-address-folder with valid info-file
        mkdir(DATA_DIR . '44:44:44:44:44:44', 0775);
        $fileHandle = fopen(DATA_DIR . '44:44:44:44:44:44/info.json', 'w');
        fwrite($fileHandle, '{"type":"type","info":"info"}');
        fclose($fileHandle);

        // create version-folder with valid content
        mkdir(DATA_DIR . '44:44:44:44:44:44/4.4', 0775);
        $fileHandle = fopen(DATA_DIR . '44:44:44:44:44:44/4.4/info.json', 'w');
        fwrite($fileHandle, '{"softwareName":"softwareName","description":"description"}');
        fclose($fileHandle);
        $fileHandle = fopen(DATA_DIR . '44:44:44:44:44:44/4.4/image.bin', 'w');
        fwrite($fileHandle, 'binary data');
        fclose($fileHandle);

        // expects one DeviceVersion to be returned
        $devicesRepository = new Devices($this->logger);
        $deviceVersionsRepository = new DeviceVersions($this->logger);

        $device = $devicesRepository->load('44:44:44:44:44:44');
        $deviceVersion = $deviceVersionsRepository->load($device, '4.4');

        $this->assertEquals('4.4', $deviceVersion->getVersion());
        $this->assertEquals('softwareName', $deviceVersion->getSoftwareName());
        $this->assertEquals('description', $deviceVersion->getDescription());
        $this->assertTrue($deviceVersion->isExisting());
        $this->assertTrue($deviceVersion->isValid());
    }

    /**
     * @test
     * @expectedException Exception
     * @expectedExceptionMessage Invalid device given to load! (isExisting: false isValid: false)
     */
    public function loadInvalidDevice() {

        // expects one DeviceVersion to be returned
        $devicesRepository = new Devices($this->logger);
        $deviceVersionsRepository = new DeviceVersions($this->logger);

        $device = $devicesRepository->load('55:55:55:55:55:55');
        $deviceVersion = $deviceVersionsRepository->load($device, '5.5');

    }

    /**
     * @test
     * @expectedException Exception
     * @expectedExceptionMessage Invalid version given to load!
     */
    public function loadInvalidVersion() {

        mkdir(DATA_DIR . '66:66:66:66:66:66', 0775);
        $fileHandle = fopen(DATA_DIR . '66:66:66:66:66:66/info.json', 'w');
        fwrite($fileHandle, '{"type":"type","info":"info"}');
        fclose($fileHandle);

        // expects one DeviceVersion to be returned
        $devicesRepository = new Devices($this->logger);
        $deviceVersionsRepository = new DeviceVersions($this->logger);

        $device = $devicesRepository->load('66:66:66:66:66:66');
        $deviceVersion = $deviceVersionsRepository->load($device, 'invalid');
    }

    /**
     * @test
     */
    public function loadNoBinaryFile() {

        mkdir(DATA_DIR . '77:77:77:77:77:77', 0775);
        $fileHandle = fopen(DATA_DIR . '77:77:77:77:77:77/info.json', 'w');
        fwrite($fileHandle, '{"type":"type","info":"info"}');
        fclose($fileHandle);

        // create version-folder with valid content
        mkdir(DATA_DIR . '77:77:77:77:77:77/7.7', 0775);
        $fileHandle = fopen(DATA_DIR . '77:77:77:77:77:77/7.7/info.json', 'w');
        fwrite($fileHandle, '{"softwareName":"softwareName","description":"description"}');
        fclose($fileHandle);

        // expects one DeviceVersion to be returned
        $devicesRepository = new Devices($this->logger);
        $deviceVersionsRepository = new DeviceVersions($this->logger);

        $device = $devicesRepository->load('77:77:77:77:77:77');
        $deviceVersion = $deviceVersionsRepository->load($device, '7.7');

        $this->assertTrue($deviceVersion->isExisting());
        $this->assertFalse($deviceVersion->isValid());
    }

    /**
     * @test
     */
    public function loadNoVersionDirectpry() {

        mkdir(DATA_DIR . '77:77:77:77:77:77', 0775);
        $fileHandle = fopen(DATA_DIR . '77:77:77:77:77:77/info.json', 'w');
        fwrite($fileHandle, '{"type":"type","info":"info"}');
        fclose($fileHandle);

        // expects one DeviceVersion to be returned
        $devicesRepository = new Devices($this->logger);
        $deviceVersionsRepository = new DeviceVersions($this->logger);

        $device = $devicesRepository->load('77:77:77:77:77:77');
        $deviceVersion = $deviceVersionsRepository->load($device, '7.7');

        $this->assertFalse($deviceVersion->isExisting());
        $this->assertFalse($deviceVersion->isValid());
    }

    /**
     * @test
     */
    public function deleteSuccess() {

        mkdir(DATA_DIR . 'AA:AA:AA:AA:AA:AA', 0775);
        $fileHandle = fopen(DATA_DIR . 'AA:AA:AA:AA:AA:AA/info.json', 'w');
        fwrite($fileHandle, '{"type":"type","info":"info"}');
        fclose($fileHandle);

        // create version-folder with valid content
        mkdir(DATA_DIR . 'AA:AA:AA:AA:AA:AA/10.10', 0775);
        $fileHandle = fopen(DATA_DIR . 'AA:AA:AA:AA:AA:AA/10.10/info.json', 'w');
        fwrite($fileHandle, '{"softwareName":"softwareName","description":"description"}');
        fclose($fileHandle);

        $fileHandle = fopen(DATA_DIR . 'AA:AA:AA:AA:AA:AA/10.10/image.bin', 'w');
        fwrite($fileHandle, 'binary data');
        fclose($fileHandle);

        $devicesRepository = new Devices($this->logger);
        $device = $devicesRepository->load('AA:AA:AA:AA:AA:AA');
        $deviceVersionsRepository = new DeviceVersions($this->logger);
        $deviceVersion = $deviceVersionsRepository->load($device, '10.10');
        $deviceVersionsRepository->delete($device, $deviceVersion);

        $this->assertEquals(
            'Deleted info-file of version 10.10 of device with mac: AA:AA:AA:AA:AA:AA',
            $this->logger->getHandlers()[0]->getRecords()[0]['message']
        );
        $this->assertEquals(
            'INFO',
            $this->logger->getHandlers()[0]->getRecords()[0]['level_name']
        );
        $this->assertEquals(
            'Deleted image-file of version 10.10 of device with mac: AA:AA:AA:AA:AA:AA',
            $this->logger->getHandlers()[0]->getRecords()[1]['message']
        );
        $this->assertEquals(
            'INFO',
            $this->logger->getHandlers()[0]->getRecords()[1]['level_name']
        );
        $this->assertEquals(
            'Deleted directory of version 10.10 of device with mac: AA:AA:AA:AA:AA:AA',
            $this->logger->getHandlers()[0]->getRecords()[2]['message']
        );
        $this->assertEquals(
            'INFO',
            $this->logger->getHandlers()[0]->getRecords()[2]['level_name']
        );
    }

    /**
     * @test
     * @expectedException Exception
     * @expectedExceptionMessage Failed deleting info-file of version 11.11 of device with mac: BB:BB:BB:BB:BB:BB
     */
    public function deleteNoInfoFile() {
        mkdir(DATA_DIR . 'BB:BB:BB:BB:BB:BB', 0775);
        $fileHandle = fopen(DATA_DIR . 'BB:BB:BB:BB:BB:BB/info.json', 'w');
        fwrite($fileHandle, '{"type":"type","info":"info"}');
        fclose($fileHandle);
        mkdir(DATA_DIR . 'BB:BB:BB:BB:BB:BB/11.11', 0775);
        $fileHandle = fopen(DATA_DIR . 'BB:BB:BB:BB:BB:BB/11.11/image.bin', 'w');
        fwrite($fileHandle, 'binary data');
        fclose($fileHandle);

        $devicesRepository = new Devices($this->logger);
        $device = $devicesRepository->load('BB:BB:BB:BB:BB:BB');
        $deviceVersionsRepository = new DeviceVersions($this->logger);
        $deviceVersion = $deviceVersionsRepository->load($device, '11.11');
        $deviceVersionsRepository->delete($device, $deviceVersion);

    }

    /**
     * @test
     * @expectedException Exception
     * @expectedExceptionMessage Failed deleting image-file of version 12.12 of device with mac: CC:CC:CC:CC:CC:CC
     */
    public function deleteNoImageFile() {
        mkdir(DATA_DIR . 'CC:CC:CC:CC:CC:CC', 0775);
        $fileHandle = fopen(DATA_DIR . 'CC:CC:CC:CC:CC:CC/info.json', 'w');
        fwrite($fileHandle, '{"type":"type","info":"info"}');
        fclose($fileHandle);
        mkdir(DATA_DIR . 'CC:CC:CC:CC:CC:CC/12.12', 0775);
        $fileHandle = fopen(DATA_DIR . 'CC:CC:CC:CC:CC:CC/12.12/info.json', 'w');
        fwrite($fileHandle, '{"softwareName":"softwareName","description":"description"}');
        fclose($fileHandle);

        $devicesRepository = new Devices($this->logger);
        $device = $devicesRepository->load('CC:CC:CC:CC:CC:CC');
        $deviceVersionsRepository = new DeviceVersions($this->logger);
        $deviceVersion = $deviceVersionsRepository->load($device, '12.12');
        $deviceVersionsRepository->delete($device, $deviceVersion);

    }

    /**
     * @test
     * @expectedException Exception
     * @expectedExceptionMessage Failed deleting info-file of version 13.13 of device with mac: DD:DD:DD:DD:DD:DD
     */
    public function deleteNoVersionDirectory() {

        mkdir(DATA_DIR . 'DD:DD:DD:DD:DD:DD', 0775);
        $fileHandle = fopen(DATA_DIR . 'DD:DD:DD:DD:DD:DD/info.json', 'w');
        fwrite($fileHandle, '{"type":"type","info":"info"}');
        fclose($fileHandle);

        $devicesRepository = new Devices($this->logger);
        $device = $devicesRepository->load('DD:DD:DD:DD:DD:DD');
        $deviceVersionsRepository = new DeviceVersions($this->logger);
        $deviceVersion = $deviceVersionsRepository->load($device, '13.13');
        $deviceVersionsRepository->delete($device, $deviceVersion);
    }

    /**
     * @test
     */
    public function isValidVersion() {

        $deviceVersionsRepository = new DeviceVersions($this->logger);

        $this->assertFalse($deviceVersionsRepository->isValidVersion('invalid'));
        $this->assertFalse($deviceVersionsRepository->isValidVersion('1'));
        $this->assertFalse($deviceVersionsRepository->isValidVersion('11'));
        $this->assertFalse($deviceVersionsRepository->isValidVersion('11.'));

        $this->assertTrue($deviceVersionsRepository->isValidVersion('11.1'));
        $this->assertTrue($deviceVersionsRepository->isValidVersion('11.11'));
        $this->assertTrue($deviceVersionsRepository->isValidVersion('11.111'));

        $this->assertFalse($deviceVersionsRepository->isValidVersion('11:1'));
        $this->assertFalse($deviceVersionsRepository->isValidVersion('11:11'));
        $this->assertFalse($deviceVersionsRepository->isValidVersion('11:111'));

        $this->assertTrue($deviceVersionsRepository->isValidVersion('11.111.1'));
        $this->assertTrue($deviceVersionsRepository->isValidVersion('11.111.12'));
        $this->assertTrue($deviceVersionsRepository->isValidVersion('11.111.123'));
    }

    /**
     * @test
     * @expectedException Exception
     * @expectedExceptionMessage Access to empty $version of com\gpioneers\esp\httpupload\models\DeviceVersion. Probably using not fully initialized com\gpioneers\esp\httpupload\models\DeviceVersion?
     */
    public function getDeviceVersionInfoPath() {
        $device = new Device('', $this->logger);
        $deviceVersion = new DeviceVersion('', $this->logger);

        $deviceVersionsRepository = new DeviceVersions($this->logger);

        $exception = null;
        try {
            $deviceVersionsRepository->getDeviceVersionInfoPath($device, $deviceVersion);
        } catch(\Exception $e) {
            $exception = $e;
        }

        $this->assertEquals(
            'Access to empty $version of ' . get_class($deviceVersion) . '. Probably using not fully initialized ' . get_class($deviceVersion) . '?',
            $this->logger->getHandlers()[0]->getRecords()[0]['message']
        );
        $this->assertEquals(
            'ERROR',
            $this->logger->getHandlers()[0]->getRecords()[0]['level_name']
        );

        throw $exception;
    }

    /**
     * @test
     * @expectedException Exception
     * @expectedExceptionMessage Access to empty $version of com\gpioneers\esp\httpupload\models\DeviceVersion. Probably using not fully initialized com\gpioneers\esp\httpupload\models\DeviceVersion?
     */
    public function getDeviceVersionImagePath() {
        $device = new Device('', $this->logger);
        $deviceVersion = new DeviceVersion('', $this->logger);

        $deviceVersionsRepository = new DeviceVersions($this->logger);

        $exception = null;
        try {
            $deviceVersionsRepository->getDeviceVersionImagePath($device, $deviceVersion);
        } catch(\Exception $e) {
            $exception = $e;
        }

        $this->assertEquals(
            'Access to empty $version of ' . get_class($deviceVersion) . '. Probably using not fully initialized ' . get_class($deviceVersion) . '?',
            $this->logger->getHandlers()[0]->getRecords()[0]['message']
        );
        $this->assertEquals(
            'ERROR',
            $this->logger->getHandlers()[0]->getRecords()[0]['level_name']
        );

        throw $exception;
    }

}
