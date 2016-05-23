<?php
// Routes

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

/*

  Data & storage directory:
    ../data/{STA-Mac}/ - infoPurposeLocation.txt
                       - token.txt
                      {version}/image.bin

*/

/**
 * redirect all URLs that end in a / to the non-trailing / equivalent
 * http://www.slimframework.com/docs/cookbook/route-patterns.html
 */
$app->add(function (Request $request, Response $response, callable $next) {
    $uri = $request->getUri();
    $path = $uri->getPath();
    if ($path != '/' && substr($path, -1) == '/') {
        // permanently redirect paths with a trailing slash
        // to their non-trailing counterpart
        $uri = $uri->withPath(substr($path, 0, -1));
        return $response->withRedirect((string)$uri, 301);
    }

    return $next($request, $response);
});





// GET / -> menu & description

require __DIR__ . '/routes-admin.php';

require __DIR__ . '/routes-api.php';
