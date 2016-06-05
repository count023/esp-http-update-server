<?php

namespace com\gpioneers\esp\httpupload\controllers;

use com\gpioneers\esp\httpupload\controllers\Device;
use com\gpioneers\esp\httpupload\controllers\DeviceVersion;
use com\gpioneers\esp\httpupload\models\Devices;

class DeviceVersionTest extends \PHPUnit_Framework_TestCase {

    private $logger;
    private $ci;
    private $device;

    /**
     * @before
     */
    public static function createDataDirTest() {
        if (is_dir(DATA_DIR)) { // cleanup from a previous failed test run is needed!
            self::removeDataDirTest();
        }
        mkdir(DATA_DIR, 0775);

        mkdir(DATA_DIR . '22:22:22:22:22:22', 0775);
        $fileHandle = fopen(DATA_DIR . '22:22:22:22:22:22/info.json', 'w');
        fwrite($fileHandle, '{"type":"type","info":"info"}');
        fclose($fileHandle);
    }

    /**
     * @after
     */
    public static function removeDataDirTest() {
        system('rm -rf ' . DATA_DIR);
    }

    protected function setup() {
        $loggerHandler = new \Monolog\Handler\TestHandler();
        $this->logger = new \Monolog\Logger('testLogger', array($loggerHandler));

        $containerMock = $this->getMockBuilder('\Slim\Container')->disableOriginalConstructor()->getMock();
        $containerMock->expects($this->exactly(3))->method('__get')->with($this->equalTo('logger'))->willReturn($this->logger);
        $this->ci = $containerMock;

        $devicesRepository = new Devices($this->logger);
        $this->device = $devicesRepository->load('22:22:22:22:22:22');
    }

    /**
     * @test
     */
    public function validateCompletelyEmpty() {

        $method = new \ReflectionMethod('com\gpioneers\esp\httpupload\controllers\DeviceVersion', 'validate');
        $method->setAccessible(true);

        $msgs = $method->invoke(new DeviceVersion($this->ci), $this->device, array());

        $this->assertEquals('Keine Version angegeben!', $msgs['version']);
        $this->assertEquals('Keinen Software-Namen angegeben!', $msgs['softwareName']);
        $this->assertEquals('Keine Beschreibung angegeben!', $msgs['description']);
        $this->assertEquals('Keine Datei gesendet!', $msgs['file']);
    }

    /**
     * @test
     */
    public function validateInvalidMacAndInvalidVersion() {

        $method = new \ReflectionMethod('com\gpioneers\esp\httpupload\controllers\DeviceVersion', 'validate');
        $method->setAccessible(true);

        $msgs = $method->invoke(new DeviceVersion($this->ci), $this->device, array('mac' => 'invalid', 'version' => 'invalid'));

        $this->assertEquals('Ungültige Version angegeben!', $msgs['version']);
        $this->assertEquals('Keinen Software-Namen angegeben!', $msgs['softwareName']);
        $this->assertEquals('Keine Beschreibung angegeben!', $msgs['description']);
        $this->assertEquals('Keine Datei gesendet!', $msgs['file']);
    }

    /**
     * @test
     */
    public function validateExistingVersion() {

        // create version-folder with valid content
        mkdir(DATA_DIR . '22:22:22:22:22:22/2.2', 0775);
        $fileHandle = fopen(DATA_DIR . '22:22:22:22:22:22/2.2/info.json', 'w');
        fwrite($fileHandle, '{"softwareName":"softwareName","description":"description"}');
        fclose($fileHandle);
        $fileHandle = fopen(DATA_DIR . '22:22:22:22:22:22/2.2/image.bin', 'w');
        fwrite($fileHandle, 'BINARY FILE');
        fclose($fileHandle);

        $devicesRepository = new Devices($this->logger);
        $device = $devicesRepository->load('22:22:22:22:22:22');

        $method = new \ReflectionMethod('\com\gpioneers\esp\httpupload\controllers\DeviceVersion', 'validate');
        $method->setAccessible(true);

        $msgs = $method->invoke(new DeviceVersion($this->ci), $device, array(
            'version' => '2.2'
        ));

        $this->assertEquals('Diese Version existiert bereits!', $msgs['version']);
        $this->assertEquals('Keinen Software-Namen angegeben!', $msgs['softwareName']);
        $this->assertEquals('Keine Beschreibung angegeben!', $msgs['description']);
        $this->assertEquals('Keine Datei gesendet!', $msgs['file']);
    }

    /**
     * @test
     */
    public function validateNoFile() {

        // create version-folder with valid content
        mkdir(DATA_DIR . '22:22:22:22:22:22/2.2', 0775);
        $fileHandle = fopen(DATA_DIR . '22:22:22:22:22:22/2.2/info.json', 'w');
        fwrite($fileHandle, '{"softwareName":"softwareName","description":"description"}');
        fclose($fileHandle);
        $fileHandle = fopen(DATA_DIR . '22:22:22:22:22:22/2.2/image.bin', 'w');
        fwrite($fileHandle, 'BINARY FILE');
        fclose($fileHandle);

        $devicesRepository = new Devices($this->logger);
        $device = $devicesRepository->load('22:22:22:22:22:22');

        $method = new \ReflectionMethod('\com\gpioneers\esp\httpupload\controllers\DeviceVersion', 'validate');
        $method->setAccessible(true);

        $msgs = $method->invoke(new DeviceVersion($this->ci), $device, array(
            'version' => '2.2',
            'softwareName' => 'softwareName',
            'description' => 'description'
        ));

        $this->assertEquals('Diese Version existiert bereits!', $msgs['version']);
        $this->assertTrue(!array_key_exists('softwareName', $msgs));
        $this->assertTrue(!array_key_exists('description', $msgs));
        $this->assertEquals('Keine Datei gesendet!', $msgs['file']);
    }

    /**
     * @test
     */
    public function validateNoFileUpdate() {

        // create version-folder with valid content
        mkdir(DATA_DIR . '22:22:22:22:22:22/2.2', 0775);
        $fileHandle = fopen(DATA_DIR . '22:22:22:22:22:22/2.2/info.json', 'w');
        fwrite($fileHandle, '{"softwareName":"softwareName","description":"description"}');
        fclose($fileHandle);
        $fileHandle = fopen(DATA_DIR . '22:22:22:22:22:22/2.2/image.bin', 'w');
        fwrite($fileHandle, 'BINARY FILE');
        fclose($fileHandle);

        $devicesRepository = new Devices($this->logger);
        $device = $devicesRepository->load('22:22:22:22:22:22');

        $method = new \ReflectionMethod('\com\gpioneers\esp\httpupload\controllers\DeviceVersion', 'validate');
        $method->setAccessible(true);

        $msgs = $method->invoke(new DeviceVersion($this->ci), $device, array(
            'currentVersion' => '2.2',
            'version' => '2.2',
            'softwareName' => 'softwareName',
            'description' => 'description'
        ), true);

        $this->assertTrue(!array_key_exists('version', $msgs));
        $this->assertTrue(!array_key_exists('softwareName', $msgs));
        $this->assertTrue(!array_key_exists('description', $msgs));
        $this->assertTrue(!array_key_exists('file', $msgs));
    }

    /**
     * @test
     */
    public function validateNoFileUpdateMoveVersion() {

        // create version-folder with valid content
        mkdir(DATA_DIR . '22:22:22:22:22:22/2.2', 0775);
        $fileHandle = fopen(DATA_DIR . '22:22:22:22:22:22/2.2/info.json', 'w');
        fwrite($fileHandle, '{"softwareName":"softwareName","description":"description"}');
        fclose($fileHandle);
        $fileHandle = fopen(DATA_DIR . '22:22:22:22:22:22/2.2/image.bin', 'w');
        fwrite($fileHandle, 'BINARY FILE');
        fclose($fileHandle);

        // create other version-folder with valid content
        mkdir(DATA_DIR . '22:22:22:22:22:22/2.3', 0775);
        $fileHandle = fopen(DATA_DIR . '22:22:22:22:22:22/2.3/info.json', 'w');
        fwrite($fileHandle, '{"softwareName":"softwareName","description":"description"}');
        fclose($fileHandle);
        $fileHandle = fopen(DATA_DIR . '22:22:22:22:22:22/2.3/image.bin', 'w');
        fwrite($fileHandle, 'BINARY FILE');
        fclose($fileHandle);

        $devicesRepository = new Devices($this->logger);
        $device = $devicesRepository->load('22:22:22:22:22:22');

        $method = new \ReflectionMethod('\com\gpioneers\esp\httpupload\controllers\DeviceVersion', 'validate');
        $method->setAccessible(true);

        $msgs = $method->invoke(new DeviceVersion($this->ci), $device, array(
            'currentVersion' => '2.2',
            'version' => '2.3',
            'softwareName' => 'softwareName',
            'description' => 'description'
        ), true);

        $this->assertEquals('You tried to change the version number to "2.3", but this version already exists!', $msgs['version']);
        $this->assertTrue(!array_key_exists('softwareName', $msgs));
        $this->assertTrue(!array_key_exists('description', $msgs));
        $this->assertTrue(!array_key_exists('file', $msgs));
    }

    /**
     * @test
     */
    public function validateValid() {

        $method = new \ReflectionMethod('\com\gpioneers\esp\httpupload\controllers\DeviceVersion', 'validate');
        $method->setAccessible(true);

        $uploadedFileMock = $this->getMockBuilder('\Slim\Http\UploadedFile')->disableOriginalConstructor()->getMock();
        $uploadedFileMock->expects($this->once())->method('getsize')->willReturn(123);

        $msgs = $method->invoke(new DeviceVersion($this->ci), $this->device, array(
            'version' => '0.0',
            'softwareName' => 'softwareName',
            'description' => 'description',
            'files' => array(
                'file' => $uploadedFileMock
            )
        ));

        $this->assertEmpty($msgs);
    }
}
