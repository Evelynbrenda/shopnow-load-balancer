<?php

require_once __DIR__ . '/../balanceador/RoundRobin.php';
require_once __DIR__ . '/../balanceador/LeastConnections.php';

$passed = 0;
$failed = 0;

function test(string $name, callable $callback): void
{
    global $passed, $failed;

    try {
        $callback();
        echo "[OK] $name\n";
        $passed++;
    } catch (Throwable $e) {
        echo "[FALHOU] $name: " . $e->getMessage() . "\n";
        $failed++;
    }
}

function assertSameValue($expected, $actual): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            "Esperado: " . var_export($expected, true) .
            " | Recebido: " . var_export($actual, true)
        );
    }
}

$servers = ['Servidor 1', 'Servidor 2', 'Servidor 3'];

test('Round Robin seleciona o primeiro servidor', function () use ($servers) {
    assertSameValue('Servidor 1', RoundRobin::choose($servers, 0));
});

test('Round Robin percorre todos os servidores', function () use ($servers) {
    $expected = ['Servidor 1', 'Servidor 2', 'Servidor 3'];

    foreach ($expected as $index => $server) {
        assertSameValue($server, RoundRobin::choose($servers, $index));
    }
});

test('Round Robin reinicia a sequencia', function () use ($servers) {
    assertSameValue('Servidor 1', RoundRobin::choose($servers, 3));
});

test('Least Connections escolhe o servidor com menos conexoes', function () use ($servers) {
    $connections = [
        'Servidor 1' => 5,
        'Servidor 2' => 1,
        'Servidor 3' => 3
    ];

    assertSameValue(
        'Servidor 2',
        LeastConnections::choose($servers, $connections)
    );
});

test('Least Connections escolhe o primeiro em caso de empate', function () use ($servers) {
    $connections = [
        'Servidor 1' => 2,
        'Servidor 2' => 2,
        'Servidor 3' => 2
    ];

    assertSameValue(
        'Servidor 1',
        LeastConnections::choose($servers, $connections)
    );
});

test('Least Connections considera zero para servidor sem contador', function () use ($servers) {
    assertSameValue(
        'Servidor 1',
        LeastConnections::choose($servers, [])
    );
});

echo "\nResultado: $passed aprovados; $failed falharam.\n";

exit($failed > 0 ? 1 : 0);
