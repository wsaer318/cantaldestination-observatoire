<?php

$query = $_SERVER['QUERY_STRING'] ?? '';
$uri = '/api/v2/infographie/regions-excursionnistes' . ($query ? '?' . $query : '');
$_SERVER['REQUEST_URI'] = $uri;
require __DIR__ . '/../v2/index.php';
