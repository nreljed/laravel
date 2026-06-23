<?php

// Check if this is a deploy workflow call (with token) or a normal request
$token = $_GET['token'] ?? '';
$isDeployCall = ($token === 'op123');

if (function_exists('opcache_reset')) {
    opcache_reset();
}

if ($isDeployCall) {
    @unlink(__DIR__.'/.user.ini');
    @unlink(__FILE__);

    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode([
        'opcache_reset' => true,
        'worker' => getmypid(),
    ]);
    exit;
} else {
    return;
}
