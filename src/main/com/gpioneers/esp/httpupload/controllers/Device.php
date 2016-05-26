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
            $device = $this->repository->load(null, $this->ci->logger);

            // check if it is a redirect from read with anknown mac-address
            $queryParameter = $request->getQueryParams();
            $staMac = array_key_exists('staMac', $queryParameter) ? $queryParameter['staMac'] : '';
            if (!empty($staMac)) {
                $msgs = array('mac' => 'Bisher unbekanntes Device');
                $device->setMac($staMac);
            }
        } else { // edit
            $device = $this->repository->load($args['staMac'], $this->ci->logger);
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
        $msgs = $this->repository->validate($formData);

        if (!empty($msgs)) {

            $device = $this->repository->load(null, $this->ci->logger);
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

            $device = $this->repository->load($formData['mac'], $this->ci->logger);
            $device->setType($formData['type']);
            $device->setInfo($formData['info']);
            $success = $this->repository->save($device);

            // redirect to device page and show sucess message
            return $this->ci->renderer->render(
                $response,
                'admin/device/detail.phtml',
                array_merge([
                    'staMac' => $formData['mac'],
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
            $device = $this->repository->load($args['staMac'], $this->ci->logger);
        } catch (Exception $e) {
            $device = $this->repository->load(null, $this->ci->logger);
            $device->setMac($args['staMac']);
            $args['device'] = $device;
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
     *
     * @TODO: if mac changes on update, check for not overwriteing already existing one!
     */
    public function update(Request $request, Response $response, $args) {

        $defaults = array(
            'title' => 'ESP-Binary-Image-Upload: Update ESP'
        );

        $formData = $request->getParsedBody();

        // validate
        $msgs = $this->repository->validate($formData, false);
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

                $msgs['mac'] = 'You tried to change the mac-address of the device, but the new mac-address already exists!';
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

            if ($currentDevice->getMac() !== $newDevice->getMac() && $newDevice->isExisting()) {

                $msgs['mac'] = 'You tried to change the mac-address of the device, but the new mac-address already exists!';
                // resetting the mac-address to the old valid one!
                $newDevice->setMac($currentDevice->getMac());

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

                $success = $this->repository->update($currentDevice, $newDevice);
                return $response->withStatus(302)->withHeader('Location', '/admin/device/' . $formData['mac'] . '?msg=' . ($success ? 'Sucessfully updated' : 'Updating failed'));
            }
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
}
