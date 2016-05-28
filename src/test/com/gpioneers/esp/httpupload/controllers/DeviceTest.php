<?php

namespace com\gpioneers\esp\httpupload\controllers;

use com\gpioneers\esp\httpupload\controllers\Device;

class DeviceTest extends \PHPUnit_Framework_TestCase {

    private $logger;
    private $ci;

    protected function setup() {
        $loggerHandler = new \Monolog\Handler\TestHandler();
        $this->logger = new \Monolog\Logger('testLogger', array($loggerHandler));

        $containerMock = $this->getMockBuilder('\Slim\Container')->disableOriginalConstructor()->getMock();
        $containerMock->expects($this->once())->method('__get')->with($this->equalTo('logger'))->willReturn($this->logger);
        $this->ci = $containerMock;
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
    public function validateCompletelyEmpty() {

        $method = new \ReflectionMethod('com\gpioneers\esp\httpupload\controllers\Device', 'validate');
        $method->setAccessible(true);

        $msgs = $method->invoke(new Device($this->ci), array());

        $this->assertEquals('Keine Mac-Adresse angegeben!', $msgs['mac']);
        $this->assertEquals('Keine ESP-Version angegeben!', $msgs['type']);
        $this->assertEquals('Keine weiteren Informationen zum ESP angegeben!', $msgs['info']);
    }

    /**
     * @test
     */
    public function validateInvalidMac() {

        $method = new \ReflectionMethod('com\gpioneers\esp\httpupload\controllers\Device', 'validate');
        $method->setAccessible(true);

        $msgs = $method->invoke(new Device($this->ci), array('mac' => 'invalid'));

        $this->assertEquals('Ungültige Mac-Adresse angegeben!', $msgs['mac']);
        $this->assertEquals('Keine ESP-Version angegeben!', $msgs['type']);
        $this->assertEquals('Keine weiteren Informationen zum ESP angegeben!', $msgs['info']);
    }

    /**
     * @test
     */
    public function validateExistingMac() {

        mkdir(DATA_DIR . '22:22:22:22:22:22', 0775);
        $fileHandle = fopen(DATA_DIR . '22:22:22:22:22:22/info.json', 'w');
        fwrite($fileHandle, '{"type":"type","info":"info"}');
        fclose($fileHandle);

        $method = new \ReflectionMethod('com\gpioneers\esp\httpupload\controllers\Device', 'validate');
        $method->setAccessible(true);

        $msgs = $method->invoke(new Device($this->ci), array('mac' => '22:22:22:22:22:22'));

        $this->assertEquals('Device mit dieser Mac-Adresse existiert bereits!', $msgs['mac']);
        $this->assertEquals('Keine ESP-Version angegeben!', $msgs['type']);
        $this->assertEquals('Keine weiteren Informationen zum ESP angegeben!', $msgs['info']);
    }

    /**
     * @test
     */
    public function validateValid() {

        $method = new \ReflectionMethod('com\gpioneers\esp\httpupload\controllers\Device', 'validate');
        $method->setAccessible(true);

        $msgs = $method->invoke(new Device($this->ci), array(
            'mac' => '22:22:22:22:22:22',
            'type' => 'type',
            'info' => 'info'
        ));

        $this->assertEmpty($msgs);
    }

}
