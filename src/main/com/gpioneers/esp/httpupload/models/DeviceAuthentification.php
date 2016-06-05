<?php

namespace com\gpioneers\esp\httpupload\models;

use \Psr\Log\LoggerInterface;
use \com\gpioneers\esp\httpupload\models\Device;

class DeviceAuthentification {

    private $logger;

    /**
     * @var Device
     */
    private $device;
    /**
     * @var string
     */
    private $apMac;
    /**
     * @var number
     */
    private $chipSize;
    /**
     * @var number unix-timestamp
     */
    private $timestamp;

    /**
     * DeviceAuthentification constructor.
     * @param \com\gpioneers\esp\httpupload\models\Device $device
     * @param LoggerInterface $logger
     */
    public function __construct(Device $device, LoggerInterface $logger) {
        $this->device = $device;
        $this->logger = $logger;
    }

    /**
     * @return string
     */
    public function getStaMac() {
        return $this->device->getMac();
    }

    /**
     * @return \com\gpioneers\esp\httpupload\models\Device
     */
    public function getDevice() {
        return $this->device;
    }

    /**
     * @return string
     */
    public function getApMac() {
        return $this->apMac;
    }
    /**
     * @param string $apMac
     */
    public function setApMac($apMac) {
        $this->apMac = $apMac;
    }

    /**
     * @return number
     */
    public function getChipSize() {
        return $this->chipSize;
    }
    /**
     * @param number $chipSize
     */
    public function setChipSize($chipSize) {
        $this->chipSize = $chipSize;
    }

    /**
     * @return number
     */
    public function getTimestamp() {
        return $this->timestamp;
    }
    /**
     * @param number $timestamp
     */
    public function setTimestamp($timestamp) {
        $this->timestamp = $timestamp;
    }
}
