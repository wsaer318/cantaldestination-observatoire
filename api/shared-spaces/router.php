<?php

require_once __DIR__ . '/../config/app.php';
require_once BASE_PATH . '/config/session_config.php';

use App\Core\Request;

$app = require BASE_PATH . '/app/bootstrap.php';

$request = Request::fromGlobals('/api');
$response = $app->handle($request);
$response->send();
