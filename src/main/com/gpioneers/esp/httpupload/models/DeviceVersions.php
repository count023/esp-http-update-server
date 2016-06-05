<?php

namespace com\gpioneers\esp\httpupload\models;

use Psr\Log\LoggerInterface;
use Psr\Http\Message\UploadedFileInterface;

use com\gpioneers\esp\httpupload\exceptions\DeviceNotExistsException;
use com\gpioneers\esp\httpupload\exceptions\DeviceInvalidException;
use com\gpioneers\esp\httpupload\exceptions\UploadedFileErrorException;
use com\gpioneers\esp\httpupload\exceptions\DeviceVersionInfoFileUnwritableException;
use com\gpioneers\esp\httpupload\exceptions\DeviceVersionImageFileDeletionException;
use com\gpioneers\esp\httpupload\exceptions\DeviceVersionInfoFileDeletionException;
use com\gpioneers\esp\httpupload\exceptions\DeviceVersionDirectoryDeletionException;
use com\gpioneers\esp\httpupload\exceptions\InvalidVersionException;
use com\gpioneers\esp\httpupload\exceptions\DeviceNotPersistedException;
use com\gpioneers\esp\httpupload\exceptions\DeviceVersionNotPersistedException;


class DeviceVersions {

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var string
     */
    private $infoFileName = 'info.json';
    /**
     * @var string
     */
    private $imageFileName = 'image.bin';

    /**
     * DeviceVersions constructor.
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger) {
        $this->logger = $logger;
    }

    /**
     * @param Device $device
     * @return array DeviceVersion[]
     * @throws DeviceInvalidException
     * @throws DeviceNotExistsException
     * @throws InvalidVersionException
     */
    public function getAll(Device $device) {

        $deviceVersions = array();

        // load versions from basePath
        if (is_readable($this->getDeviceDirectoryPath($device))) {
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
        }
        return $deviceVersions;
    }

    /**
     * @param Device $device
     * @param DeviceVersion $deviceVersion
     * @param UploadedFileInterface $uploadedFile
     * @return bool
     * @throws DeviceNotPersistedException
     * @throws DeviceVersionInfoFileUnwritableException
     * @throws DeviceVersionNotPersistedException
     * @throws UploadedFileErrorException
     */
    public function save(Device $device, DeviceVersion $deviceVersion, UploadedFileInterface $uploadedFile) {

        if (!is_dir($this->getDeviceVersionDirectoryPath($device, $deviceVersion))) {
            mkdir($this->getDeviceVersionDirectoryPath($device, $deviceVersion));
        }

        $deviceVersionInfoFileHandle = fopen($this->getDeviceVersionInfoPath($device, $deviceVersion), 'w');
        if ($deviceVersionInfoFileHandle === false) {
            // @codeCoverageIgnoreStart
            // not testable; file needs to be changed externaly to come into this state
            throw new DeviceVersionInfoFileUnwritableException('Can not open deviceVersionInfoFile: ' . $this->getDeviceVersionInfoPath($device, $deviceVersion));
            // @codeCoverageIgnoreEnd

        }
        $writtenBytes = fwrite($deviceVersionInfoFileHandle, $this->getDeviceVersionInfoAsJson($deviceVersion));

        if ($uploadedFile !== null && $uploadedFile->getSize() >= 1) { // on update the uploaded file may be null ...
            if ($uploadedFile->getError() === UPLOAD_ERR_OK) {
                $uploadedFile->moveTo($this->getDeviceVersionImagePath($device, $deviceVersion));
            } else {
                throw new UploadedFileErrorException('Uploaded file has error: ' . $uploadedFile->getError());
            }
        }

        return $writtenBytes > 0 && fclose($deviceVersionInfoFileHandle);
    }

    /**
     * @param Device $device
     * @param DeviceVersion $currentDeviceVersion
     * @param DeviceVersion $newDeviceVersion
     * @param UploadedFileInterface $uploadedFile
     * @return bool
     * @throws DeviceNotPersistedException
     * @throws DeviceVersionImageFileDeletionException
     * @throws DeviceVersionInfoFileUnwritableException
     * @throws DeviceVersionNotPersistedException
     * @throws UploadedFileErrorException
     *
     * @TODO: in case moving the version directory check wether the target already exists!
     */
    public function update(Device $device, DeviceVersion $currentDeviceVersion, DeviceVersion $newDeviceVersion, UploadedFileInterface $uploadedFile) {

        if ($currentDeviceVersion->getVersion() !== $newDeviceVersion->getVersion()) {

            $oldPath = str_replace(':', '\:', $this->getDeviceVersionDirectoryPath($device, $currentDeviceVersion));
            $newPath = str_replace(':', '\:', $this->getDeviceVersionDirectoryPath($device, $newDeviceVersion));

            $this->logger->addInfo('About to move deviceVersion directory from ' . $oldPath . ' to ' . $newPath);
            $systemResponse = system('mv ' . $oldPath . ' ' . $newPath);
            $this->logger->addDebug('Moved deviceVersion directory from ' . $oldPath . ' to ' . $newPath . 'with systemResponse: ' . $systemResponse);
        }

        if ($uploadedFile !== null && $uploadedFile->getSize() >= 1 && is_file($this->getDeviceVersionImagePath($device, $newDeviceVersion))) {
            // if a new binary file is provided, simply delete the old one to be able to move the new one at same location
            if (!unlink($this->getDeviceVersionImagePath($device, $newDeviceVersion))) {
                // bubble up error
                // @codeCoverageIgnoreStart
                // not testable; file needs to be changed externaly to get into this state
                throw new DeviceVersionImageFileDeletionException('Failed deleting image-file of version ' . $newDeviceVersion->getVersion() . ' of device with mac: ' . $device->getMac());
                // @codeCoverageIgnoreEnd
            }
        }

        return $this->save($device, $newDeviceVersion, $uploadedFile);
    }

    /**
     * @param Device $device
     * @param string $version
     * @return DeviceVersion
     * @throws DeviceInvalidException
     * @throws DeviceNotExistsException
     * @throws DeviceNotPersistedException
     * @throws DeviceVersionNotPersistedException
     * @throws InvalidVersionException
     */
    public function load(Device $device, $version) {
        
        if (!$device->isExisting()) {
            throw new DeviceNotExistsException('Invalid device given to load!');
        }
        if (!$device->isValid()) {
            throw new DeviceInvalidException('Invalid device given to load!');
        }

        $deviceVersion = new DeviceVersion($version, $this->logger);

        if ($version !== null) {

            $isValidVersion = $this->isValidVersion($version);
            if (!$isValidVersion) {
                throw new InvalidVersionException('Invalid version given to load!');
            }

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
        }

        return $deviceVersion;
    }

    /**
     * @param Device $device
     * @param DeviceVersion $deviceVersion
     * @return void
     * @throws DeviceNotPersistedException
     * @throws DeviceVersionDirectoryDeletionException
     * @throws DeviceVersionImageFileDeletionException
     * @throws DeviceVersionInfoFileDeletionException
     * @throws DeviceVersionNotPersistedException
     */
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
                    throw new DeviceVersionDirectoryDeletionException('Failed deleting directory of version ' . $deviceVersion->getVersion() . ' of device with mac: ' . $device->getMac());
                }
                // @codeCoverageIgnoreEnd
            } else {
                // bubble up error
                throw new DeviceVersionImageFileDeletionException('Failed deleting image-file of version ' . $deviceVersion->getVersion() . ' of device with mac: ' . $device->getMac());
            }
        } else {
            // bubble up error
            throw new DeviceVersionInfoFileDeletionException('Failed deleting info-file of version ' . $deviceVersion->getVersion() . ' of device with mac: ' . $device->getMac());
        }
    }

    /**
     * @param string $version
     * @return bool
     */
    public function isValidVersion($version) {
        $res = preg_match('/^[0-9]{1,2}\.[0-9]{1,3}(\.[0-9]{1,3})?$/', $version, $matches);
        return ($res === 1);
    }

    /**
     * @param DeviceVersion $deviceVersion
     * @return string the info of given DeviceVersion as JSON
     */
    public function getDeviceVersionInfoAsJson(DeviceVersion $deviceVersion) {
        return json_encode(array(
            'softwareName' => $deviceVersion->getSoftwareName(),
            'description' => $deviceVersion->getDescription()
        ));
    }

    /**
     * @param Device $device
     * @param DeviceVersion $deviceVersion
     * @return string
     * @throws DeviceNotPersistedException
     * @throws DeviceVersionNotPersistedException
     */
    public function getDeviceVersionInfoPath(Device $device, DeviceVersion $deviceVersion) {
        return $this->getDeviceVersionDirectoryPath($device, $deviceVersion) . $this->infoFileName;
    }

    /**
     * @param Device $device
     * @param DeviceVersion $deviceVersion
     * @return string
     * @throws DeviceNotPersistedException
     * @throws DeviceVersionNotPersistedException
     */
    public function getDeviceVersionImagePath(Device $device, DeviceVersion $deviceVersion) {
        return $this->getDeviceVersionDirectoryPath($device, $deviceVersion) . $this->imageFileName;
    }

    /**
     * @param Device $device
     * @param DeviceVersion $deviceVersion
     * @return string
     * @throws DeviceNotPersistedException
     * @throws DeviceVersionNotPersistedException
     */
    public function getDeviceVersionDirectoryPath(Device $device, DeviceVersion $deviceVersion) {
        if (empty($device->getMac())) {
            $this->logger->addError('Access to empty $mac of ' . get_class($device) . '. Probably using not fully initialized ' . get_class($device) . '?');
            throw new DeviceNotPersistedException('Access to empty $mac of ' . get_class($device) . '. Probably using not fully initialized ' . get_class($device) . '?');
        }
        if (empty($deviceVersion->getVersion())) {
            $this->logger->addError('Access to empty $version of ' . get_class($deviceVersion) . '. Probably using not fully initialized ' . get_class($deviceVersion) . '?');
            throw new DeviceVersionNotPersistedException('Access to empty $version of ' . get_class($deviceVersion) . '. Probably using not fully initialized ' . get_class($deviceVersion) . '?');
        }
        return $this->getDeviceDirectoryPath($device) . $deviceVersion->getVersion() . DIRECTORY_SEPARATOR;
    }

    /**
     * @param Device $device
     * @return string
     */
    public function getDeviceDirectoryPath(Device $device) {
        return DATA_DIR . $device->getMac() . DIRECTORY_SEPARATOR;
    }

}
