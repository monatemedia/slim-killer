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

    public function download(string $url, array $headers, string $path, int $timeoutSeconds = 300): Response
    {
        $lines = ['User-Agent: slim-killer'];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }
        $file = @fopen($path, 'wb');
        if ($file === false) {
            return new Response(0, '', "could not write {$path}");
        }
        $handle = curl_init($url);
        if ($handle === false) {
            fclose($file);

            return new Response(0, '', 'curl could not start');
        }
        // GitHub answers a zip download with a redirect to a short-lived address. curl
        // does not send the Authorization header on to another host, which is right.
        curl_setopt_array($handle, [
            CURLOPT_FILE => $file,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_HTTPHEADER => $lines,
            CURLOPT_FAILONERROR => false,
        ]);
        curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        fclose($file);

        return new Response($status, '', $error);
    }
}
