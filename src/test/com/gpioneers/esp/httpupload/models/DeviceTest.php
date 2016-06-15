<?php

namespace com\gpioneers\esp\httpupload\models;

use com\gpioneers\esp\httpupload\models\Device;
use com\gpioneers\esp\httpupload\models\DeviceVersion;

class DeviceTest extends \PHPUnit_Framework_TestCase {

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
	 * @test
	 */
	public function setVersions() {

		$deviceVersion1 = new DeviceVersion('1.0', $this->logger);
		$deviceVersion2 = new DeviceVersion('2.0', $this->logger);
		$deviceVersion3 = '3.0';
		$deviceVersion4 = new DeviceVersion('4.0', $this->logger);

		$deviceVersions = array($deviceVersion1, $deviceVersion2, $deviceVersion3, $deviceVersion4);

		$device = new Device('12:34:56:78:90:12', $this->logger);

		$device->setVersions($deviceVersions);

		$this->assertEquals($deviceVersion1, $device->getVersions()[0]);
		$this->assertEquals($deviceVersion2, $device->getVersions()[1]);
		$this->assertEquals($deviceVersion4, $device->getVersions()[2]);

	}

	/**
	 * @test
	 */
	public function addVersion() {
		$deviceVersion1 = new DeviceVersion('1.0', $this->logger);
		$deviceVersion2 = new DeviceVersion('2.0', $this->logger);
		$deviceVersion4 = new DeviceVersion('4.0', $this->logger);

		$device = new Device('12:34:56:78:90:12', $this->logger);

		$device->addVersion($deviceVersion1);
		$device->addVersion($deviceVersion2);
		$device->addVersion($deviceVersion4);

		$device->addVersion($deviceVersion2);

		$this->assertEquals(3, count($device->getVersions()));
		$this->assertEquals(
			'Attempt to add version to device already knowing this version.',
			$this->logger->getHandlers()[0]->getRecords()[0]['message']
		);
		$this->assertEquals(
			'WARNING',
			$this->logger->getHandlers()[0]->getRecords()[0]['level_name']
		);

	}

}
