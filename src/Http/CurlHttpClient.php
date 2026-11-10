<?php

declare(strict_types=1);

namespace SlimKiller\Http;

final class CurlHttpClient implements HttpClient
{
    public function get(string $url, array $headers = [], int $timeoutSeconds = 20): Response
    {
        return $this->send('GET', $url, $headers, null, $timeoutSeconds);
    }

    public function send(string $method, string $url, array $headers = [], ?string $body = null, int $timeoutSeconds = 20): Response
    {
        $lines = ['User-Agent: slim-killer'];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        $handle = curl_init($url);
        if ($handle === false) {
            return new Response(0, '', 'curl could not start');
        }
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => $method === 'GET',
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_HTTPHEADER => $lines,
            CURLOPT_CUSTOMREQUEST => $method !== '' ? $method : 'GET',
        ];
        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }
        curl_setopt_array($handle, $options);
        $response = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);

        return new Response($status, is_string($response) ? $response : '', $error);
    }
}
