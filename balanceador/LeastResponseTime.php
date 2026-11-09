<?php

class LeastResponseTime
{
    public static function choose(
        array $healthyServers,
        array $responseTimes,
        int $tieBreakIndex = 0
    ): string {
        if (empty($healthyServers)) {
            throw new InvalidArgumentException('Nenhum servidor disponível.');
        }

        // Prioriza servidores que ainda não possuem medição.
        $unmeasured = [];

        foreach ($healthyServers as $server) {
            if (!isset($responseTimes[$server])) {
                $unmeasured[] = $server;
            }
        }

        if (!empty($unmeasured)) {
            return $unmeasured[$tieBreakIndex % count($unmeasured)];
        }

        // Encontra a menor média de tempo de resposta.
        $minimum = PHP_FLOAT_MAX;
        $candidates = [];

        foreach ($healthyServers as $server) {
            $responseTime = $responseTimes[$server];

            if ($responseTime < $minimum) {
                $minimum = $responseTime;
                $candidates = [$server];
            } elseif ($responseTime === $minimum) {
                $candidates[] = $server;
            }
        }

        // Alterna entre servidores em caso de empate.
        return $candidates[$tieBreakIndex % count($candidates)];
    }

    public static function updateAverage(
        ?float $currentAverage,
        float $newResponseTime,
        float $alpha = 0.3
    ): float {
        if ($currentAverage === null) {
            return $newResponseTime;
        }

        return ($alpha * $newResponseTime)
            + ((1 - $alpha) * $currentAverage);
    }
}
