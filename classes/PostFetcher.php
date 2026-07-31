<?php
declare(strict_types=1);

namespace Grav\Plugin\MetaFeed;

use Grav\Common\Cache;
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

    /**
     * @return array{added: int, errors: string[]}
     */
    public function fetchAll(): array
    {
        $config   = $this->grav['config']->get('plugins.meta-feed');
        $accounts = $config['accounts'] ?? [];
        $limit    = (int)($config['fetch_limit'] ?? 20);

        $added  = 0;
        $errors = [];

        $directory = $this->grav['flex']->getDirectory('post');
        if (!$directory) {
            $message = 'Flex directory "post" not found.';
            $this->grav['log']->error('MetaFeed: ' . $message);

            return ['added' => 0, 'errors' => [$message]];
        }

        foreach ($accounts as $account) {
            try {
                $this->validateAccount($account);

                $token    = $this->resolveToken($account, $accounts);
                $rawPosts = $account['platform'] === 'facebook'
                    ? $this->api->fetchFacebookPosts($account['page_id'], $token, $limit)
                    : $this->api->fetchInstagramPosts($account['page_id'], $token, $limit);

                foreach ($rawPosts as $rawPost) {
                    if ($this->savePost($directory, $account, $rawPost)) {
                        $added++;
                    }
                }
            } catch (\Exception $e) {
                $errors[] = $this->label($account) . ': ' . $e->getMessage();
                $this->grav['log']->error('MetaFeed: Fetch failed for ' . $this->label($account) . ': ' . $e->getMessage());
            }
        }

        if ($added > 0) {
            $this->invalidatePageCache();
        }

        return ['added' => $added, 'errors' => $errors];
    }

    /**
     * Grav's page cache only watches the pages folder, so new posts stored under
     * user/data would otherwise stay invisible until the cache expires on its own.
     *
     * Clearing the cache driver alone is not enough: the cache key is derived from
     * the request root URL, so a CLI or scheduler run addresses a different
     * namespace than the web requests it needs to invalidate. Grav's own
     * clearCache() also touches the user config, which changes the config key and
     * therefore invalidates every namespace. Processed images survive unless
     * system.cache.clear_images_by_default is enabled.
     *
     * This only runs when posts were actually added, so a quiet feed never
     * triggers it.
     */
    private function invalidatePageCache(): void
    {
        try {
            Cache::clearCache('standard');
        } catch (\Throwable $e) {
            $this->grav['log']->warning('MetaFeed: Could not invalidate page cache: ' . $e->getMessage());
        }
    }

    private function savePost(object $directory, array $account, array $rawPost): bool
    {
        $postId = $rawPost['id'] ?? '';
        if (!$postId) {
            return false;
        }

        $key = $account['platform'] . '-' . preg_replace('/[^a-zA-Z0-9]/', '-', $postId);

        // FolderStorage generates a random key unless the row carries a storage_key,
        // so the check has to go against the storage rather than the cached index.
        if ($directory->getStorage()->hasKey($key)) {
            return false;
        }

        // Create the object folder before saving so media can be placed there
        $objectFolder = $this->postsBasePath . '/' . $key;
        if (!is_dir($objectFolder)) {
            mkdir($objectFolder, 0755, true);
        }

        $mediaType = $rawPost['media_type'] ?? 'IMAGE';

        // Videos have no still image of their own; Meta exposes a poster frame as thumbnail_url.
        $displayUrl = $mediaType === 'VIDEO'
            ? ($rawPost['thumbnail_url'] ?? '')
            : ($rawPost['full_picture'] ?? $rawPost['media_url'] ?? '');

        $imageFile = $this->downloadMedia($displayUrl, $objectFolder, 'image');

        $object = $directory->createObject([
            'storage_key'    => $key,
            'active'         => true,
            'provider'       => $account['platform'],
            'post_id'        => $postId,
            'page_id'        => $account['page_id'],
            'message'        => $rawPost['message'] ?? $rawPost['caption'] ?? '',
            'image_file'     => $imageFile,
            'media_type'     => $mediaType,
            'link'           => $rawPost['permalink_url'] ?? $rawPost['permalink'] ?? '',
            'published_at'   => $rawPost['created_time'] ?? $rawPost['timestamp'] ?? '',
            'fetched_at'     => (new \DateTime())->format('Y-m-d H:i:s'),
        ], $key);

        $object->save();

        return true;
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
        if (!empty($account['use_shared_token']) && !empty($account['shared_token_page_id'])) {
            foreach ($accounts as $a) {
                if ((string)$a['page_id'] === (string)$account['shared_token_page_id']) {
                    return $a['access_token'];
                }
            }
            throw new \RuntimeException(
                'Shared token account "' . $account['shared_token_page_id'] . '" not found.'
            );
        }
        return $account['access_token'];
    }

    private function validateAccount(array $account): void
    {
        if (empty($account['page_id'])) {
            throw new \RuntimeException('Account is missing "page_id".');
        }

        $platform = $account['platform'] ?? '';
        if (!in_array($platform, ['facebook', 'instagram'], true)) {
            throw new \RuntimeException(
                'Account "' . $account['page_id'] . '" has an invalid "platform" value '
                . '(got "' . $platform . '", expected "facebook" or "instagram").'
            );
        }

        if (empty($account['access_token']) && empty($account['use_shared_token'])) {
            throw new \RuntimeException('Account "' . $account['page_id'] . '" is missing "access_token".');
        }
    }

    private function label(array $account): string
    {
        return $account['name'] ?? ($account['platform'] . ':' . ($account['page_id'] ?? '?'));
    }
}
