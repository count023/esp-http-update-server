# A simple php application as counterpart of Esp-HTTP-Update with some security manners

!! Work in Progress !!

## TODOs
- ~~write custom Authenticator for tuupola/slim-basic-auth: passes for users and devices should be saved encrypted~~ Done :white_check_mark:
- ~~write DeviceAuthRequest: Should validate the request header fields and save a temporary available auth-file~~ Done :white_check_mark:
- ~~write DeviceUpdater: validates the Authentification and provide the highest version available if greater than the current software version of the Device~~ Done :white_check_mark:

## Based on Slim Framework 3 Skeleton Application

Use this skeleton application to quickly setup and start working on a new Slim Framework 3 application. This application uses the latest Slim 3 with the PHP-View template renderer. It also uses the Monolog logger.

This skeleton application was built for Composer. This makes setting up a new Slim Framework application quick and easy.

### Install the Application

Run this command from the directory in which you want to install your new Slim Framework application.

    php composer.phar create-project slim/slim-skeleton [my-app-name]

Replace `[my-app-name]` with the desired directory name for your new application. You'll want to:

* Point your virtual host document root to your new application's `public/` directory.
* Ensure `logs/` is web writeable.

That's it! Now go build something cool.
