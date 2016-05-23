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
        $response = $this->ci->renderer->render(
            $response,
            'admin/device/list.phtml',
            array_merge([
                'defaults' => $defaults,
                'devices' => $devices
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

        // it's an update:
        $formData = $request->getParsedBody();
        if (empty($args['staMac'])) {
            $args['staMac'] = null;
        }
        $device = $this->repository->load($args['staMac'], $this->ci->logger);
        $formData['mac'] = $args['staMac'];
        $formData['type'] = $device->getType();
        $formData['deviceInfo'] = $device->getInfo();

        $response = $this->ci->renderer->render(
            $response,
            'admin/device/form.phtml',
            array_merge([
                'defaults' => $defaults,
                'device' => $device
            ], $args)
        );

        return $response;
    }

    /**
     * POST /admin/device/new
     *
     * @TODO: almost the same as $this->update. Join it!
     */
    public function create(Request $request, Response $response, $args) {

        $formData = $request->getParsedBody();

        // validate &
        $msgs = $this->repository->validate($formData);

        if (!empty($msgs)) {

            return $this->showForm($request, $response, [ 'msgs' => $msgs, 'staMac' => $formData['mac'] ]);

        } else {

            $device = $this->repository->load($formData['mac'], $this->ci->logger);
            $device->setType($formData['type']);
            $device->setInfo($formData['deviceInfo']);
            $success = $this->repository->save($device);

            // redirect to device page and show sucess message
            return $this->read(
                $request,
                $response,
                array(
                    'staMac' => $formData['mac'],
                    'msg' => ($success ? 'Sucessfully created' : 'Creation failed')
                )
            );
        }

    }

    // GET /admin/device/{STA-Mac}(known)
    // GET /admin/device/{STA-Mac}(unknown)
    public function read(Request $request, Response $response, $args) {
        $defaults = array(
            'title' => 'ESP-Binary-Image-Upload: Bekannte ESPs'
        );

        $device = $this->repository->load($args['staMac'], $this->ci->logger);
        if ($device->isExisting()) {
            return $this->ci->renderer->render(
                $response,
                'admin/device/detail.phtml',
                array_merge([
                    'defaults' => $defaults,
                    'device' => $device
                ], $args)
            );
        } else {
            $args['msgs'] = array('mac' => 'Bisher unbekanntes Device');
            return $this->showForm($request, $response, $args);
        }
    }

    /**
     * POST /admin/device/{STA-Mac}/edit
     *
     * @TODO: if mac changes on update, check for not overwriteing already existing one!
     */
    public function update(Request $request, Response $response, $args) {

        $formData = $request->getParsedBody();

        // validate
        $msgs = $this->repository->validate($formData);
        if (!empty($msgs)) {

            return $this->showForm($request, $response, [ 'msgs' => $msgs, 'formData' => $formData ]);

        } else {

            echo 'Updating: staMac: ' . $formData['staMac'] . ' mac: ' . $formData['mac'] . '<br><pre><code>';
            var_dump($formData);
            echo '</code></pre>';

            $currentDevice = $this->repository->load($formData['staMac']);
            $newDevice = $this->repository->load($formData['mac']);
            $newDevice->setType($formData['type']);
            $newDevice->setInfo($formData['deviceInfo']);

            $success = $this->repository->update($currentDevice, $newDevice);

            // redirect to device page and show sucess message
            $newResponse = $this->read(
                $request,
                $response,
                array(
                    'staMac' => $formData['mac'],
                    'msg' => ($success ? 'Sucessfully updated' : 'Updating failed')
                )
            );

            if ($formData['staMac'] !== $formData['mac']) {
                return $newResponse->withStatus(302)->withHeader('Location', '/admin/device/' . $formData['mac']);
            } else {
                return $newResponse;
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

                $args = array_merge($args, array('msg' => 'Deleted device ' . $device->getMac() . "\n"));
                return $this->all($request, $response, $args);

            } catch(\Exception $ex) {

                $this->ci->logger->addError('Failed to delete device ' . $device->getMac() . ' by authorzed user named: \'' . $userName . '\'');
                return $response->withStatus(500);

            }

        }



        // else: send 404
        $this->ci->logger->addWarning('Request to delete not existing device with mac ' . $args['staMac'] . ' by authorzed user named: \'' . $userName . '\'');
        return $response->withStatus(404);

    }

}
