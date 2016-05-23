<?php

namespace com\gpioneers\esp\httpupload\models;

use \Psr\Log\LoggerInterface;

class Device {

    protected $logger;

    /**
     * @var string
     * @format 14:14:14:14:14
     */
    private $mac;
    /**
     * @var string
     */
    private $type;
    /**
     * @var string
     */
    private $info;

    /**
     * 1:n relation device (this) : (versions (DeviceVersion)
     * @var DeviceVersion[] array
     */
    private $versions = array();

    /**
     * @var boolean
     */
    private $exists = false;
    /**
     * @var boolean
     */
    private $valid = false;


    public function __construct($mac, LoggerInterface $logger) {
        $this->logger = $logger;
        $this->setMac($mac);
    }


    public function getMac() {
    	return $this->mac;
    }
    public function setMac($mac) {
    	$this->mac = $mac;
    }

    public function getType() {
        return $this->type;
    }
    public function setType($type) {
        $this->type = $type;
    }

	public function getInfo() {
    	return $this->info;
    }
    public function setInfo($info) {
    	$this->info = $info;
    }

	public function getVersions() {
    	return $this->versions;
    }
    public function addVersion($version) {
        if (!in_array($version, $this->versions)) {
    	   $this->versions[] = $version;
        } else {
            $this->logger->addWarning('Attempt to add version to device already knowing this version.');
        }
    }
    public function setVersions($versions) {
        $this->versions = array_values(array_filter($versions, function ($version) {
            return $version instanceof DeviceVersion;
        }));
    }

    public function isExisting() {
        return $this->exists;
    }
    public function setExisting($exists) {
        $this->exists = $exists;
    }

    public function isValid() {
        return $this->valid;
    }
    public function setValid($valid) {
        $this->valid = $valid;
    }
}
