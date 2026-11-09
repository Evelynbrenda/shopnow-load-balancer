<?php

class HealthCheck
{
    public static function isHealthy(string $url): bool
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 2,
                'ignore_errors' => true
            ]
        ]);

        $headers = @get_headers($url . '/?health=1', false, $context);

        return $headers !== false
            && isset($headers[0])
            && str_contains($headers[0], '200');
    }
}
