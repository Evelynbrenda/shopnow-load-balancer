<?php

$serverId = getenv('SERVER_ID') ?: 'Servidor desconhecido';

// Verificação de saúde: não aplica atraso artificial.
if (isset($_GET['health'])) {
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode([
        'server' => $serverId,
        'status' => 'online'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// Atraso artificial configurado pelo Docker.
$delayMs = (int) (getenv('RESPONSE_DELAY_MS') ?: 0);

if ($delayMs > 0) {
    usleep($delayMs * 1000);
}

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'application' => 'ShopNow',
    'server' => $serverId,
    'status' => 'online',
    'artificial_delay_ms' => $delayMs,
    'timestamp' => date('c')
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
