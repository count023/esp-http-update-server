<?php

// Settings to make all errors more obvious during testing
error_reporting(-1);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
date_default_timezone_set('UTC');


define('PROJECT_ROOT', realpath(__DIR__ . '/../..'));

define('DATA_DIR', PROJECT_ROOT . '/data-test/');

require_once PROJECT_ROOT . '/vendor/autoload.php';


/* End of file bootstrap.php */