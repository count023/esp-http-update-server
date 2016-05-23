<?php

namespace com\gpioneers\esp\httpupload\controllers;

use \Interop\Container\ContainerInterface;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

use \com\gpioneers\esp\httpupload\models\Devices;
use \com\gpioneers\esp\httpupload\models\DeviceVersions;

class DeviceVersion {

    protected $ci;
    protected $parentRepository;
    protected $repository;

    // Constructor
    public function __construct(ContainerInterface $ci) {
        $this->ci = $ci;
        $this->parentRepository = new Devices($this->ci->logger);
        $this->repository = new DeviceVersions($this->ci->logger);
    }

    // GET /admin/upload
    public function showForm(Request $request, Response $response, $args) {

        $defaults = array(
            'title' => 'ESP-Binary-Image-Upload'
        );

        $device = $this->parentRepository->load($args['staMac']);

        if ($device->isExisting()) {

            $formData = $request->getParsedBody();
            $deviceVersion = $this->repository->load($device, $formData['version'], $this->ci->logger);

            // it's an update:
            if (!empty($args['version'])) {
                $formData['softwareName'] = $deviceVersion->getSoftwareName();
                $formData['description'] = $deviceVersion->getDescription();
            } else {
                $formData['version'] = '';
                $formData['softwareName'] = '';
                $formData['description'] = '';
            }

            $response = $this->ci->renderer->render(
                $response,
                'admin/device/version/form.phtml',
                array_merge([
                    'defaults' => $defaults,
                    'device' => $this->parentRepository->getAll(),
                    'router' => $this->ci->router,
                    'formData' => $formData
                ], $args)
            );

            return $response;

        } else {
            return $response->withStatus(302)->withHeader('Location', '/admin/device/new');
        }
    }

    // POST /admin/upload
    public function create(Request $request, Response $response, $args) {

        $formData = $request->getParsedBody();
        $formData['files'] = $request->getUploadedFiles();

        // validate &
        $msgs = $this->repository->validate($formData);
        if (!empty($msgs)) {

            return $this->showForm($request, $response, [ 'msgs' => $msgs, 'formData' => $formData ]);

        } else {
            // upload &

            // show sucess message
        }




    }

    // GET /admin/{STA-Mac}(known)
    // GET /admin/{STA-Mac}(unknown)
    public function read(Request $request, Response $response, $args) {

    }

    // POST /admin/{STA-Mac
    public function update(Request $request, Response $response, $args) {

    }

    // DELETE /admin/{STA-Mac}/{version}
    public function delete(Request $request, Response $response, $args) {

      $userName = explode(':', $request->getUri()->getUserInfo())[0];

      $directoryPath = $this->ci["environment"]["DOCUMENT_ROOT"] . '/../data/' . $args['staMac'] . '/' . $args['version'];

      if (is_dir($directoryPath) && is_file($directoryPath . '/image.bin')) {
          if (unlink($directoryPath . '/image.bin') && rmdir($directoryPath)) {
              $response->getBody()->write('Deleted image for ' . $args['staMac'] . ' of version ' . $args['version'] . "\n");
              $this->ci->logger->addInfo('Deleted image for ' . $args['staMac'] . ' of version ' . $args['version'] . ' by authorzed user named: \'' . $userName . '\'');
          } else {
              // send error
              $this->ci->logger->addError('Failed deleting image for ' . $args['staMac'] . ' of version ' . $args['version'] . ' by authorzed user named: \'' . $userName . '\'');
              return $response->withStatus(500);
          }
      } else {
          // send 404
          $this->ci->logger->addWarning('Request to delete not existing image for ' . $args['staMac'] . ' of version ' . $args['version'] . ' by authorzed user named: \'' . $userName . '\'');
          return $response->withStatus(404);
      }

      return $response;

    }

}
