<?php

namespace com\gpioneers\esp\httpupload\models;

use \Psr\Log\LoggerInterface;

class Device {

    /**
     * @var LoggerInterface
     */
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
     * 1:! relation device (this) : (DeviceAuthentification)
     * @var DeviceAuthentification
     */
    private $authentification;

    /**
     * @var boolean
     */
    private $exists = false;
    /**
     * @var boolean
     */
    private $valid = false;

    /**
     * Device constructor.
     * @param string $mac
     * @param LoggerInterface $logger
     */
    public function __construct($mac, LoggerInterface $logger) {
        $this->logger = $logger;
        $this->setMac($mac);
    }

    /**
     * @return string
     */
    public function getMac() {
    	return $this->mac;
    }
    /**
     * @param string $mac
     */
    public function setMac($mac) {
    	$this->mac = $mac;
    }

    /**
     * @return string
     */
    public function getType() {
        return $this->type;
    }
    /**
     * @param string $type
     */
    public function setType($type) {
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getInfo() {
    	return $this->info;
    }
    /**
     * @param string $info
     */
    public function setInfo($info) {
    	$this->info = $info;
    }

    /**
     * @return DeviceVersion[]
     */
    public function getVersions() {
    	return $this->versions;
    }
    /**
     * @param DeviceVersion $version
     */
    public function addVersion(DeviceVersion $version) {
        if (!in_array($version, $this->versions)) {
    	   $this->versions[] = $version;
        } else {
            $this->logger->addWarning('Attempt to add version to device already knowing this version.');
        }
    }
    /**
     * @param DeviceVersion[] $versions
     */
    public function setVersions($versions) {
        $this->versions = array_values(array_filter($versions, function ($version) {
            return $version instanceof DeviceVersion;
        }));
    }
    /**
     * @param string $version
     * @return DeviceVersion|null
     * @throws \Exception
     */
    public function getVersion($version) {
        $fittingVersions = array_values(array_filter($this->versions, function (DeviceVersion $v) use ($version) {
            return $v->getVersion() === $version;
        }));
        if (count($fittingVersions) > 1) {
            throw new \Exception('Found more than one fitting version for "' . $version . '"! Something really went totaly wrong!');
        }
        return empty($fittingVersions) ? null : $fittingVersions[0];
    }

    /**
     * @param DeviceAuthentification $authentification
     */
    public function setAuthentification(DeviceAuthentification $authentification) {
        $this->authentification = $authentification;
    }
    /**
     * @return DeviceAuthentification
     */
    public function getAuthentification() {
        return $this->authentification;
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
