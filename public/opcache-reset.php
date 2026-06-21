<?php

// Check if this is a deploy workflow call (with token) or a normal request
$token = $_GET['token'] ?? '';
$isDeployCall = ($token === 'op123');

if ($isDeployCall) {
    $result = opcache_reset();

    @unlink(__DIR__ . '/.user.ini');
    @unlink(__FILE__);

    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode([
        'opcache_reset' => $result,
        'worker' => getmypid(),
    ]);
    exit;
}

// Normal request: just clear opcache silently, then continue to index.php
@opcache_reset();
