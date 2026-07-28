<?php
declare(strict_types=1);

namespace Grav\Plugin\MetaFeed\Traits;

trait HttpClientTrait
{
    private function httpGet(string $url): array
    {
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('MetaFeed requires the PHP cURL extension.');
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException('HTTP request failed: ' . $error);
        }

        $data = json_decode((string)$response, true);

        if (isset($data['error'])) {
            throw new \RuntimeException($data['error']['message'] ?? 'Unknown Meta API error');
        }

        return (array)$data;
    }
}
