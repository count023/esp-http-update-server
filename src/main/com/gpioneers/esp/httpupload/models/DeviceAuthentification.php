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

    public function __construct(Device $device, LoggerInterface $logger) {
        $this->device = $device;
        $this->logger = $logger;
    }

    public function getStaMac() {
        return $this->device->getMac();
    }

    public function getDevice() {
        return $this->device;
    }

    public function getApMac() {
        return $this->apMac;
    }
    public function setApMac($apMac) {
        $this->apMac = $apMac;
    }

    public function getChipSize() {
        return $this->chipSize;
    }
    public function setChipSize($chipSize) {
        $this->chipSize = $chipSize;
    }

    public function getTimestamp() {
        return $this->timestamp;
    }
    public function setTimestamp($timestamp) {
        $this->timestamp = $timestamp;
    }
}
