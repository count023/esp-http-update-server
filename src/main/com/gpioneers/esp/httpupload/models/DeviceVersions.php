<?php

namespace com\gpioneers\esp\httpupload\models;

use Psr\Log\LoggerInterface;
use Psr\Http\Message\UploadedFileInterface;

class DeviceVersions {

    protected $logger;

    /**
     * @var string
     */
    private $infoFileName = 'info.json';
    /**
     * @var string
     */
    private $imageFileName = 'image.bin';

    public function __construct(LoggerInterface $logger) {
    	$this->logger = $logger;
    }

    public function getAll(Device $device) {

        $deviceVersions = array();

        // load versions from basePath
        $basePathContent = scandir($this->getDeviceDirectoryPath($device));
        if ($basePathContent !== false) {
            $versionDirs = array_filter(
                $basePathContent,
                function ($path) use ($device) {
                    return $path !== '.' && $path !== '..' && !is_file($this->getDeviceDirectoryPath($device) . $path) && $this->isValidVersion($path);
                }
            );
            foreach ($versionDirs as $version) {
                $deviceVersion = $this->load($device, $version);
                if ($deviceVersion->isExisting() && $deviceVersion->isValid()) {
                    $deviceVersions[] = $deviceVersion;
                }
            }
        }
        return $deviceVersions;
    }   


    public function save(Device $device, DeviceVersion $deviceVersion, UploadedFileInterface $uploadedFile) {

        if (!is_dir($this->getDeviceVersionDirectoryPath($device, $deviceVersion))) {
            mkdir($this->getDeviceVersionDirectoryPath($device, $deviceVersion));
        }

        $deviceVersionInfoFileHandle = fopen($this->getDeviceVersionInfoPath($device, $deviceVersion), 'w');
        if ($deviceVersionInfoFileHandle === false) {
            // @codeCoverageIgnoreStart 
            // not testable; file needs to be changed externaly to come into this state
            throw new \Exception('Can not open deviceVersionInfoFile: ' . $this->getDeviceVersionInfoPath($device, $deviceVersion));
            // @codeCoverageIgnoreEnd 
            
        }
        $writtenBytes = fwrite($deviceVersionInfoFileHandle, $this->getDeviceVersionInfoAsJson($deviceVersion));

        if ($uploadedFile->getError() === UPLOAD_ERR_OK) {
            $uploadedFile->moveTo($this->getDeviceVersionImagePath($device, $deviceVersion));
        } else {
            throw new \Exception('Uploaded file has error: ' . $uploadedFile->getError());
        }

        return $writtenBytes > 0 && fclose($deviceVersionInfoFileHandle);
    }

    public function update(Device $device, DeviceVersion $currentDeviceVersion, DeviceVersion $newDeviceVersion, UploadedFileInterface $uploadedFile) {
        
        // @TODO: rename() is not working for not empty directories ... try call system ... 
        if ($currentDeviceVersion->getVersion() !== $newDeviceVersion->getVersion()) {

            $oldPath = str_replace(':', '\:', $this->getDeviceVersionDirectoryPath($device, $currentDeviceVersion));
            $newPath = str_replace(':', '\:', $this->getDeviceVersionDirectoryPath($device, $newDeviceVersion));

            $this->logger->addInfo('About to move deviceVersion directory from ' . $oldPath . ' to ' . $newPath);
            echo 'oldDir: ' . $oldPath . ' newDir: ' . $newPath . '<br>';
            $systemResponse = system('mv ' . $oldPath . ' ' . $newPath);
            var_dump($systemResponse);
        }

        if ($uploadedFile !== null && is_file($this->getDeviceVersionImagePath($device, $newDeviceVersion))) {
            // if a new binary file is provided, simply delete the old one to be able to move the new one at same location
            if (!unlink($this->getDeviceVersionImagePath($device, $newDeviceVersion))) {
                // bubble up error
                // @codeCoverageIgnoreStart 
                // not testable; file needs to be changed externaly to come into this state
                throw new \Exception('Failed deleting image-file of version ' . $newDeviceVersion->getVersion() . ' of device with mac: ' . $device->getMac());
                // @codeCoverageIgnoreEnd 
            }
        }

        return $this->save($device, $newDeviceVersion, $uploadedFile);
    }

    /*
     * @param Device $device
     * @param string $version
     * @return DeviceVersion
     */
    public function load(Device $device, $version) {

        if (!$device->isExisting() || !$device->isValid()) {
            throw new \Exception('Invalid device given to load! (isExisting: ' . ($device->isExisting() ? 'true' : 'false') . ' isValid: ' . ($device->isValid() ? 'true' : 'false') . ')');
        }

        if (!$this->isValidVersion($version)) {
            throw new \Exception('Invalid version given to load!');
        }

        $deviceVersion = new DeviceVersion($version, $this->logger);

        // populate the deviceVersion...
        if (is_dir($this->getDeviceVersionDirectoryPath($device, $deviceVersion))) {
            // load deviceInfo from basePath
            if (is_file($this->getDeviceVersionInfoPath($device, $deviceVersion))) {

                $infoFileHandle = fopen($this->getDeviceVersionInfoPath($device, $deviceVersion), 'r');
                if ($infoFileHandle !== false) {
                    $infoFileJson = json_decode(
                        fread(
                            $infoFileHandle,
                            filesize($this->getDeviceVersionInfoPath($device, $deviceVersion))
                        )
                    );
                    $deviceVersion->setSoftwareName($infoFileJson->softwareName);
                    $deviceVersion->setDescription($infoFileJson->description);
                }
                if (is_file($this->getDeviceVersionImagePath($device, $deviceVersion))) {
                    $deviceVersion->setValid(true);
                }
            }

            $deviceVersion->setExisting(true);
        }

        return $deviceVersion;
    }

    public function validate($formData) {

    	$msgs = array();

        // formData contains valid mac
        if (empty($formData['mac'])) {
            $msgs['mac'] = 'Keine Mac-Adresse angegeben!';
        } else {
            $devicesRepository = new Devices($this->logger);
            if (!$devicesRepository->isValidMac($formData['mac'])) {
                $msgs['mac'] = 'Ungültige Mac-Adresse angegeben!';
            }
        }
        // postData contains valid version
        if (empty($formData['version'])) {
            $msgs['version'] = 'Keine Version angegeben!';
        } else if (!$this->isValidVersion($formData['version'])) {
            $msgs['version'] = 'Ungültige Version angegeben!';
        } else if (empty($msgs['mac'])) {
            $devicesRepository = new Devices($this->logger);
            $device = $devicesRepository->load($formData['mac']);
            if ($this->load($device, $formData['version'])->isExisting()) {
                $msgs['version'] = 'Diese Version existiert bereits!';
            }
        }
        // postData contains any software name
        if (empty($formData['softwareName'])) {
            $msgs['softwareName'] = 'Keinen Software-Namen angegeben!';
        }
        // postData contains any description
        if (empty($formData['description'])) {
            $msgs['description'] = 'Keine Beschreibung angegeben!';
        }
        // postData contains a binary image
        if (
            !array_key_exists('files', $formData) ||
            !array_key_exists('file', $formData['files']) ||
            $formData['files']['file']->getSize() <= 0
        ) {
            $msgs['file'] = 'Keine Datei gesendet!';
        }

        $this->logger->addDebug(__METHOD__ . ' ' . print_r($msgs, true));

        return $msgs;
    }

    public function delete(Device $device, DeviceVersion $deviceVersion) {

        if (@unlink($this->getDeviceVersionInfoPath($device, $deviceVersion))) {

            $this->logger->addInfo('Deleted info-file of version ' . $deviceVersion->getVersion() . ' of device with mac: ' . $device->getMac());

            if (@unlink($this->getDeviceVersionImagePath($device, $deviceVersion))) {

                $this->logger->addInfo('Deleted image-file of version ' . $deviceVersion->getVersion() . ' of device with mac: ' . $device->getMac());

                if (@rmdir($this->getDeviceVersionDirectoryPath($device, $deviceVersion))) {
                    $this->logger->addInfo('Deleted directory of version ' . $deviceVersion->getVersion() . ' of device with mac: ' . $device->getMac());
                }
                // @codeCoverageIgnoreStart 
                // not testable; file needs to be changed externaly to come into this state
                else {
                    // bubble up error
                    throw new \Exception('Failed deleting directory of version ' . $deviceVersion->getVersion() . ' of device with mac: ' . $device->getMac());
                }
                // @codeCoverageIgnoreEnd 
            } else {
                // bubble up error
                throw new \Exception('Failed deleting image-file of version ' . $deviceVersion->getVersion() . ' of device with mac: ' . $device->getMac());
            }
        } else {
            // bubble up error
            throw new \Exception('Failed deleting info-file of version ' . $deviceVersion->getVersion() . ' of device with mac: ' . $device->getMac());
        } 
    }

    public function isValidVersion($version) {
        return preg_match('/^[0-9]{1,2}\.[0-9]{1,3}(\.[0-9]{1,3})?$/', $version) === 1;
    }

    public function getDeviceVersionInfoAsJson(DeviceVersion $deviceVersion) {
        return json_encode(array(
            'softwareName' => $deviceVersion->getSoftwareName(),
            'description' => $deviceVersion->getDescription()
        ));
    }

    public function getDeviceVersionInfoPath(Device $device, DeviceVersion $deviceVersion) {
        if (empty($deviceVersion->getVersion())) {
            $this->logger->addError('Access to empty $version of ' . get_class($deviceVersion) . '. Probably using not fully initialized ' . get_class($deviceVersion) . '?');
            throw new \Exception('Access to empty $version of ' . get_class($deviceVersion) . '. Probably using not fully initialized ' . get_class($deviceVersion) . '?');
        }
        return $this->getDeviceVersionDirectoryPath($device, $deviceVersion) . $this->infoFileName;
    }

    public function getDeviceVersionImagePath(Device $device, DeviceVersion $deviceVersion) {
        if (empty($deviceVersion->getVersion())) {
            $this->logger->addError('Access to empty $version of ' . get_class($deviceVersion) . '. Probably using not fully initialized ' . get_class($deviceVersion) . '?');
            throw new \Exception('Access to empty $version of ' . get_class($deviceVersion) . '. Probably using not fully initialized ' . get_class($deviceVersion) . '?');
        }
        return $this->getDeviceVersionDirectoryPath($device, $deviceVersion) . $this->imageFileName;
    }

    public function getDeviceVersionDirectoryPath(Device $device, DeviceVersion $deviceVersion) {
        return $this->getDeviceDirectoryPath($device) . $deviceVersion->getVersion() . DIRECTORY_SEPARATOR;
    }

    public function getDeviceDirectoryPath(Device $device) {
        return DATA_DIR . $device->getMac() . DIRECTORY_SEPARATOR;
    }

}