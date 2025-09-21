<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once BASE_PATH . '/config/session_config.php';

use App\Core\Request;
use App\Core\Response;

$app = require BASE_PATH . '/app/bootstrap.php';

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($requestMethod === 'OPTIONS') {
    $response = (new Response('', 204))
        ->withHeaders([
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, PATCH, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, X-CSRF-Token, X-Api-Token',
        ]);
    $response->send();
    return;
}

$request = Request::fromGlobals('/api/v2');
$response = $app->handle($request);
$response->send();
