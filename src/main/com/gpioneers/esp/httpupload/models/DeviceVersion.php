<?php

namespace com\gpioneers\esp\httpupload\models;

use Psr\Log\LoggerInterface;

class DeviceVersion {

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var string
     * @format n.n[.n]
     */
    private $version;
    /**
     * @var string
     */
    private $softwareName;
    /**
     * @var string
     */
    private $description;

    /**
     * @var boolean
     */
    private $exists = false;
    /**
     * @var boolean
     */
    private $valid = false;

    /**
     * DeviceVersion constructor.
     * @param string $version
     * @param LoggerInterface $logger
     */
    public function __construct($version, LoggerInterface $logger) {
        $this->version = $version;
        $this->logger = $logger;
    }

    /**
     * @return string
     */
    public function getVersion() {
    	return $this->version;
    }
    /**
     * @param string $version
     */
    public function setVersion($version) {
    	$this->version = $version;
    }

    /**
     * @return string
     */
    public function getSoftwareName() {
    	return $this->softwareName;
    }
    /**
     * @param string $softwareName
     */
    public function setSoftwareName($softwareName) {
    	$this->softwareName = $softwareName;
    }

    /**
     * @return string
     */
    public function getDescription() {
    	return $this->description;
    }
    /**
     * @param string $description
     */
    public function setDescription($description) {
    	$this->description = $description;
    }

    /**
     * @return bool
     */
    public function isExisting() {
        return $this->exists;
    }
    /**
     * @param bool $exists
     */
    public function setExisting($exists) {
        $this->exists = $exists;
    }

    /**
     * @return bool
     */
    public function isValid() {
        return $this->valid;
    }
    /**
     * @param bool $valid
     */
    public function setValid($valid) {
        $this->valid = $valid;
    }
}
