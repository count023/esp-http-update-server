<?php

namespace com\gpioneers\esp\httpupload\controllers;

use \Interop\Container\ContainerInterface;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use \com\gpioneers\esp\httpupload\models\Devices;
use \com\gpioneers\esp\httpupload\models\DeviceVersions;
use \com\gpioneers\esp\httpupload\models\DeviceAuthentification as DeviceAuthentificationModel;
use \com\gpioneers\esp\httpupload\models\DeviceAuthentifications;

class DeviceAuthentification {

    /**
     * @ver LoggerInterface
     */
    protected $ci;
    /**
     * @var DeviceAuthentifications
     */
    protected $repository;
    /**
     * @var Devices
     */
    protected $deviceRepository;
    /**
     * @var Devices
     */
    protected $deviceVersionRepository;

    /**
     * DeviceAuthentification constructor.
     * @param ContainerInterface $ci
     */
    public function __construct(ContainerInterface $ci) {
        $this->ci = $ci;
        $this->deviceRepository = new Devices($this->ci->logger);
        $this->deviceVersionRepository = new DeviceVersions($this->ci->logger);
        $this->repository = new DeviceAuthentifications($this->deviceRepository, $this->ci->logger);
    }

    /**
     * requesting authentification with certain header infos about identity
     *
     * ! This method needs to be accessible only by BasicAuth !
     *
     * Testable with:
     * <pre><code>
     * curl
     *     -vvv
     *     --user <DEVICE>:<PASSWORD>
     *     -X POST
     *     --header "x-ESP8266-STA-MAC: 00:00:00:00:00:00"
     *     --header "x-ESP8266-AP-MAC: 01:01:01:01:01:01"
     *     --header "x-ESP8266-chip-size: 8192"
     *     --header "x-ESP8266-version: 0.0"
     *     http://localhost:8001/device/authenticate/00:00:00:00:00:00
     * </code></pre>
     * a file authentification.json should be written to
     * DATA_DIR . '/00:00:00:00:00:00/authentification.json' with content:
     * <pre><code>
     * {"staMac":"00:00:00:00:00:00","apMac":"01:01:01:01:01:01","chipSize":"8192","timestamp":<TIMESTAMP>}
     * </code></pre>
     * if a device with mac 00:00:00:00:00:00 already exists ...
     *
     * @param Request $request
     * @param Response $response
     * @param array $args as provided by slim 3
     * @return Response
     */
    public function authenticate(Request $request, Response $response, $args) {

        // validate
        $headerValues = $this->getRelevantHeaderValues($request);
        if ($this->isValidRequest($headerValues, $args)) {

            try {
                // save this authentification info
                $device = $this->deviceRepository->load($headerValues['staMac'][0]);
                if ($device->isExisting() && $device->isValid()) {

                    // @TODO: check if $this->repository->load($staMac) can be sufficient ...
                    $deviceAuthentification = new DeviceAuthentificationModel($device, $this->ci->logger);
                    $deviceAuthentification->setApMac($headerValues['apMac'][0]);
                    $deviceAuthentification->setChipSize($headerValues['chipSize'][0]);
                    $deviceAuthentification->setTimestamp(time());

                    $this->repository->save($deviceAuthentification);

                    // response with Status-Code 200 if the device is known
                    return $response->withStatus(200);
                } else {
                    // send 422 Unprocessable Entity
                    return $response->withStatus(422, 'Unprocessable Entity');
                }
            } catch (\Exception $ex) { // schematically invalid mac-address
                // send 400 Bad Request
                return $response->withStatus(400);
            }
        } else {
            // send 420 Policy Not Fulfilled
            return $response->withStatus(420, 'Policy Not Fulfilled');
        }

        // send 404 Not Found in case nothing is working ;) (Should never be reached in any case ... )
        return $response->withStatus(404);
    }

    /**
     * handling request by ESP8266httpUpdate library
     *
     * _!NO!_ BasicAuth here!
     *
     * header fields available on httpUpdateRequest from ESP along
     * https://github.com/esp8266/Arduino/blob/master/libraries/ESP8266httpUpdate/src/ESP8266httpUpdate.cpp#L173
     * <pre><code>
     *    http.setUserAgent(F("ESP8266-http-Update"));
     *    http.addHeader(F("x-ESP8266-STA-MAC"), WiFi.macAddress());
     *    http.addHeader(F("x-ESP8266-AP-MAC"), WiFi.softAPmacAddress());
     *    http.addHeader(F("x-ESP8266-free-space"), String(ESP.getFreeSketchSpace()));
     *    http.addHeader(F("x-ESP8266-sketch-size"), String(ESP.getSketchSize()));
     *    http.addHeader(F("x-ESP8266-chip-size"), String(ESP.getFlashChipRealSize()));
     *    http.addHeader(F("x-ESP8266-sdk-version"), ESP.getSdkVersion());
     *    http.addHeader(F("x-ESP8266-mode"), F("sketch"));
     *    http.addHeader(F("x-ESP8266-version"), currentVersion);
     * </code></pre>
     *
     * @param Request $request
     * @param Response $response
     * @param array $args as provided by slim 3
     * @return Response
     */
    public function download(Request $request, Response $response, $args) {

        $headerValues = $this->getRelevantHeaderValues($request);

        if ($this->isValidRequest($headerValues, $args)) {
            try {
                // save this authentification info
                $device = $this->deviceRepository->load($headerValues['staMac'][0]);
                if ($device->isExisting() && $device->isValid()) {

                    $deviceAuthentification = $this->repository->load($device);
                    if ($deviceAuthentification !== null) {

                        // check timestamp aso...
                        if (
                            $this->repository->authenticate(
                                $deviceAuthentification,
                                array(
                                    'staMac' => $headerValues['staMac'][0],
                                    'apMac' => $headerValues['apMac'][0],
                                    'chipSize' => $headerValues['chipSize'][0]
                                )
                            )
                        ) {
                            // check version
                            $highestVersion = $this->deviceRepository->getHighestVersion($device);
                            if ($highestVersion->getVersion() > $headerValues['version'][0]) {
                                $filePath = $this->deviceVersionRepository->getDeviceVersionImagePath($device, $highestVersion);
                                // send binary image - the old way ... quite short and sweet ;)
                                header("HTTP/1.1 200 OK");
                                header("Content-Type: application/octet-stream");
                                header("Content-Transfer-Encoding: Binary");
                                header("Content-Length:" . filesize($filePath));
                                readfile($filePath);
                                exit;
                            } else {
                                // send 304 Not Modified
                                return $response->withStatus(304);
                            }
                        } // else would be 401 Not Authorized ... see below
                    }
                    // send 401 Not Authorized
                    return $response->withStatus(401);
                } // else would be 404 Not Found ... see below
            } catch (\Exception $ex) { // schematically invalid mac-address
                // send 400 Bad Request
                return $response->withStatus(400);
            }
        }
        // send 404 Not Found
        return $response->withStatus(404);
    }

    /**
     * @param Request $request
     * @return array
     */
    private function getRelevantHeaderValues(Request $request) {
        $headerValues = array();
        $headerValues['staMac'] = $request->getHeader('x-ESP8266-STA-MAC');
        $headerValues['apMac'] = $request->getHeader('x-ESP8266-AP-MAC');
        $headerValues['chipSize'] = $request->getHeader('x-ESP8266-chip-size');
        $headerValues['version'] = $request->getHeader('x-ESP8266-version');

        return $headerValues;
    }

    /**
     * @param $headerValues array of request header fields
     * @param $args array framework request arguments as provided bei slim 3
     */
    private function isValidRequest($headerValues, $args) {
        return (
            count($headerValues['staMac']) >= 1 &&
            $headerValues['staMac'][0] === $args['staMac'] && // path of request must contain same STA-MAC!
            count($headerValues['apMac']) >= 1 &&
            count($headerValues['chipSize']) >= 1 &&
            count($headerValues['version']) >= 1
        );
    }
}
