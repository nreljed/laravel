<?php

$token = $_GET['token'] ?? '';

if ($token !== 'op123') {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$result = opcache_reset();

@unlink(__FILE__);

http_response_code(200);
header('Content-Type: application/json');
echo json_encode([
    'opcache_reset' => $result,
    'worker' => getmypid(),
]);
