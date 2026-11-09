<?php
require_once __DIR__ . '/HealthCheck.php';
require_once __DIR__ . '/RoundRobin.php';
require_once __DIR__ . '/LeastConnections.php';
require_once __DIR__ . '/LeastResponseTime.php';

class LoadBalancer
{
    private string $stateFile;

    public function __construct(private array $servers)
    {
        $this->stateFile = sys_get_temp_dir() . '/shopnow_lb_state.json';
    }

    private function withLockedState(callable $callback)
    {
        $handle = fopen($this->stateFile, 'c+');

        if ($handle === false) {
            throw new RuntimeException('Não foi possível abrir o arquivo de estado.');
        }

        flock($handle, LOCK_EX);

        $contents = stream_get_contents($handle);
        $state = json_decode($contents ?: '{}', true);

        if (!is_array($state)) {
            $state = [];
        }

        if (!isset($state['connections']) || !is_array($state['connections'])) {
            $state['connections'] = [];
        }

        if (!isset($state['response_times']) || !is_array($state['response_times'])) {
            $state['response_times'] = [];
        }

        $state['next_index'] ??= 0;

        foreach ($this->servers as $server) {
            $state['connections'][$server] ??= 0;
        }

        try {
            $result = $callback($state);

            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode($state));
            fflush($handle);

            return $result;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public function acquire(string $algorithm): ?string
    {
        $healthy = [];

        foreach ($this->servers as $server) {
            if (HealthCheck::isHealthy($server)) {
                $healthy[] = $server;
            }
        }

        if (!$healthy) {
            return null;
        }

        return $this->withLockedState(function (&$state) use ($algorithm, $healthy) {
            if ($algorithm === 'least_connections') {
                $selected = LeastConnections::choose(
                    $healthy,
                    $state['connections'],
                    (int) $state['next_index']
                );
            } elseif ($algorithm === 'least_response_time') {
                $selected = LeastResponseTime::choose(
                    $healthy,
                    $state['response_times'],
                    (int) $state['next_index']
                );
            } else {
                $selected = RoundRobin::choose(
                    $healthy,
                    (int) $state['next_index']
                );
            }

            $state['next_index']++;
            $state['connections'][$selected]++;

            return $selected;
        });
    }

    public function recordResponseTime(string $server, float $milliseconds): void
    {
        $this->withLockedState(function (&$state) use ($server, $milliseconds) {
            $currentAverage = $state['response_times'][$server] ?? null;

            $state['response_times'][$server] =
                LeastResponseTime::updateAverage(
                    $currentAverage !== null ? (float) $currentAverage : null,
                    max(0, $milliseconds)
                );

            return null;
        });
    }

    public function release(string $server): void
    {
        $this->withLockedState(function (&$state) use ($server) {
            $state['connections'][$server] = max(
                0,
                ($state['connections'][$server] ?? 1) - 1
            );

            return null;
        });
    }
}
