<?php
declare(strict_types=1);

namespace Grav\Plugin\MetaFeed;

use Grav\Common\Grav;

/**
 * Sends an error mail, at most once per error type (context) and configured interval.
 */
class ErrorNotifier
{
    private const STATE_FILE = 'user-data://meta-feed/notify-state.json';

    private Grav $grav;

    public function __construct(Grav $grav)
    {
        $this->grav = $grav;
    }

    /**
     * @param string[] $errors
     */
    public function notify(string $context, array $errors): void
    {
        if (!$errors) {
            return;
        }

        $config = $this->grav['config']->get('plugins.meta-feed');
        $to     = trim((string)($config['notify_email'] ?? ''));
        if ($to === '') {
            return;
        }

        $intervalHours = max(0, (int)($config['notify_interval_hours'] ?? 24));
        $statePath     = $this->grav['locator']->findResource(self::STATE_FILE, true, true);

        if ($this->isThrottled($statePath, $context, $intervalHours)) {
            return;
        }

        if (!isset($this->grav['Email'])) {
            $this->grav['log']->warning('MetaFeed: Cannot send error mail, email plugin is not available.');

            return;
        }

        $from = (string)$this->grav['config']->get('plugins.email.from', $to);

        $body = sprintf(
            '<p>Problem in Meta Feed plugin (%s)</p><p>Site: %s</p><ul>%s</ul><p>Further errors within %d h are not mailed.</p>',
            htmlspecialchars($context, ENT_QUOTES),
            htmlspecialchars((string)$this->grav['uri']->rootUrl(true), ENT_QUOTES),
            implode('', array_map(
                static fn(string $e): string => '<li>' . htmlspecialchars($e, ENT_QUOTES) . '</li>',
                $errors
            )),
            $intervalHours
        );

        try {
            $email   = $this->grav['Email'];
            $message = $email->message('Meta Feed problem', $body, 'text/html')
                ->setFrom($from)
                ->setTo($to);

            if ($email->send($message) < 1) {
                $this->grav['log']->warning('MetaFeed: Error mail was not accepted by the mailer.');

                return;
            }
        } catch (\Throwable $e) {
            $this->grav['log']->warning('MetaFeed: Could not send error mail: ' . $e->getMessage());

            return;
        }

        $this->markSent($statePath, $context);
    }

    private function readState(string $statePath): array
    {
        if (!is_file($statePath)) {
            return [];
        }

        $state = json_decode((string)file_get_contents($statePath), true);

        return \is_array($state) ? $state : [];
    }

    private function isThrottled(string $statePath, string $context, int $intervalHours): bool
    {
        $last = (int)($this->readState($statePath)[$context] ?? 0);

        return $last > 0 && (time() - $last) < $intervalHours * 3600;
    }

    private function markSent(string $statePath, string $context): void
    {
        $dir = \dirname($statePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $state           = $this->readState($statePath);
        $state[$context] = time();

        file_put_contents($statePath, json_encode($state), LOCK_EX);
    }
}
