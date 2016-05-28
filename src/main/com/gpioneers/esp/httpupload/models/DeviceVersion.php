<?php

namespace com\gpioneers\esp\httpupload\models;

use Psr\Log\LoggerInterface;

class DeviceVersion {

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


    public function __construct($version, LoggerInterface $logger) {
        $this->version = $version;
        $this->logger = $logger;
    }

    public function getVersion() {
    	return $this->version;
    }
    /**
     * @codeCoverageIgnore
     * currently not used anywehere! ...
     */
    public function setVersion($version) {
    	$this->version = $version;
    }

    public function getSoftwareName() {
    	return $this->softwareName;
    }
    public function setSoftwareName($softwareName) {
    	$this->softwareName = $softwareName;
    }

    public function getDescription() {
    	return $this->description;
    }
    public function setDescription($description) {
    	$this->description = $description;
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
