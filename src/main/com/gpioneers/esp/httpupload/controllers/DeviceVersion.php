<?php

namespace com\gpioneers\esp\httpupload\controllers;

use \Interop\Container\ContainerInterface;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

use \com\gpioneers\esp\httpupload\models\Devices;
use \com\gpioneers\esp\httpupload\models\Device as DeviceModel;
use \com\gpioneers\esp\httpupload\models\DeviceVersions;
use \com\gpioneers\esp\httpupload\exceptions\DeviceVersionNotExistsException;

class DeviceVersion {

    /**
     * @var ContainerInterface
     */
    protected $ci;
    /**
     * @var Devices
     */
    protected $parentRepository;
    /**
     * @var DeviceVersions
     */
    protected $repository;

    /**
     * DeviceVersion constructor.
     * @param ContainerInterface $ci
     */
    public function __construct(ContainerInterface $ci) {
        $this->ci = $ci;
        $this->parentRepository = new Devices($this->ci->logger);
        $this->repository = new DeviceVersions($this->ci->logger);
    }

    /**
     * GET /admin/{STA-Mac}/version/new
     * GET /admin/device/{STA-Mac}/version/{version}/edit
     *
     * @param Request $request
     * @param Response $response
     * @param array $args as provided by slim 3
     * @return Response
     * @throws \com\gpioneers\esp\httpupload\exceptions\DeviceInvalidException
     * @throws \com\gpioneers\esp\httpupload\exceptions\DeviceNotExistsException
     * @throws \com\gpioneers\esp\httpupload\exceptions\InvalidVersionException
     */
    public function showForm(Request $request, Response $response, $args) {

        $defaults = array(
            'title' => 'ESP-Binary-Image-Upload'
        );

        $device = $this->loadDevice($args['staMac']);

        if ($device !== null) {

            $version = '';

            if (empty($args['version'])) { // new

                $deviceVersion = $this->repository->load($device, null);

                // check if it is a redirect from read with unknown version
                $queryParameter = $request->getQueryParams();
                $version = array_key_exists('version', $queryParameter) ? $queryParameter['version'] : '';
                if (!empty($version)) {
                    $msgs = array('version' => 'Bisher unbekannte Version');
                    $deviceVersion->setVersion($version);
                }

            } else { // edit

                $deviceVersion = $this->repository->load($device, $args['version']);
            }

            return $this->ci->renderer->render(
                $response,
                'admin/device/version/form.phtml',
                array_merge([
                    'defaults' => $defaults,
                    'device' => $device,
                    'deviceVersion' => $deviceVersion,
                    'currentVersion' => $version
                ], $args)
            );

        } else {
            return $response->withStatus(302)->withHeader('Location', '/admin/device/new');
        }
    }

    /**
     * POST /admin/{STA-Mac}/version/new
     *
     * @param Request $request
     * @param Response $response
     * @param array $args as provided by slim 3
     * @return Response
     * @throws \com\gpioneers\esp\httpupload\exceptions\DeviceInvalidException
     * @throws \com\gpioneers\esp\httpupload\exceptions\DeviceNotExistsException
     * @throws \com\gpioneers\esp\httpupload\exceptions\DeviceVersionInfoFileUnwritableException
     * @throws \com\gpioneers\esp\httpupload\exceptions\InvalidVersionException
     * @throws \com\gpioneers\esp\httpupload\exceptions\UploadedFileErrorException
     */
    public function create(Request $request, Response $response, $args) {

        $defaults = array(
            'title' => 'ESP-Binary-Image-Upload: Neue Version'
        );

        $device = $this->loadDevice($args['staMac']);

        if ($device !== null) {

            $formData = $request->getParsedBody();
            $formData['files'] = $request->getUploadedFiles();

            // validate &
            $msgs = $this->validate($device, $formData);

            if (!empty($msgs)) {

                $deviceVersion = $this->repository->load($device, null);
                $deviceVersion->setVersion($formData['version']);
                $deviceVersion->setSoftwareName($formData['softwareName']);
                $deviceVersion->setDescription($formData['description']);

                return $this->ci->renderer->render(
                    $response,
                    'admin/device/version/form.phtml',
                    array_merge([
                        'defaults' => $defaults,
                        'device' => $device,
                        'deviceVersion' => $deviceVersion,
                        'msgs' => $msgs
                    ], $args)
                );

            } else {

                $deviceVersion = $this->repository->load($device, $formData['version']);
                $deviceVersion->setSoftwareName($formData['softwareName']);
                $deviceVersion->setDescription($formData['description']);
                $success = $this->repository->save($device, $deviceVersion, $formData['files']['file']);

                // redirect to device version page and show success message
                return $this->ci->renderer->render(
                    $response,
                    'admin/device/version/detail.phtml',
                    array_merge([
                        'msg' => ($success ? 'Sucessfully created' : 'Creation failed'),
                        'defaults' => $defaults,
                        'device' => $device,
                        'deviceVersion' => $deviceVersion
                    ], $args)
                );

            }
        } else {
            return $response->withStatus(302)->withHeader('Location', '/admin/device/new');
        }
    }

    /**
     * GET /admin/{STA-Mac}/version/{version}(known)
     * GET /admin/{STA-Mac}/version/{version}(unknown)
     *
     * @param Request $request
     * @param Response $response
     * @param array $args as provided by slim 3
     * @return Response
     * @throws \com\gpioneers\esp\httpupload\exceptions\DeviceInvalidException
     * @throws \com\gpioneers\esp\httpupload\exceptions\DeviceNotExistsException
     * @throws \com\gpioneers\esp\httpupload\exceptions\InvalidVersionException
     */
    public function read(Request $request, Response $response, $args) {

        $defaults = array(
            'title' => 'ESP-Binary-Image-Upload: Version anzeigen'
        );

        $device = $this->loadDevice($args['staMac']);

        if ($device !== null) {

            $deviceVersion = null;
            try {
                $deviceVersion = $this->repository->load($device, $args['version']);
            } catch (Exception $e) {
                $deviceVersion = $this->repository->load($device, null);
                $deviceVersion->setVersion($args['version']);
            }

            if ($deviceVersion->isExisting()) {

                return $this->ci->renderer->render(
                    $response,
                    'admin/device/version/detail.phtml',
                    array_merge([
                        'defaults' => $defaults,
                        'device' => $device,
                        'deviceVersion' => $deviceVersion
                    ], $args)
                );

            } else {
                return $response->withStatus(302)->withHeader('Location', '/admin/device/' . $device->getMac() . '/version/new?version=' . $args['version']);
            }
        } else {
            return $response->withStatus(302)->withHeader('Location', '/admin/device/new');
        }
    }

    /**
     * POST /admin/device/{STA-Mac}/version/{version}/edit
     *
     * @param Request $request
     * @param Response $response
     * @param array $args as provided by slim 3
     * @return Response
     * @throws DeviceVersionNotExistsException
     * @throws \com\gpioneers\esp\httpupload\exceptions\DeviceInvalidException
     * @throws \com\gpioneers\esp\httpupload\exceptions\DeviceNotExistsException
     * @throws \com\gpioneers\esp\httpupload\exceptions\DeviceVersionDuplicateException
     * @throws \com\gpioneers\esp\httpupload\exceptions\DeviceVersionImageFileDeletionException
     * @throws \com\gpioneers\esp\httpupload\exceptions\InvalidVersionException
     */
    public function update(Request $request, Response $response, $args) {

        $defaults = array(
            'title' => 'ESP-Binary-Image-Upload: Update Version'
        );

        $device = $this->loadDevice($args['staMac']);

        if ($device !== null) {

            $formData = $request->getParsedBody();
            $formData['files'] = $request->getUploadedFiles();

            // validate &
            $msgs = $this->validate($device, $formData, true);
            if (!empty($msgs)) {

                $currentDeviceVersion = $device->getVersion($formData['currentVersion']); //$this->repository->load($device, $formData['currentVersion']);
                if ($currentDeviceVersion === null) {
                    throw new DeviceVersionNotExistsException('Trial to update not existing version! ("' . $formData['currentVersion'] . '")');
                }
                $newDeviceVersion = null;
                if (!empty($msgs['version'])) { // empty or invalid version or new version already exists
                    $existingVersion = $device->getVersion($formData['version']);
                    if ($existingVersion !== null) {
                        $newDeviceVersion = $existingVersion;
                    } else {
                        $newDeviceVersion = $this->repository->load($device, null);
                        $newDeviceVersion->setVersion($formData['version']);
                    }
                } else {
                    $newDeviceVersion = $this->repository->load($device, $formData['version']);
                }
                $newDeviceVersion->setSoftwareName($formData['softwareName']);
                $newDeviceVersion->setDescription($formData['description']);

                if ($currentDeviceVersion->getVersion() !== $newDeviceVersion->getVersion() && $newDeviceVersion->isExisting()) {
                    // resetting the version number to the old valid one!
                    $this->ci->logger->addDebug('resetting the version number to the old valid one!');
                    $newDeviceVersion->setVersion($currentDeviceVersion->getVersion());
                }

                return $this->ci->renderer->render(
                    $response,
                    'admin/device/version/form.phtml',
                    array_merge([
                        'defaults' => $defaults,
                        'device' => $device,
                        'deviceVersion' => $newDeviceVersion,
                        'msgs' => $msgs,
                        # 'formData' => $formData
                    ], $args)
                );

            } else {

                $currentDeviceVersion = $this->repository->load($device, $formData['currentVersion']);
                $newDeviceVersion = $this->repository->load($device, $formData['version']);
                $newDeviceVersion->setSoftwareName($formData['softwareName']);
                $newDeviceVersion->setDescription($formData['description']);

                $success = $this->repository->update($device, $currentDeviceVersion, $newDeviceVersion, $formData['files']['file']);
                return $response->withStatus(302)->withHeader('Location', '/admin/device/' . $device->getMac() . '/version/' . $newDeviceVersion->getVersion() . '?msg=' . ($success ? 'Sucessfully updated' : 'Updating failed'));
            }
        } else {
            return $response->withStatus(302)->withHeader('Location', '/admin/device/new?staMac=' . $args['staMac']);
        }
    }

    /**
     * DELETE /admin/device/{STA-Mac}/version/{version}
     * POST /admin/device/{STA-Mac}/version/{version}/delete
     *
     * @param Request $request
     * @param Response $response
     * @param array $args as provided by slim 3
     * @return Response
     */
    public function delete(Request $request, Response $response, $args) {

      $userName = explode(':', $request->getUri()->getUserInfo())[0];

      $device = $this->loadDevice($args['staMac']);

      if ($device !== null) {

          $deviceVersion = null;
          try {
              $deviceVersion = $this->repository->load($device, $args['version']);
              /*
               * @throws DeviceInvalidException -> 400 Bad Request
               * @throws DeviceNotExistsException -> 404
               * @throws DeviceNotPersistedException ->
               * @throws DeviceVersionNotPersistedException ->
               * @throws InvalidVersionException ->
               */
          } catch (\Exception $e) {
              return $response->withStatus(404)->withHeader('Location', '/admin/devices?msg=' . 'Trial to delete unknown device (' . $device->getMac() . ') version (' . $args['version'] . ')');
          }

          if ($deviceVersion !== null && $deviceVersion->isExisting()) {
              try {
                  $this->repository->delete($device, $deviceVersion);
              } catch (\Exception $ex) {
                  $this->ci->logger->addError('Failed to delete device version of device ' . $device->getMac() . ' with version ' . $deviceVersion->getVersion() . ' by authorzed user named: \'' . $userName . '\'');
                  return $response->withStatus(500)->withHeader('Location', '/admin/devices?msg=' . 'Unknown error occurred when trying to delete device ' . $device->getMac());
              }
              $this->ci->logger->addInfo('Deleted device version of device ' . $device->getMac() . ' version ' . $deviceVersion->getVersion() . ' by authorzed user named: \'' . $userName . '\'');
              return $response->withStatus(404)->withHeader('Location', '/admin/device/' . $device->getMac() . '?msg=' . 'Sucessfully deleted version of device (' . $device->getMac() . ') version (' . $args['version'] . ')');
          } else {
              $this->ci->logger->addError('Request to delete not existing device version of device ' . $device->getMac() . ' with version ' . $deviceVersion->getVersion() . ' by authorzed user named: \'' . $userName . '\'');
              return $response->withStatus(404)->withHeader('Location', '/admin/devices?msg=' . 'Trial to delete unknown device (' . $device->getMac() . ') version (' . $args['version'] . ')');
          }

      } else {
          return $response->withStatus(302)->withHeader('Location', '/admin/device/new');
      }

    }

    /**
     * @param string $staMac
     * @return \com\gpioneers\esp\httpupload\models\Device|null if device does not exist or given ,mac is invalid
     * @throws \com\gpioneers\esp\httpupload\exceptions\InvalidMacException
     */
    private function loadDevice($staMac) {
        $device = null;
        if ($this->parentRepository->isValidMac($staMac)) {
            $requestedDevice = $this->parentRepository->load($staMac);
            if ($requestedDevice->isExisting()) {
                $device = $requestedDevice;
            }
        }
        return $device;
    }

    /**
     * @param \com\gpioneers\esp\httpupload\models\Device $device
     * @param array $formData
     * @param bool $isUpdate optional
     * @return array validation messages, keys in analogy to the expected form-data keys
     * @throws \com\gpioneers\esp\httpupload\exceptions\DeviceVersionDuplicateException
     */
    private function validate(DeviceModel $device, $formData, $isUpdate = false) {

        $msgs = array();

        // postData contains valid version
        if (empty($formData['version'])) {
            $msgs['version'] = 'Keine Version angegeben!';
        } else if (!$this->repository->isValidVersion($formData['version'])) {
            $msgs['version'] = 'Ungültige Version angegeben!';
        } else {
            $newDeviceVersion = $device->getVersion($formData['version']); // $this->repository->load($device, $formData['version']);
            if (!$isUpdate && $newDeviceVersion !== null && $newDeviceVersion->isExisting()) {
                $msgs['version'] = 'Diese Version existiert bereits!';
            } else if ($isUpdate && $formData['version'] !== $formData['currentVersion'] && $newDeviceVersion !== null && $newDeviceVersion->isExisting()) {
                $msgs['version'] = 'You tried to change the version number to "' . $formData['version'] . '", but this version already exists!';
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
            !$isUpdate && // on update the file could be empty!
            (
                !array_key_exists('files', $formData) ||
                $formData['files'] === null ||
                !array_key_exists('file', $formData['files']) ||
                $formData['files']['file']->getSize() <= 0
            )
        ) {
            $msgs['file'] = 'Keine Datei gesendet!';
        }

        $this->ci->logger->addDebug(__METHOD__ . ' ' . print_r($msgs, true));

        return $msgs;
    }
}
