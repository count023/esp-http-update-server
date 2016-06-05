<?php
// Routes Api

// { requires auth(esp-thingy):

// POST /device/authenticate/{staMac} -> validates BasicAuth & (STA-Mac | AP-Mac)-Kombination & returns on success temporarily valid OAUTH2-Token (saved to disk)

$app->post('/device/authenticate/{staMac:[0-9a-fA-F\:]*}', '\\com\\gpioneers\\esp\\httpupload\\controllers\\DeviceAuthentification:authenticate');

// } //  requires auth


// public {

// GET /update -> requires temporarily available authorization
$app->get('/device/update/{staMac:[0-9a-fA-F\:]*}', '\\com\\gpioneers\\esp\\httpupload\\controllers\\DeviceAuthentification:download');

// } // public
