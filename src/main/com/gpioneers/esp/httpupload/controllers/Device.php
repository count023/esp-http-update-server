<?php

namespace com\gpioneers\esp\httpupload\controllers;

use \Interop\Container\ContainerInterface;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use \com\gpioneers\esp\httpupload\models\Devices;

class Device {

    protected $ci;
    protected $repository;

    // Constructor
    public function __construct(ContainerInterface $ci) {
        $this->ci = $ci;
        $this->repository = new Devices($this->ci->logger);
    }

    // GET / admin/devices
    public function all(Request $request, Response $response, $args) {

        $devices = $this->repository->getAll();

        $defaults = array(
            'title' => 'ESP-Binary-Image-Upload: ESPs'
        );

        $queryParameter = $request->getQueryParams();
        $msg = array_key_exists('msg', $queryParameter) ? $queryParameter['msg'] : '';

        $response = $this->ci->renderer->render(
            $response,
            'admin/device/list.phtml',
            array_merge([
                'defaults' => $defaults,
                'devices' => $devices,
                'msg' => $msg
            ], $args)
        );

        return $response;
    }

    // GET /admin/device/new
    // GET /admin/device/{STA-Mac}/edit
    public function showForm(Request $request, Response $response, $args) {

        $defaults = array(
            'title' => 'ESP-Binary-Image-Upload: Einrichtung ESP'
        );

        $msgs = array();

        if (empty($args['staMac'])) { // new

            $device = $this->repository->load(null);

            // check if it is a redirect from read with unknown mac-address
            $queryParameter = $request->getQueryParams();
            $staMac = array_key_exists('staMac', $queryParameter) ? $queryParameter['staMac'] : '';
            if (!empty($staMac)) {
                $msgs = array('mac' => 'Bisher unbekanntes Device');
                $device->setMac($staMac);
            }
        } else { // edit

            $device = $this->repository->load($args['staMac']);
        }

        return $this->ci->renderer->render(
            $response,
            'admin/device/form.phtml',
            array_merge([
                'defaults' => $defaults,
                'device' => $device,
                'msgs' => $msgs
            ], $args)
        );
    }

    /**
     * POST /admin/device/new
     *
     * @TODO: almost the same as $this->update. Join it!
     */
    public function create(Request $request, Response $response, $args) {

        $defaults = array(
            'title' => 'ESP-Binary-Image-Upload: Einrichtung ESP'
        );

        $formData = $request->getParsedBody();

        // validate &
        $msgs = $this->validate($formData);

        if (!empty($msgs)) {

            $device = $this->repository->load(null);
            $device->setMac($formData['mac']);
            $device->setType($formData['type']);
            $device->setInfo($formData['info']);

            return $this->ci->renderer->render(
                $response,
                'admin/device/form.phtml',
                array_merge([
                    'defaults' => $defaults,
                    'device' => $device,
                    'msgs' => $msgs
                ], $args)
            );

        } else {

            $device = $this->repository->load($formData['mac']);
            $device->setType($formData['type']);
            $device->setInfo($formData['info']);
            $success = $this->repository->save($device);

            // redirect to device page and show success message
            return $this->ci->renderer->render(
                $response,
                'admin/device/detail.phtml',
                array_merge([
                    'msg' => ($success ? 'Sucessfully created' : 'Creation failed'),
                    'defaults' => $defaults,
                    'device' => $device
                ], $args)
            );
        }
    }

    // GET /admin/device/{STA-Mac}(known)
    // GET /admin/device/{STA-Mac}(unknown)
    public function read(Request $request, Response $response, $args) {

        $device = null;
        try {
            $device = $this->repository->load($args['staMac']);
        } catch (Exception $e) {
            $device = $this->repository->load(null);
            $device->setMac($args['staMac']);
        }

        if ($device->isExisting()) {

            return $this->ci->renderer->render(
                $response,
                'admin/device/detail.phtml',
                array_merge([
                    'defaults' => array(
                        'title' => 'ESP-Binary-Image-Upload: Bekannter ESPs'
                    ),
                    'device' => $device
                ], $args)
            );

        } else {
            return $response->withStatus(302)->withHeader('Location', '/admin/device/new?staMac=' . $args['staMac']);
        }
    }

    /**
     * POST /admin/device/{STA-Mac}/edit
     */
    public function update(Request $request, Response $response, $args) {

        $defaults = array(
            'title' => 'ESP-Binary-Image-Upload: Update ESP'
        );

        $formData = $request->getParsedBody();

        // validate
        $msgs = $this->validate($formData, true);
        if (!empty($msgs)) {

            $currentDevice = $this->repository->load($formData['staMac']);
            $newDevice = null;
            if (!empty($msgs['mac'])) {
                $newDevice = $this->repository->load(null);
                $newDevice->setMac($formData['mac']);
            } else {
                $newDevice = $this->repository->load($formData['mac']);
            }
            $newDevice->setType($formData['type']);
            $newDevice->setInfo($formData['info']);

            if ($currentDevice->getMac() !== $newDevice->getMac() && $newDevice->isExisting()) {
                // resetting the mac-address to the old valid one!
                $newDevice->setMac($currentDevice->getMac());
            }

            return $this->ci->renderer->render(
                $response,
                'admin/device/form.phtml',
                array_merge([
                    'defaults' => $defaults,
                    'device' => $newDevice,
                    'msgs' => $msgs,
                    'formData' => $formData
                ], $args)
            );

        } else {

            $currentDevice = $this->repository->load($formData['staMac']);
            $newDevice = $this->repository->load($formData['mac']);
            $newDevice->setType($formData['type']);
            $newDevice->setInfo($formData['info']);

            $success = $this->repository->update($currentDevice, $newDevice);
            return $response->withStatus(302)->withHeader('Location', '/admin/device/' . $newDevice->getMac() . '?msg=' . ($success ? 'Sucessfully updated' : 'Updating failed'));
        }
    }

    // DELETE /admin/device/{STA-Mac}
    // POST /admin/device/{STA-Mac}/delete
    public function delete(Request $request, Response $response, $args) {

        $userName = explode(':', $request->getUri()->getUserInfo())[0];

        $device = $this->repository->load($args['staMac'], $this->ci->logger);

        if ($device->isExisting()) {

            $this->ci->logger->addInfo('About to delete device ' . $device->getMac() . ' by authorzed user named: \'' . $userName . '\'');

            // try to call Devices::delete
            try {

                $this->repository->delete($device);
                $this->ci->logger->addInfo('Deleted device ' . $device->getMac() . ' by authorzed user named: \'' . $userName . '\'');

                return $response->withStatus(302)->withHeader('Location', '/admin/devices?msg=' . 'Deleted device ' . $device->getMac());

            } catch(\Exception $ex) {

                $this->ci->logger->addError('Failed to delete device ' . $device->getMac() . ' by authorzed user named: \'' . $userName . '\'');
                return $response->withStatus(500)->withHeader('Location', '/admin/devices?msg=' . 'Unknown error occurred when trying to delete device ' . $device->getMac());
            }
        }

        // else: send 404
        $this->ci->logger->addWarning('Request to delete not existing device with mac ' . $args['staMac'] . ' by authorzed user named: \'' . $userName . '\'');
        return $response->withStatus(404)->withHeader('Location', '/admin/devices?msg=' . 'Trial to delete unknown device ' . $device->getMac());
    }

    private function validate($formData, $isUpdate = false) {

        $msgs = array();

        // formData contains valid mac
        if (empty($formData['mac'])) {
            $msgs['mac'] = 'Keine Mac-Adresse angegeben!';
        } else if (!$this->repository->isValidMac($formData['mac'])) {
            $msgs['mac'] = 'Ungültige Mac-Adresse angegeben!';
        } else {
            $newDevice = $this->repository->load($formData['mac']);
            if (!$isUpdate && $newDevice->isExisting()) {
                $msgs['mac'] = 'Device mit dieser Mac-Adresse existiert bereits!';
            } else if ($isUpdate && $formData['mac'] !== $formData['staMac'] && $newDevice->isExisting()) {
                $msgs['mac'] = 'You tried to change the mac-address of the device to "' . $formData['mac'] . '", but this mac-address already exists!';
            }
        }
        // postData contains any esp type
        if (empty($formData['type'])) {
            $msgs['type'] = 'Keine ESP-Version angegeben!';
        }
        // postData contains any info
        if (empty($formData['info'])) {
            $msgs['info'] = 'Keine weiteren Informationen zum ESP angegeben!';
        }

        return $msgs;
    }
}
