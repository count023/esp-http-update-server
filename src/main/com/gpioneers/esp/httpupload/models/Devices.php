<?php

namespace com\gpioneers\esp\httpupload\models;

use Psr\Log\LoggerInterface;

class Devices {

    private $logger;

    protected $subRepository;

    /**
     * @var string
     */
    private $infoFileName = 'info.json';

    public function __construct(LoggerInterface $logger) {
        $this->logger = $logger;
        $this->subRepository = new DeviceVersions($logger);
    }

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

    public function save(Device $device) {
        if (!is_dir($this->getDeviceDirectoryPath($device))) {
        mkdir($this->getDeviceDirectoryPath($device));
    	}
    	$deviceInfoFileHandle = fopen($this->getDeviceInfoPath($device), 'w');

    	if ($deviceInfoFileHandle === false) {
            // @codeCoverageIgnoreStart
            // not testable; file needs to be changed externaly to come into this state
            throw new \Exception('Can not open deviceInfoFile: ' . $this->getDeviceInfoPath($device));
            // @codeCoverageIgnoreEnd
    	}

        $writtenBytes = fwrite($deviceInfoFileHandle, $this->getDeviceInfoAsJson($device));
        $success = $writtenBytes > 0 && fclose($deviceInfoFileHandle);

        $device->setExisting($success);
        $device->setValid($success);

        return $success;
    }

    public function update(Device $currentDevice, Device $newDevice) {

        // @TODO: rename() is not working for not empty directories ... try call system ...
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
     * @return Device
     */
    public function load($mac) {

        $device = new Device($mac, $this->logger);

        // allow null value for mac (new device...)
        if ($mac !== null) {

            if (!$this->isValidMac($mac)) {
                throw new \Exception('Invalid mac given to load!');
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

                $device->setVersions($this->subRepository->getAll($device));
            }
        }

        return $device;
    }

    /**
     * @return void
     * @throws \Exception if info file or base-folder do not exist
     *
     * Remark: need to call implicit functions `unlink` and `rmdir` with surpressing '@' to throw custom \Exceptions!
     * @TODO: the thrown \Exception, in case of directory does not exist, is a bit misleading
     */
    public function delete(Device $device) {

        foreach ($device->getVersions() as $version) {
            $this->subRepository->delete($device, $version);
        }

        // unlink $device->getInfoPath()
        if (@unlink($this->getDeviceInfoPath($device))) {

            $this->logger->addInfo('Deleted info-file of device with mac: ' . $device->getMac());

            // rmdir $device->getDirectoryPath()
            if (@rmdir($this->getDeviceDirectoryPath($device))) {

                $this->logger->addInfo('Deleted directory of device with mac: ' . $device->getMac());

            }
            // @codeCoverageIgnoreStart
            // not testable; file needs to be changed externaly to come into this state
            else {
                // bubble up error
                throw new \Exception('Failed deleting directory of device with mac: ' . $device->getMac());
            }
            // @codeCoverageIgnoreEnd
        } else {
            // bubble up error
            throw new \Exception('Failed deleting info-file of device with mac: ' . $device->getMac());
        }
    }

    /**
     * @return boolean
     */
    public function isValidMac($mac) {
        return preg_match('/^([0-9A-F]{2}:){5}([0-9A-F]{2})$/i', $mac) === 1;
    }

    public function getDeviceInfoAsJson(Device $device) {
        return json_encode(array(
            'type' => $device->getType(),
            'info' => $device->getInfo()
        ));
    }

    public function getDeviceInfoPath(Device $device) {
        if (empty($device->getMac())) {
            $this->logger->addError('Access to empty $mac of ' . get_class($device) . '. Probably using not fully initialized ' . get_class($device) . '?');
            throw new \Exception('Access to empty $mac of ' . get_class($device) . '. Probably using not fully initialized ' . get_class($device) . '?');
        }
        return $this->getDeviceDirectoryPath($device) . $this->infoFileName;
    }

    public function getDeviceDirectoryPath(Device $device) {
        return DATA_DIR . $device->getMac() . DIRECTORY_SEPARATOR;
    }

}
