<?php

namespace com\gpioneers\esp\httpupload\models;

use Psr\Log\LoggerInterface;

class DeviceAuthentifications {

    /**
     * @ver LoggerInterface
     */
    protected $logger;

    /**
     * @var Devices
     */
    private $repository;

    /**
     * @var string
     */
    private $authentificationFileName = 'authentification.json';

    /**
     * constant time difference between the authentification request and the request to download a new version if available
     */
    const MAXIMAL_TIME_DIFFERENCE = 5; // 5 seconds

    public function __construct(LoggerInterface $logger) {
        $this->logger = $logger;
        $this->repository = new Devices($this->logger);
    }

    /**
     * @throws \Exception if given $staMac is invlid
     */
    public function load($staMac) {

        $device = $this->repository->load($staMac);
        if ($device->isExisting() && $device->isValid() && is_file($this->getDeviceAuthentificationPath($device))) {

            $deviceAuthentificationFileHandle = fopen($this->getDeviceAuthentificationPath($device), 'r');
            if ($deviceAuthentificationFileHandle === false) {
                // @codeCoverageIgnoreStart
                // not testable; file needs to be changed externaly to come into this state
                throw new \Exception('Can not open deviceAuthentificationFile: ' . $this->getDeviceAuthentificationPath($device));
                // @codeCoverageIgnoreEnd
            }

            $deviceAuthentification = new DeviceAuthentification($device, $this->logger);

            $deviceAuthentificationFileJson = json_decode(
                fread(
                    $deviceAuthentificationFileHandle,
                    filesize($this->getDeviceAuthentificationPath($deviceAuthentification->getDevice()))
                )
            );
            $deviceAuthentification->setApMac($deviceAuthentificationFileJson->apMac);
            $deviceAuthentification->setChipSize($deviceAuthentificationFileJson->chipSize);
            $deviceAuthentification->setTimestamp($deviceAuthentificationFileJson->timestamp);

            return $deviceAuthentification;
        }

        return null;
    }

    public function save(DeviceAuthentification $deviceAuthentification) {
        $json = $this->getDeviceAuthentificationAsJson($deviceAuthentification);

        $deviceAuthentificationFileHandle = fopen($this->getDeviceAuthentificationPath($deviceAuthentification->getDevice()), 'w');

        if ($deviceAuthentificationFileHandle === false) {
            // @codeCoverageIgnoreStart
            // not testable; file needs to be changed externaly to come into this state
            throw new \Exception('Can not open deviceAuthentificationFile: ' . $this->getDeviceAuthentificationPath($deviceAuthentification->getDevice()));
            // @codeCoverageIgnoreEnd
        }

        $writtenBytes = fwrite($deviceAuthentificationFileHandle, $this->getDeviceAuthentificationAsJson($deviceAuthentification));
        $success = $writtenBytes > 0 && fclose($deviceAuthentificationFileHandle);

        return $success;
    }

    /**
     * @return boolean
     */
    public function authenticate(DeviceAuthentification $deviceAuthentification, $headerInfos) {

        return (
            $deviceAuthentification->getTimestamp() >= time() - self::MAXIMAL_TIME_DIFFERENCE &&
            $deviceAuthentification->getStaMac() === $headerInfos['staMac'] &&
            $deviceAuthentification->getApMac() === $headerInfos['apMac'] &&
            $deviceAuthentification->getChipSize() === $headerInfos['chipSize']
        );
    }

    public function getDeviceAuthentificationAsJson(DeviceAuthentification $deviceAuthentification) {
        return json_encode(
            array(
                'staMac' => $deviceAuthentification->getStaMac(),
                'apMac' => $deviceAuthentification->getApMac(),
                'chipSize' => $deviceAuthentification->getChipSize(),
                'timestamp' => $deviceAuthentification->getTimestamp()
            )
        );
    }

    public function getDeviceAuthentificationPath(Device $device) {
        return $this->repository->getDeviceDirectoryPath($device) . $this->authentificationFileName;
    }

}
