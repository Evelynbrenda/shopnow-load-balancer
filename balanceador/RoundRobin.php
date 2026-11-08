<?php
class RoundRobin
{
    public static function choose(array $healthyServers, int $nextIndex): string
    {
        return $healthyServers[$nextIndex % count($healthyServers)];
    }
}
