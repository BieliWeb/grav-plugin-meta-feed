<?php
declare(strict_types=1);

namespace Grav\Plugin\MetaFeed\Api;

use Grav\Plugin\MetaFeed\Traits\HttpClientTrait;

class MetaGraphApi
{
    use HttpClientTrait;

    private string $apiVersion;

    public function __construct(string $apiVersion = '21.0')
    {
        $this->apiVersion = $apiVersion;
    }

    public function fetchFacebookPosts(string $pageId, string $accessToken, int $limit = 20): array
    {
        $fields = 'id,message,full_picture,permalink_url,created_time';
        $url = "https://graph.facebook.com/v{$this->apiVersion}/{$pageId}/posts"
            . "?fields={$fields}&limit={$limit}&access_token=" . urlencode($accessToken);

        $response = $this->httpGet($url);
        return $response['data'] ?? [];
    }

    public function fetchInstagramPosts(string $userId, string $accessToken, int $limit = 20): array
    {
        $fields = 'id,caption,media_type,media_url,thumbnail_url,permalink,timestamp';
        $url = "https://graph.facebook.com/v{$this->apiVersion}/{$userId}/media"
            . "?fields={$fields}&limit={$limit}&access_token=" . urlencode($accessToken);

        $response = $this->httpGet($url);
        return $response['data'] ?? [];
    }
}
