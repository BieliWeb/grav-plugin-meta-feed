<?php
declare(strict_types=1);

namespace Grav\Plugin\MetaFeed;

use Grav\Common\Grav;
use Grav\Common\File\CompiledYamlFile;
use Grav\Plugin\MetaFeed\Traits\HttpClientTrait;

/**
 * Handles access token lifecycle.
 *
 * Supported token types:
 *   page     - Page Access Token derived from a long-lived user token. Does not expire.
 *   fb_user  - Long-lived Facebook user token (60 days). Refreshed via fb_exchange_token.
 *   ig_login - Instagram Login token (60 days). Refreshed via graph.instagram.com.
 */
class TokenManager
{
    use HttpClientTrait;

    private const REFRESH_THRESHOLD_DAYS = 15;

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
        $errors   = [];

        foreach ($accounts as &$account) {
            $type = $account['token_type'] ?? 'page';

            // Page tokens never expire.
            if ($type === 'page') {
                continue;
            }

            if (empty($account['token_expires']) || empty($account['access_token'])) {
                continue;
            }

            try {
                if (!$this->needsRefresh($account['token_expires'])) {
                    continue;
                }

                $result = $type === 'ig_login'
                    ? $this->refreshInstagramLoginToken($account['access_token'])
                    : $this->refreshFacebookUserToken($account);

                $account['access_token']  = $result['token'];
                $account['token_expires'] = $result['expires'];
                $updated = true;

                $this->grav['log']->info(
                    'MetaFeed: Token refreshed for ' . $this->label($account) . ', valid until ' . $result['expires']
                );
            } catch (\Exception $e) {
                $errors[] = $this->label($account) . ': ' . $e->getMessage();
                $this->grav['log']->error(
                    'MetaFeed: Token refresh failed for ' . $this->label($account) . ': ' . $e->getMessage()
                );
            }
        }
        unset($account);

        if ($updated) {
            $this->saveAccounts($accounts);
        }

        (new ErrorNotifier($this->grav))->notify('token refresh', $errors);
    }

    private function label(array $account): string
    {
        return $account['name'] ?? (($account['platform'] ?? '?') . ':' . ($account['page_id'] ?? '?'));
    }

    private function needsRefresh(string $expiryDate): bool
    {
        $expires  = new \DateTime($expiryDate);
        $daysLeft = (int)(new \DateTime())->diff($expires)->format('%r%a');

        return $daysLeft <= self::REFRESH_THRESHOLD_DAYS;
    }

    /**
     * Instagram Login tokens (graph.instagram.com), not used with the Facebook Login flow.
     */
    private function refreshInstagramLoginToken(string $token): array
    {
        $url = 'https://graph.instagram.com/refresh_access_token'
            . '?grant_type=ig_refresh_token'
            . '&access_token=' . urlencode($token);

        $response = $this->httpGet($url);

        return [
            'token'   => $response['access_token'],
            'expires' => $this->expiryFromResponse($response),
        ];
    }

    /**
     * Long-lived Facebook user tokens. Works for both Facebook Pages and
     * Instagram Business accounts accessed through the Facebook Login flow.
     */
    private function refreshFacebookUserToken(array $account): array
    {
        if (empty($account['app_id']) || empty($account['app_secret'])) {
            throw new \RuntimeException('App ID and App Secret are required to refresh a Facebook user token.');
        }

        $url = 'https://graph.facebook.com/oauth/access_token'
            . '?grant_type=fb_exchange_token'
            . '&client_id='         . urlencode($account['app_id'])
            . '&client_secret='     . urlencode($account['app_secret'])
            . '&fb_exchange_token=' . urlencode($account['access_token']);

        $response = $this->httpGet($url);

        return [
            'token'   => $response['access_token'],
            'expires' => $this->expiryFromResponse($response),
        ];
    }

    private function expiryFromResponse(array $response): string
    {
        $seconds = (int)($response['expires_in'] ?? 0);
        $days    = $seconds > 0 ? (int)floor($seconds / 86400) : 60;

        return (new \DateTime())->modify("+{$days} days")->format('Y-m-d');
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
