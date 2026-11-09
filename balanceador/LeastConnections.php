<?php
class LeastConnections
{
    public static function choose(
        array $healthyServers,
        array $connections,
        int $tieBreakIndex = 0
    ): string {
        if (empty($healthyServers)) {
            throw new InvalidArgumentException('Nenhum servidor disponível.');
        }

        $minimum = PHP_INT_MAX;
        $candidates = [];

        foreach ($healthyServers as $server) {
            $activeConnections = $connections[$server] ?? 0;

            if ($activeConnections < $minimum) {
                $minimum = $activeConnections;
                $candidates = [$server];
            } elseif ($activeConnections === $minimum) {
                $candidates[] = $server;
            }
        }

        $position = $tieBreakIndex % count($candidates);

        return $candidates[$position];
    }
}