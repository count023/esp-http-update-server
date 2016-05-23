<?php

use com\gpioneers\esp\httpupload\models\Devices;
use com\gpioneers\esp\httpupload\models\Device;

class DevicesTest extends PHPUnit_Framework_TestCase {

    private $logger;

    protected function setup() {
        $loggerHandler = new Monolog\Handler\TestHandler();
        $this->logger = new Monolog\Logger('testLogger', array($loggerHandler));
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

        // create file in DATA_DIR -> have to be skipped!
        $fileHandle = fopen(DATA_DIR . 'to-be-ignored.txt', 'w');
        fwrite($fileHandle, 'to be ignored!');
        fclose($fileHandle);

        // create empty folder named not like an valid mac -> have to be skipped
        mkdir(DATA_DIR . 'invalidMacDir', 0775);

        // create empty mac-address-folder -> have to be skipped
        mkdir(DATA_DIR . '11:11:11:11:11:11', 0775);

        // create mac-address-folder with valid info-file
        mkdir(DATA_DIR . '22:22:22:22:22:22', 0775);
        $fileHandle = fopen(DATA_DIR . '22:22:22:22:22:22/info.json', 'w');
        fwrite($fileHandle, '{"type":"type","info":"info"}');
        fclose($fileHandle);


        // expects one Device to be returned
        $devicesRepository = new Devices($this->logger);

        $devices = $devicesRepository->getAll();

        $this->assertEquals(1, count($devices));
        $this->assertEquals('22:22:22:22:22:22', $devices[0]->getMac());
        $this->assertEquals('type', $devices[0]->getType());
        $this->assertEquals('info', $devices[0]->getInfo());
    }

    /**
     * @test
     */
    public function save() {

        $devicesRepository = new Devices($this->logger);

        $device = $devicesRepository->load('33:33:33:33:33:33');
        $device->setType('type');
        $device->setInfo('info');
        $success = $devicesRepository->save($device);

        $this->assertTrue($success);
        $this->assertTrue(is_dir(DATA_DIR . '33:33:33:33:33:33'));
        $this->assertTrue(is_file(DATA_DIR . '33:33:33:33:33:33/info.json'));

        $fileHandle = fopen(DATA_DIR . '33:33:33:33:33:33/info.json', 'r');
        $infoFileContent = fread($fileHandle, filesize(DATA_DIR . '33:33:33:33:33:33/info.json'));
        $this->assertEquals('{"type":"type","info":"info"}', $infoFileContent);
    }

    /**
     * @test
     */
    public function update() {
        mkdir(DATA_DIR . '22:22:22:22:22:22', 0775);
        $fileHandle = fopen(DATA_DIR . '22:22:22:22:22:22/info.json', 'w');
        fwrite($fileHandle, '{"type":"type","info":"info"}');
        fclose($fileHandle);

        $devicesRepository = new Devices($this->logger);
        $currentDevice = $devicesRepository->load('22:22:22:22:22:22');
        $newDevice = $devicesRepository->load('22:22:22:22:22:22');
        $newDevice->setType('newType');
        $newDevice->setInfo('newInfo');

        $success = $devicesRepository->update($currentDevice, $newDevice);

        $fileHandle = fopen(DATA_DIR . '22:22:22:22:22:22/info.json', 'r');
        $infoFileContent = fread($fileHandle, filesize(DATA_DIR . '22:22:22:22:22:22/info.json'));
        $this->assertEquals('{"type":"newType","info":"newInfo"}', $infoFileContent);

    }

    /**
     * @test
     */
    public function updateChangingMac() {
        mkdir(DATA_DIR . '22:22:22:22:22:22', 0775);
        $fileHandle = fopen(DATA_DIR . '22:22:22:22:22:22/info.json', 'w');
        fwrite($fileHandle, '{"type":"type","info":"info"}');
        fclose($fileHandle);

        $devicesRepository = new Devices($this->logger);
        $currentDevice = $devicesRepository->load('22:22:22:22:22:22');
        $newDevice = $devicesRepository->load('33:33:33:33:33:33');
        $newDevice->setType('newType');
        $newDevice->setInfo('newInfo');

        $success = $devicesRepository->update($currentDevice, $newDevice);

        $this->assertFalse(is_dir(DATA_DIR . '22:22:22:22:22:22'));
        $this->assertTrue(is_dir(DATA_DIR . '33:33:33:33:33:33'));
        $this->assertTrue(is_file(DATA_DIR . '33:33:33:33:33:33/info.json'));
        $fileHandle = fopen(DATA_DIR . '33:33:33:33:33:33/info.json', 'r');
        $infoFileContent = fread($fileHandle, filesize(DATA_DIR . '33:33:33:33:33:33/info.json'));
        $this->assertEquals('{"type":"newType","info":"newInfo"}', $infoFileContent);
    }

    /**
     * @test
     */
    public function updateChangingMacWithVersions() {

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
        $currentDevice = $devicesRepository->load('22:22:22:22:22:22');
        $newDevice = $devicesRepository->load('33:33:33:33:33:33');
        $newDevice->setType('newType');
        $newDevice->setInfo('newInfo');

        $success = $devicesRepository->update($currentDevice, $newDevice);

        $this->assertFalse(is_dir(DATA_DIR . '22:22:22:22:22:22'));
        $this->assertTrue(is_dir(DATA_DIR . '33:33:33:33:33:33'));
        $this->assertTrue(is_file(DATA_DIR . '33:33:33:33:33:33/info.json'));
        $this->assertTrue(is_dir(DATA_DIR . '33:33:33:33:33:33/2.2'));
        $this->assertTrue(is_file(DATA_DIR . '33:33:33:33:33:33/2.2/info.json'));
        $this->assertTrue(is_file(DATA_DIR . '33:33:33:33:33:33/2.2/image.bin'));
        $fileHandle = fopen(DATA_DIR . '33:33:33:33:33:33/info.json', 'r');
        $infoFileContent = fread($fileHandle, filesize(DATA_DIR . '33:33:33:33:33:33/info.json'));
        $this->assertEquals('{"type":"newType","info":"newInfo"}', $infoFileContent);
        $fileHandle = fopen(DATA_DIR . '33:33:33:33:33:33/2.2/info.json', 'r');
        $infoFileContent = fread($fileHandle, filesize(DATA_DIR . '33:33:33:33:33:33/2.2/info.json'));
        $this->assertEquals('{"softwareName":"softwareName","description":"description"}', $infoFileContent);
    }

    /**
     * @test
     */
    public function loadNewDevice() {
        $devicesRepository = new Devices($this->logger);
        $device = $devicesRepository->load(null);

        $this->assertEmpty($device->getMac());
        $this->assertEmpty($device->getType());
        $this->assertEmpty($device->getInfo());
    }

    /**
     * @test
     * @expectedException Exception
     * @expectedExceptionMessage Invalid mac given to load!
     */
    public function loadInvalid() {
        $devicesRepository = new Devices($this->logger);
        $device = $devicesRepository->load('invalid');
    }

    /**
     * @test
     */
    public function loadNotExisting() {
        $devicesRepository = new Devices($this->logger);
        $device = $devicesRepository->load('99:99:99:99:99:99');

        $this->assertEquals('99:99:99:99:99:99', $device->getMac());
        $this->assertNull($device->getType());
        $this->assertNull($device->getInfo());
        $this->assertFalse($device->isExisting());
        $this->assertFalse($device->isValid());
    }

    /**
     * @test
     */
    public function loadExisting() {

        mkdir(DATA_DIR . '22:22:22:22:22:22', 0775);
        $fileHandle = fopen(DATA_DIR . '22:22:22:22:22:22/info.json', 'w');
        fwrite($fileHandle, '{"type":"type","info":"info"}');
        fclose($fileHandle);

        $devicesRepository = new Devices($this->logger);
        $device = $devicesRepository->load('22:22:22:22:22:22');

        $this->assertEquals('22:22:22:22:22:22', $device->getMac());
        $this->assertEquals('type', $device->getType());
        $this->assertEquals('info', $device->getInfo());
        $this->assertTrue($device->isExisting());
        $this->assertTrue($device->isValid());
    }

    /**
     * @test
     */
    public function validateCompletelyEmpty() {

        $devicesRepository = new Devices($this->logger);
        $msgs = $devicesRepository->validate(array());

        $this->assertEquals('Keine Mac-Adresse angegeben!', $msgs['mac']);
        $this->assertEquals('Keine ESP-Version angegeben!', $msgs['type']);
        $this->assertEquals('Keine weiteren Informationen zum ESP angegeben!', $msgs['deviceInfo']);
    }

    /**
     * @test
     */
    public function validateInvalidMac() {

        $devicesRepository = new Devices($this->logger);
        $msgs = $devicesRepository->validate(array('mac' => 'invalid'));

        $this->assertEquals('Ungültige Mac-Adresse angegeben!', $msgs['mac']);
        $this->assertEquals('Keine ESP-Version angegeben!', $msgs['type']);
        $this->assertEquals('Keine weiteren Informationen zum ESP angegeben!', $msgs['deviceInfo']);
    }

    /**
     * @test
     */
    public function validateExistingMac() {

        mkdir(DATA_DIR . '22:22:22:22:22:22', 0775);
        $fileHandle = fopen(DATA_DIR . '22:22:22:22:22:22/info.json', 'w');
        fwrite($fileHandle, '{"type":"type","info":"info"}');
        fclose($fileHandle);

        $devicesRepository = new Devices($this->logger);
        $msgs = $devicesRepository->validate(array('mac' => '22:22:22:22:22:22'));

        $this->assertEquals('Device mit dieser Mac-Adresse existiert bereits!', $msgs['mac']);
        $this->assertEquals('Keine ESP-Version angegeben!', $msgs['type']);
        $this->assertEquals('Keine weiteren Informationen zum ESP angegeben!', $msgs['deviceInfo']);
    }

    /**
     * @test
     */
    public function validateValid() {

        $devicesRepository = new Devices($this->logger);
        $msgs = $devicesRepository->validate(array(
            'mac' => '22:22:22:22:22:22',
            'type' => 'type',
            'deviceInfo' => 'info'
        ));

        $this->assertEmpty($msgs);
    }

    /**
     * @test
     */
    public function deleteSuccess() {

        mkdir(DATA_DIR . '22:22:22:22:22:22', 0775);
        $fileHandle = fopen(DATA_DIR . '22:22:22:22:22:22/info.json', 'w');
        fwrite($fileHandle, '{"type":"type","info":"info"}');
        fclose($fileHandle);

        $devicesRepository = new Devices($this->logger);
        $device = $devicesRepository->load('22:22:22:22:22:22');
        $devicesRepository->delete($device);

        $this->assertEquals(
            'Deleted info-file of device with mac: 22:22:22:22:22:22',
            $this->logger->getHandlers()[0]->getRecords()[0]['message']
        );
        $this->assertEquals(
            'INFO',
            $this->logger->getHandlers()[0]->getRecords()[0]['level_name']
        );
        $this->assertEquals(
            'Deleted directory of device with mac: 22:22:22:22:22:22',
            $this->logger->getHandlers()[0]->getRecords()[1]['message']
        );
        $this->assertEquals(
            'INFO',
            $this->logger->getHandlers()[0]->getRecords()[1]['level_name']
        );
    }

    /**
     * @test
     */
    public function deleteSuccessWithVersions() {

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
        $devicesRepository->delete($device);

        $this->assertEquals(
            'Deleted info-file of version 2.2 of device with mac: 22:22:22:22:22:22',
            $this->logger->getHandlers()[0]->getRecords()[0]['message']
        );
        $this->assertEquals(
            'INFO',
            $this->logger->getHandlers()[0]->getRecords()[0]['level_name']
        );
        $this->assertEquals(
            'Deleted image-file of version 2.2 of device with mac: 22:22:22:22:22:22',
            $this->logger->getHandlers()[0]->getRecords()[1]['message']
        );
        $this->assertEquals(
            'INFO',
            $this->logger->getHandlers()[0]->getRecords()[1]['level_name']
        );
        $this->assertEquals(
            'Deleted directory of version 2.2 of device with mac: 22:22:22:22:22:22',
            $this->logger->getHandlers()[0]->getRecords()[2]['message']
        );
        $this->assertEquals(
            'INFO',
            $this->logger->getHandlers()[0]->getRecords()[2]['level_name']
        );

        $this->assertEquals(
            'Deleted info-file of device with mac: 22:22:22:22:22:22',
            $this->logger->getHandlers()[0]->getRecords()[3]['message']
        );
        $this->assertEquals(
            'INFO',
            $this->logger->getHandlers()[0]->getRecords()[3]['level_name']
        );
        $this->assertEquals(
            'Deleted directory of device with mac: 22:22:22:22:22:22',
            $this->logger->getHandlers()[0]->getRecords()[4]['message']
        );
        $this->assertEquals(
            'INFO',
            $this->logger->getHandlers()[0]->getRecords()[4]['level_name']
        );
    }

    /**
     * @test
     * @expectedException Exception
     * @expectedExceptionMessage Failed deleting info-file of device with mac: 22:22:22:22:22:22
     */
    public function deleteNoInfoFile() {
        mkdir(DATA_DIR . '22:22:22:22:22:22', 0775);

        $devicesRepository = new Devices($this->logger);
        $device = $devicesRepository->load('22:22:22:22:22:22');
        $devicesRepository->delete($device);
    }

    /**
     * @test
     * @expectedException Exception
     * @expectedExceptionMessage Failed deleting info-file of device with mac: 22:22:22:22:22:22
     */
    public function deleteNoBaseDirectory() {

        $devicesRepository = new Devices($this->logger);
        $device = $devicesRepository->load('22:22:22:22:22:22');
        $devicesRepository->delete($device);
    }

    /**
     * @test
     */
    public function isValidMac() {

        $devicesRepository = new Devices($this->logger);

        $this->assertFalse($devicesRepository->isValidMac('invalid'));
        $this->assertFalse($devicesRepository->isValidMac('1'));
        $this->assertFalse($devicesRepository->isValidMac('11'));
        $this->assertFalse($devicesRepository->isValidMac('11:'));
        $this->assertFalse($devicesRepository->isValidMac('11:1'));
        $this->assertFalse($devicesRepository->isValidMac('11:11'));
        $this->assertFalse($devicesRepository->isValidMac('11:11:'));
        $this->assertFalse($devicesRepository->isValidMac('11:11:1'));
        $this->assertFalse($devicesRepository->isValidMac('11:11:11'));
        $this->assertFalse($devicesRepository->isValidMac('11:11:11:'));
        $this->assertFalse($devicesRepository->isValidMac('11:11:11:1'));
        $this->assertFalse($devicesRepository->isValidMac('11:11:11:11'));
        $this->assertFalse($devicesRepository->isValidMac('11:11:11:11:'));
        $this->assertFalse($devicesRepository->isValidMac('11:11:11:11:1'));
        $this->assertFalse($devicesRepository->isValidMac('11:11:11:11:11'));
        $this->assertFalse($devicesRepository->isValidMac('11:11:11:11:11:'));
        $this->assertFalse($devicesRepository->isValidMac('11:11:11:11:11:1'));
        $this->assertTrue($devicesRepository->isValidMac('11:11:11:11:11:11'));
        $this->assertFalse($devicesRepository->isValidMac('11:!1:11:11:11:11'));
        $this->assertFalse($devicesRepository->isValidMac('11_11-11$11?11/11'));
        $this->assertFalse($devicesRepository->isValidMac('FF:FF:FF:FF:FF:FG'));
        $this->assertTrue($devicesRepository->isValidMac('FF:FF:FF:FF:FF:FF'));
    }

    /**
     * @test
     * @expectedException Exception
     * @expectedExceptionMessage Access to empty $mac of com\gpioneers\esp\httpupload\models\Device. Probably using not fully initialized com\gpioneers\esp\httpupload\models\Device?
     */
    public function getDeviceInfoPath() {
        $device = new Device('', $this->logger);

        $devicesRepository = new Devices($this->logger);

        $exception = null;
        try {
            $devicesRepository->getDeviceInfoPath($device);
        } catch(\Exception $e) {
            $exception = $e;
        }

        $this->assertEquals(
            'Access to empty $mac of ' . get_class($device) . '. Probably using not fully initialized ' . get_class($device) . '?',
            $this->logger->getHandlers()[0]->getRecords()[0]['message']
        );
        $this->assertEquals(
            'ERROR',
            $this->logger->getHandlers()[0]->getRecords()[0]['level_name']
        );

        throw $exception;
    }

}
