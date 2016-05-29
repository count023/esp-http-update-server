<?php
// Routes Admin

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require __DIR__ . '/admin-users.php';

$app->add(new \Slim\Middleware\HttpBasicAuthentication([
    "path" => "/admin.*",
    "realm" => "Protected",
    "secure" => false, // allow http instead of https!
    "users" => $authorizedUsers
]));


// { requires auth(admin):

// GET /admin/list -> list of available images
$app->get('/admin', function (Request $request, Response $response) {

    $response->getBody()->write("Hallo Admin");

    $this->logger->addInfo("showed admin page");

    return $response;
});


$app->get('/admin/devices', '\\com\\gpioneers\\esp\\httpupload\\controllers\\Device:all');



// GET /admin/device/new -> show: form for info.txt
$app->get('/admin/device/new', '\\com\\gpioneers\\esp\\httpupload\\controllers\\Device:showForm');
// POST /admin/device/new -> validate AP-Mac & infoPurposeLocation => INSERT / UPDATE Data for specific ESP -> if UPDATE, remove all stored image.bin files and versions
$app->post('/admin/device/new', '\\com\\gpioneers\\esp\\httpupload\\controllers\\Device:create');

// GET /admin/device/{STA-Mac}(known)   -> show device info.txt of all versions available
// GET /admin/device/{STA-Mac}(unknown) -> redirect to new
$app->get('/admin/device/{staMac:[0-9a-f\:]*}', '\\com\\gpioneers\\esp\\httpupload\\controllers\\Device:read');

// GET /admin/device/{STA-Mac}/edit
$app->get('/admin/device/{staMac:[0-9a-f\:]*}/edit', '\\com\\gpioneers\\esp\\httpupload\\controllers\\Device:showForm');
// POST /admin/device/{STA-Mac}/edit -> validate AP-Mac & infoPurposeLocation => INSERT / UPDATE Data for specific ESP -> if UPDATE, remove all stored image.bin files and versions
$app->post('/admin/device/{staMac:[0-9a-f\:]*}/edit', '\\com\\gpioneers\\esp\\httpupload\\controllers\\Device:update');

// DELETE /admin/device/{STA-Mac} -> deletes mac-address folder and version folders and contained image.bin files
$app->delete('/admin/device/{staMac:[0-9a-f\:]*}', '\\com\\gpioneers\\esp\\httpupload\\controllers\\Device:delete');
// POST /admin/device/{STA-Mac}/delete -> deletes mac-address folder and version folders and contained image.bin files
$app->post('/admin/device/{staMac:[0-9a-f\:]*}/delete', '\\com\\gpioneers\\esp\\httpupload\\controllers\\Device:delete');



// GET /admin/{STA-Mac}/version/new show form for create
$app->get('/admin/device/{staMac:[0-9a-f\:]*}/version/new', '\\com\\gpioneers\\esp\\httpupload\\controllers\\DeviceVersion:showForm');
// POST /admin/{STA-Mac}/version/new -> validate AP-Mac & infoPurposeLocation => INSERT / UPDATE Data for specific ESP -> if UPDATE, remove all stored image.bin files and versions
$app->post('/admin/device/{staMac:[0-9a-f\:]*}/version/new', '\\com\\gpioneers\\esp\\httpupload\\controllers\\DeviceVersion:create');

// GET /admin/{STA-Mac}/version/{version}(known) show: infoPurposeLocation.txt
// GET /admin/{STA-Mac}/version/{version}(unknown) redirect to new
$app->get('/admin/device/{staMac:[0-9a-f\:]*}/version/{version:[0-9\.]*}', '\\com\\gpioneers\\esp\\httpupload\\controllers\\DeviceVersion:read');

// GET /admin/device/{STA-Mac}/version/{version}/edit
$app->get('/admin/device/{staMac:[0-9a-f\:]*}/version/{version:[0-9\.]*}/edit', '\\com\\gpioneers\\esp\\httpupload\\controllers\\DeviceVersion:showForm');
// POST /admin/device/{STA-Mac}/version/{version}/edit -> validate AP-Mac & infoPurposeLocation => INSERT / UPDATE Data for specific ESP -> if UPDATE, remove all stored image.bin files and versions
$app->post('/admin/device/{staMac:[0-9a-f\:]*}/version/{version:[0-9\.]*}/edit', '\\com\\gpioneers\\esp\\httpupload\\controllers\\DeviceVersion:update');

// DELETE /admin/device/{STA-Mac}/version/{version} -> deletes image.bin, info.json and version folder
$app->delete('/admin/device/{staMac:[0-9a-f\:]*}/version/{version:[0-9\.]*}', '\\com\\gpioneers\\esp\\httpupload\\controllers\\DeviceVersion:delete');
// POST /admin/device/{STA-Mac}/version/{version}/delete -> deletes mac-address folder and version folders and contained image.bin files
$app->post('/admin/device/{staMac:[0-9a-f\:]*}/version/{version:[0-9\.]*}/delete', '\\com\\gpioneers\\esp\\httpupload\\controllers\\DeviceVersion:delete');


// } //  requires auth
