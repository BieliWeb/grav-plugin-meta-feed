<?php
declare(strict_types=1);

namespace Grav\Plugin\MetaFeed;

use Grav\Common\Grav;
use Grav\Plugin\MetaFeed\Api\MetaGraphApi;

class PostFetcher
{
    private Grav $grav;
    private MetaGraphApi $api;
    private string $postsBasePath;

    public function __construct(Grav $grav)
    {
        $this->grav = $grav;
        $config = $grav['config']->get('plugins.meta-feed');
        $this->api = new MetaGraphApi($config['api_version'] ?? '21.0');
        $this->postsBasePath = $grav['locator']->findResource('user-data://meta-feed/posts', true, true);
    }

    public function fetchAll(): void
    {
        $config   = $this->grav['config']->get('plugins.meta-feed');
        $accounts = $config['accounts'] ?? [];
        $limit    = (int)($config['fetch_limit'] ?? 20);

        $directory = $this->grav['flex']->getDirectory('post');
        if (!$directory) {
            $this->grav['log']->error('MetaFeed: Flex directory "post" not found.');
            return;
        }

        foreach ($accounts as $account) {
            try {
                $token    = $this->resolveToken($account, $accounts);
                $rawPosts = $account['platform'] === 'facebook'
                    ? $this->api->fetchFacebookPosts($account['page_id'], $token, $limit)
                    : $this->api->fetchInstagramPosts($account['page_id'], $token, $limit);

                foreach ($rawPosts as $rawPost) {
                    $this->savePost($directory, $account, $rawPost);
                }
            } catch (\Exception $e) {
                $this->grav['log']->error('MetaFeed: Fetch failed for ' . ($account['id'] ?? '?') . ': ' . $e->getMessage());
            }
        }
    }

    private function savePost(object $directory, array $account, array $rawPost): void
    {
        $postId = $rawPost['id'] ?? '';
        if (!$postId) {
            return;
        }

        $key = $account['platform'] . '-' . preg_replace('/[^a-zA-Z0-9]/', '-', $postId);

        if ($directory->getObject($key) !== null) {
            return;
        }

        // Create the object folder before saving so media can be placed there
        $objectFolder = $this->postsBasePath . '/' . $key;
        if (!is_dir($objectFolder)) {
            mkdir($objectFolder, 0755, true);
        }

        $mediaType = $rawPost['media_type'] ?? 'IMAGE';
        $cdnUrl    = $rawPost['full_picture'] ?? $rawPost['media_url'] ?? '';
        $thumbUrl  = $rawPost['thumbnail_url'] ?? '';

        $imageFile = $this->downloadMedia(
            $mediaType === 'VIDEO' ? $thumbUrl : $cdnUrl,
            $objectFolder,
            'image'
        );
        $thumbnailFile = $mediaType === 'VIDEO'
            ? $this->downloadMedia($thumbUrl, $objectFolder, 'thumbnail')
            : '';

        $object = $directory->createObject([
            'active'         => true,
            'provider'       => $account['platform'],
            'post_id'        => $postId,
            'account_id'     => $account['id'],
            'message'        => $rawPost['message'] ?? $rawPost['caption'] ?? '',
            'image_file'     => $imageFile,
            'thumbnail_file' => $thumbnailFile,
            'media_type'     => $mediaType,
            'link'           => $rawPost['permalink_url'] ?? $rawPost['permalink'] ?? '',
            'published_at'   => $rawPost['created_time'] ?? $rawPost['timestamp'] ?? '',
            'fetched_at'     => (new \DateTime())->format('Y-m-d H:i:s'),
        ], $key);

        $object->save();
    }

    private function downloadMedia(string $url, string $folder, string $basename): string
    {
        if (empty($url)) {
            return '';
        }

        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 60,
                CURLOPT_FOLLOWLOCATION => true,
            ]);
            $content     = curl_exec($ch);
            $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);

            if (!$content) {
                return '';
            }

            $ext = match(true) {
                str_contains($contentType, 'jpeg') => 'jpg',
                str_contains($contentType, 'png')  => 'png',
                str_contains($contentType, 'gif')  => 'gif',
                str_contains($contentType, 'mp4')  => 'mp4',
                default                            => 'jpg',
            };

            $filename = $basename . '.' . $ext;
            file_put_contents($folder . '/' . $filename, $content);

            return $filename;
        } catch (\Exception $e) {
            $this->grav['log']->warning('MetaFeed: Media download failed: ' . $e->getMessage());
            return '';
        }
    }

    private function resolveToken(array $account, array $accounts): string
    {
        if (!empty($account['use_shared_token']) && !empty($account['shared_token_account'])) {
            foreach ($accounts as $a) {
                if ($a['id'] === $account['shared_token_account']) {
                    return $a['access_token'];
                }
            }
        }
        return $account['access_token'];
    }
}
