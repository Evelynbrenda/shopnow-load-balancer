<?php

require_once __DIR__ . '/../balanceador/RoundRobin.php';
require_once __DIR__ . '/../balanceador/LeastResponseTime.php';
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

test('Least Connections alterna servidores em caso de empate', function () use ($servers) {
    $connections = [
        'Servidor 1' => 2,
        'Servidor 2' => 2,
        'Servidor 3' => 2
    ];

    $expected = ['Servidor 1', 'Servidor 2', 'Servidor 3'];

    foreach ($expected as $index => $server) {
        assertSameValue(
            $server,
            LeastConnections::choose($servers, $connections, $index)
        );
    }
});

test('Least Connections prioriza o menor número de conexões mesmo com índice rotativo', function () use ($servers) {
    $connections = [
        'Servidor 1' => 5,
        'Servidor 2' => 1,
        'Servidor 3' => 3
    ];

    assertSameValue(
        'Servidor 2',
        LeastConnections::choose($servers, $connections, 2)
    );
});

test('Least Connections considera zero para servidor sem contador', function () use ($servers) {
    assertSameValue(
        'Servidor 1',
        LeastConnections::choose($servers, [])
    );
});


test('Least Response Time escolhe o servidor com menor tempo medio', function () use ($servers) {
    $responseTimes = [
        'Servidor 1' => 120.0,
        'Servidor 2' => 45.0,
        'Servidor 3' => 90.0
    ];

    assertSameValue(
        'Servidor 2',
        LeastResponseTime::choose($servers, $responseTimes)
    );
});

test('Least Response Time prioriza servidores ainda nao medidos', function () use ($servers) {
    $responseTimes = [
        'Servidor 1' => 20.0
    ];

    assertSameValue(
        'Servidor 2',
        LeastResponseTime::choose($servers, $responseTimes)
    );
});

test('Least Response Time alterna servidores em caso de empate', function () use ($servers) {
    $responseTimes = [
        'Servidor 1' => 50.0,
        'Servidor 2' => 50.0,
        'Servidor 3' => 80.0
    ];

    assertSameValue(
        'Servidor 1',
        LeastResponseTime::choose($servers, $responseTimes, 0)
    );

    assertSameValue(
        'Servidor 2',
        LeastResponseTime::choose($servers, $responseTimes, 1)
    );
});

test('Least Response Time atualiza a media movel', function () {
    $average = LeastResponseTime::updateAverage(100.0, 200.0);

    assertSameValue(130.0, $average);
});

echo "\nResultado: $passed aprovados; $failed falharam.\n";

exit($failed > 0 ? 1 : 0);
