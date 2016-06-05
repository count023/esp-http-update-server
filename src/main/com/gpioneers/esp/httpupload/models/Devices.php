<?php

namespace com\gpioneers\esp\httpupload\models;

use Psr\Log\LoggerInterface;
use com\gpioneers\esp\httpupload\exceptions\DeviceInfoFileUnwritableException;
use com\gpioneers\esp\httpupload\exceptions\DeviceInfoFileDeletionException;
use com\gpioneers\esp\httpupload\exceptions\DeviceDirectoryDeletionException;
use com\gpioneers\esp\httpupload\exceptions\DeviceNotPersistedException;
use com\gpioneers\esp\httpupload\exceptions\InvalidMacException;

class Devices {

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var DeviceVersions
     */
    protected $versionsRepository;

    /**
     * @var DeviceAuthentifications
     */
    protected $authentificationsRepository;

    /**
     * @var string
     */
    private $infoFileName = 'info.json';

    /**
     * Devices constructor.
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger) {
        $this->logger = $logger;
        $this->versionsRepository = new DeviceVersions($logger);
        $this->authentificationsRepository = new DeviceAuthentifications($this, $logger);
    }

    /**
     * @return array
     * @throws InvalidMacException
     */
    public function getAll() {

        $deviceDirs = array_filter(
            scandir(DATA_DIR),
            function ($path) {
                return $path !== '.' && $path !== '..' && !is_file(DATA_DIR . $path) && $this->isValidMac($path);
            }
        );
        $devices = array();
        foreach ($deviceDirs as $mac) {

            $device = $this->load($mac);
            if ($device->isExisting() && $device->isValid()) {
                $devices[] = $device;
            }
        }
        return $devices;
    }

    /**
     * @param Device $device
     * @return bool
     * @throws DeviceInfoFileUnwritableException
     * @throws DeviceNotPersistedException
     */
    public function save(Device $device) {
        if (!is_dir($this->getDeviceDirectoryPath($device))) {
            mkdir($this->getDeviceDirectoryPath($device));
        }
        $deviceInfoFileHandle = fopen($this->getDeviceInfoPath($device), 'w');

        if ($deviceInfoFileHandle === false) {
            // @codeCoverageIgnoreStart
            // not testable; file needs to be changed externaly to come into this state
            throw new DeviceInfoFileUnwritableException('Can not open deviceInfoFile: ' . $this->getDeviceInfoPath($device));
            // @codeCoverageIgnoreEnd
        }

        $writtenBytes = fwrite($deviceInfoFileHandle, $this->getDeviceInfoAsJson($device));
        $success = $writtenBytes > 0 && fclose($deviceInfoFileHandle);

        $device->setExisting($success);
        $device->setValid($success);

        return $success;
    }

    /**
     * @param Device $currentDevice
     * @param Device $newDevice
     * @return bool
     * @throws DeviceInfoFileUnwritableException
     */
    public function update(Device $currentDevice, Device $newDevice) {

        if ($currentDevice->getMac() !== $newDevice->getMac()) {

            $oldPath = str_replace(':', '\:', $this->getDeviceDirectoryPath($currentDevice));
            $newPath = str_replace(':', '\:', $this->getDeviceDirectoryPath($newDevice));

            $this->logger->addInfo('About to move device directory from ' . $oldPath . ' to ' . $newPath);
            $systemResponse = system('mv ' . $oldPath . ' ' . $newPath);
            $this->logger->addDebug('called mv ' . $oldPath . ' ' . $newPath . ' with response: "' . $systemResponse . '"');
        }
        return $this->save($newDevice);
    }

    /**
     * @param $mac
     * @return Device
     * @throws DeviceNotPersistedException
     * @throws InvalidMacException
     * @throws \com\gpioneers\esp\httpupload\exceptions\DeviceAuthentificationFileUnreadableException
     */
    public function load($mac) {

        $device = new Device($mac, $this->logger);

        // allow null value for mac (new device...)
        if ($mac !== null) {

            if (!$this->isValidMac($mac)) {
                throw new InvalidMacException('Invalid mac given to load!');
            }

            if (is_dir($this->getDeviceDirectoryPath($device))) {
                // load deviceInfo from basePath
                if (is_file($this->getDeviceInfoPath($device))) {

                    $descriptionFileHandle = fopen($this->getDeviceInfoPath($device), 'r');
                    if ($descriptionFileHandle !== false) {
                        $descriptionFileJson = json_decode(
                            fread(
                                $descriptionFileHandle,
                                filesize($this->getDeviceInfoPath($device))
                            )
                        );
                        $device->setType($descriptionFileJson->type);
                        $device->setInfo($descriptionFileJson->info);
                    }
                    $device->setValid(true);
                }

                $device->setExisting(true);

                $device->setVersions($this->versionsRepository->getAll($device));
                $deviceAuthentification= $this->authentificationsRepository->load($device);
                if ($deviceAuthentification !== null) {
                    $device->setAuthentification($deviceAuthentification);
                }
            }
        }

        return $device;
    }

    /**
     * @param Device $device
     * @return void
     * @throws DeviceDirectoryDeletionException
     * @throws DeviceInfoFileDeletionException
     * @throws DeviceNotPersistedException
     * @throws \com\gpioneers\esp\httpupload\exceptions\DeviceAuthentificationFileDeletionException
     * @throws \com\gpioneers\esp\httpupload\exceptions\DeviceVersionDirectoryDeletionException
     * @throws \com\gpioneers\esp\httpupload\exceptions\DeviceVersionImageFileDeletionException
     * @throws \com\gpioneers\esp\httpupload\exceptions\DeviceVersionInfoFileDeletionException
     *
     * Remark: need to call implicit functions `unlink` and `rmdir` with surpressing '@' to throw custom \Exceptions!
     */
    public function delete(Device $device) {

        foreach ($device->getVersions() as $version) {
            $this->versionsRepository->delete($device, $version);
        }

        $deviceAuthentification = $device->getAuthentification();
        if ($deviceAuthentification !== null) {
            $this->authentificationsRepository->delete($deviceAuthentification);
        }

        // unlink $device->getInfoPath()
        if (@unlink($this->getDeviceInfoPath($device))) {

            $this->logger->addInfo('Deleted info-file of device with mac: ' . $device->getMac());

        } else {
            // bubble up error
            throw new DeviceInfoFileDeletionException('Failed deleting info-file of device with mac: ' . $device->getMac());
        }
        // rmdir $device->getDirectoryPath()
        if (@rmdir($this->getDeviceDirectoryPath($device))) {

            $this->logger->addInfo('Deleted directory of device with mac: ' . $device->getMac());

        }
        // @codeCoverageIgnoreStart
        // not testable; file needs to be changed externaly to come into this state
        else {
            // bubble up error
            throw new DeviceDirectoryDeletionException('Failed deleting directory of device with mac: ' . $device->getMac());
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * @return boolean
     */
    public function isValidMac($mac) {
        return preg_match('/^([0-9A-F]{2}:){5}([0-9A-F]{2})$/i', $mac) === 1;
    }

    /**
     * @param Device $device
     * @return string
     */
    public function getDeviceInfoAsJson(Device $device) {
        return json_encode(array(
            'type' => $device->getType(),
            'info' => $device->getInfo()
        ));
    }

    /**
     * @param Device $device
     * @return string
     * @throws DeviceNotPersistedException
     */
    public function getDeviceInfoPath(Device $device) {
        if (empty($device->getMac())) {
            $this->logger->addError('Access to empty $mac of ' . get_class($device) . '. Probably using not fully initialized ' . get_class($device) . '?');
            throw new DeviceNotPersistedException('Access to empty $mac of ' . get_class($device) . '. Probably using not fully initialized ' . get_class($device) . '?');
        }
        return $this->getDeviceDirectoryPath($device) . $this->infoFileName;
    }

    /**
     * @param Device $device
     * @return string
     */
    public function getDeviceDirectoryPath(Device $device) {
        return DATA_DIR . $device->getMac() . DIRECTORY_SEPARATOR;
    }

    /**
     * @param Device $device
     * @return DeviceVersion
     */
    public function getHighestVersion(Device $device) {
        $versions = $device->getVersions();
        usort($versions, function ($deviceVersionA, $deviceVersionB) {
            return strcmp($deviceVersionA->getVersion(), $deviceVersionB->getVersion());
        });
        $highestVersion = array_pop(array_values($versions));
        return $highestVersion;
    }
}
