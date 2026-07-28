<?php
declare(strict_types=1);

namespace Grav\Plugin\MetaFeed;

use Grav\Common\Grav;
use Grav\Common\File\CompiledYamlFile;
use Grav\Plugin\MetaFeed\Traits\HttpClientTrait;

class TokenManager
{
    use HttpClientTrait;

    private Grav $grav;

    public function __construct(Grav $grav)
    {
        $this->grav = $grav;
    }

    public function refreshAll(): void
    {
        $config   = $this->grav['config']->get('plugins.meta-feed');
        $accounts = $config['accounts'] ?? [];
        $updated  = false;

        foreach ($accounts as &$account) {
            if (($account['token_type'] ?? 'page') === 'page') {
                continue;
            }
            if (empty($account['token_expires'])) {
                continue;
            }

            try {
                $expires  = new \DateTime($account['token_expires']);
                $daysLeft = (int)(new \DateTime())->diff($expires)->format('%r%a');

                if ($daysLeft <= 15) {
                    $result = $account['platform'] === 'instagram'
                        ? $this->refreshInstagramToken($account['access_token'])
                        : $this->refreshFacebookToken($account);

                    $account['access_token']  = $result['token'];
                    $account['token_expires'] = $result['expires'];
                    $updated = true;

                    $this->grav['log']->info('MetaFeed: Token refreshed for ' . $account['id']);
                }
            } catch (\Exception $e) {
                $this->grav['log']->error('MetaFeed: Token refresh failed for ' . ($account['id'] ?? '?') . ': ' . $e->getMessage());
            }
        }
        unset($account);

        if ($updated) {
            $this->saveAccounts($accounts);
        }
    }

    private function refreshInstagramToken(string $token): array
    {
        $url = 'https://graph.instagram.com/refresh_access_token'
            . '?grant_type=ig_refreshed_token'
            . '&access_token=' . urlencode($token);

        $response = $this->httpGet($url);

        return [
            'token'   => $response['access_token'],
            'expires' => (new \DateTime())->modify('+60 days')->format('Y-m-d'),
        ];
    }

    private function refreshFacebookToken(array $account): array
    {
        $url = 'https://graph.facebook.com/oauth/access_token'
            . '?grant_type=fb_exchange_token'
            . '&client_id='         . urlencode($account['app_id'] ?? '')
            . '&client_secret='     . urlencode($account['app_secret'] ?? '')
            . '&fb_exchange_token=' . urlencode($account['access_token']);

        $response = $this->httpGet($url);

        return [
            'token'   => $response['access_token'],
            'expires' => (new \DateTime())->modify('+60 days')->format('Y-m-d'),
        ];
    }

    private function saveAccounts(array $accounts): void
    {
        $path = $this->grav['locator']->findResource('user://config/plugins/meta-feed.yaml', true, true);
        $file = CompiledYamlFile::instance($path);
        $data = $file->content() ?: [];
        $data['accounts'] = $accounts;
        $file->content($data);
        $file->save();
    }
}
