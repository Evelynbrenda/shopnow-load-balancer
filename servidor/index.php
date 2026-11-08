<?php
$serverId = getenv('SERVER_ID') ?: 'Servidor desconhecido';
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'application' => 'ShopNow',
    'server' => $serverId,
    'status' => 'online',
    'timestamp' => date('c')
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
