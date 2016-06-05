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
    private $deviceRepository;

    /**
     * @var string
     */
    private $authentificationFileName = 'authentification.json';

    /**
     * constant time difference between the authentification request and the request to download a new version if available
     *
     * @var int contant
     */
    const MAXIMAL_TIME_DIFFERENCE = 23; // 23 seconds. Why "23"? Well, it's a marvelous number ;)

    /**
     * DeviceAuthentifications constructor.
     * @param Devices $deviceRepository
     * @param LoggerInterface $logger
     */
    public function __construct(Devices $deviceRepository, LoggerInterface $logger) {
        $this->logger = $logger;
        $this->deviceRepository = $deviceRepository;
    }

    /**
     * @param Device $device
     * @return DeviceAuthentification|null if provided $staMac is not known
     * @throws \Exception if given $staMac is invlid
     * @throws \Exception if given $staMac authentificationFile is not accessible
     */
    public function load(Device $device) {

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

    /**
     * @param DeviceAuthentification $deviceAuthentification
     * @return bool
     * @throws \Exception
     */
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
     * @param DeviceAuthentification $deviceAuthentification
     * @throws \Exception
     */
    public function delete(DeviceAuthentification $deviceAuthentification) {
        if (is_file($this->getDeviceAuthentificationPath($deviceAuthentification->getDevice()))) {
            if (@unlink($this->getDeviceAuthentificationPath($deviceAuthentification->getDevice()))) {
                $this->logger->addInfo('Deleted authentification-file of device with mac: ' . $deviceAuthentification->getDevice()->getMac());
          } else {
              // bubble up error
              throw new \Exception('Failed deleting authentification-file of device with mac: ' . $deviceAuthentification->getDevice()->getMac());
          }
        }
    }

    /**
     * @param DeviceAuthentification $deviceAuthentification
     * @param $headerInfos
     * @return bool
     */
    public function authenticate(DeviceAuthentification $deviceAuthentification, $headerInfos) {

        return (
            $deviceAuthentification->getTimestamp() >= time() - self::MAXIMAL_TIME_DIFFERENCE &&
            $deviceAuthentification->getStaMac() === $headerInfos['staMac'] &&
            $deviceAuthentification->getApMac() === $headerInfos['apMac'] &&
            $deviceAuthentification->getChipSize() === $headerInfos['chipSize']
        );
    }

    /**
     * @param DeviceAuthentification $deviceAuthentification
     * @return string
     */
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

    /**
     * @param Device $device
     * @return string
     */
    public function getDeviceAuthentificationPath(Device $device) {
        return $this->deviceRepository->getDeviceDirectoryPath($device) . $this->authentificationFileName;
    }

}
