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

    // Constructor
    public function __construct(ContainerInterface $ci) {
        $this->ci = $ci;
        $this->repository = new DeviceAuthentifications($this->ci->logger);
        $this->deviceRepository = new Devices($this->ci->logger);
        $this->deviceVersonRepository = new DeviceVersions($this->ci->logger);
    }

    /**
     * reqeusting authenticate with certain header infos about identity
     *
     * ! This method needs to be accessible only by BasicAuth !
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
     */
    public function authenticate(Request $request, Response $response, $args) {

        // validate
        $headerValuesStaMac = $request->getHeader('x-ESP8266-STA-MAC');
        $headerValuesApMac = $request->getHeader('x-ESP8266-AP-MAC');
        $headerValuesChipSize = $request->getHeader('x-ESP8266-chip-size');

        if (
            count($headerValuesStaMac) >= 1 &&
            $headerValuesStaMac[0] === $args['staMac'] && // path of request must contain same STA-MAC!
            count($headerValuesApMac) >= 1 &&
            count($headerValuesChipSize) >= 1
        ) {
            try {
                // save this authentification info
                $device = $this->deviceRepository->load($headerValuesStaMac[0]);
                if ($device->isExisting() && $device->isValid()) {

                    // @TODO: check if $this->repository->load($staMac) can be sufficient ...
                    $deviceAuthentification = new DeviceAuthentificationModel($device, $this->ci->logger);
                    $deviceAuthentification->setApMac($headerValuesApMac[0]);
                    $deviceAuthentification->setChipSize($headerValuesChipSize[0]);
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

        // send 404 Not Found in case nothing is working ;) (Should not been reached in any case ... )
        return $response->withStatus(404);
    }

    public function download(Request $request, Response $response, $args) {

        $headerValuesStaMac = $request->getHeader('x-ESP8266-STA-MAC');
        $headerValuesApMac = $request->getHeader('x-ESP8266-AP-MAC');
        $headerValuesChipSize = $request->getHeader('x-ESP8266-chip-size');
        $headerValuesVersion = $request->getHeader('x-ESP8266-version');

        if (
            count($headerValuesStaMac) >= 1 &&
            $headerValuesStaMac[0] === $args['staMac'] && // path of request must contain same STA-MAC!
            count($headerValuesApMac) >= 1 &&
            count($headerValuesChipSize) >= 1 &&
            count($headerValuesVersion) >= 1
        ) {
            try {
                // save this authentification info
                $device = $this->deviceRepository->load($headerValuesStaMac[0]);
                if ($device->isExisting() && $device->isValid()) {
                    $deviceAuthentification = $this->repository->load($headerValuesStaMac[0]);
                    if ($deviceAuthentification !== null) {
                        // check timestamp aso...
                        if (
                            $this->repository->authenticate(
                                $deviceAuthentification,
                                array(
                                    'staMac' => $headerValuesStaMac[0],
                                    'apMac' => $headerValuesApMac[0],
                                    'chipSize' => $headerValuesChipSize[0]
                                )
                            )
                        ) {
                            // check version
                            $versions = $device->getVersions();
                            usort($versions, function ($deviceVersionA, $deviceVersionB) {
                                return strcmp($deviceVersionA->getVersion(), $deviceVersionB->getVersion());
                            });
                            $highestVersion = array_pop(array_values($versions));

                            if ($highestVersion->getVersion() > $headerValuesVersion[0]) {
                                $filePath = $this->deviceVersonRepository->getDeviceVersionImagePath($device, $highestVersion);
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

                        } else {
                            // send 401 Not Authorized
                            return $response->withStatus(401);
                        }
                    } else {
                        // send 401 Not Authorized
                        return $response->withStatus(401);
                    }
                } else {
                    // send 404 Not Found
                    return $response->withStatus(404);
                }
            } catch (\Exception $ex) { // schematically invalid mac-address
                // send 400 Bad Request
                return $response->withStatus(400);
            }
        }
    }

}
