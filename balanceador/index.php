<?php
require_once __DIR__ . '/LoadBalancer.php';

$servers = ['http://servidor1', 'http://servidor2', 'http://servidor3'];
$algorithm = $_GET['algorithm'] ?? 'round_robin';
$allowed = ['round_robin', 'least_connections'];

if (!in_array($algorithm, $allowed, true)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Algoritmo inválido.', 'allowed' => $allowed]);
    exit;
}

$balancer = new LoadBalancer($servers);
$selected = $balancer->acquire($algorithm);

if ($selected === null) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Nenhum servidor disponível.', 'status' => 'unavailable']);
    exit;
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($uri, PHP_URL_PATH) ?: '/';
    $headers = [];
    foreach (getallheaders() as $name => $value) {
        if (!in_array(strtolower($name), ['host', 'connection', 'content-length'], true)) {
            $headers[] = $name . ': ' . $value;
        }
    }

    $context = stream_context_create(['http' => [
        'method' => $method,
        'header' => implode("\r\n", $headers),
        'content' => file_get_contents('php://input'),
        'timeout' => 10,
        'ignore_errors' => true
    ]]);

    $body = @file_get_contents($selected . $path, false, $context);
    if ($body === false) {
        http_response_code(502);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Falha ao consultar o servidor selecionado.']);
        exit;
    }

    foreach (($http_response_header ?? []) as $line) {
        if (preg_match('/^HTTP\/\S+\s+(\d+)/', $line, $matches)) {
            http_response_code((int)$matches[1]);
        } elseif (stripos($line, 'Content-Type:') === 0) {
            header($line);
        }
    }
    echo $body;
} finally {
    $balancer->release($selected);
}
