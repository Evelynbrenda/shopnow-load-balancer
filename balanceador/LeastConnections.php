<?php
class LeastConnections
{
    public static function choose(array $healthyServers, array $connections): string
    {
        $selected = $healthyServers[0];
        foreach ($healthyServers as $server) {
            if (($connections[$server] ?? 0) < ($connections[$selected] ?? 0)) {
                $selected = $server;
            }
        }
        return $selected;
    }
}
