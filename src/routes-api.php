<?php
// Routes Api

// { requires auth(esp-thingy):

// POST /authenticate -> validates BasicAuth & (STA-Mac | AP-Mac)-Kombination & returns on success temporarily valid OAUTH2-Token (saved to disk)

$app->post('/device/authenticate/{staMac:[0-9a-fA-F\:]*}', '\\com\\gpioneers\\esp\\httpupload\\controllers\\DeviceAuthentification:authenticate');

// } //  requires auth


// public {

// GET /download -> requires temporarily available athorization
$app->get('/device/{staMac:[0-9a-fA-F\:]*}/download', '\\com\\gpioneers\\esp\\httpupload\\controllers\\DeviceAuthentification:download');

// } // public
