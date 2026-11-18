<?php
declare(strict_types=1);

namespace Grav\Plugin\MetaFeed;

use Grav\Common\Grav;

/**
 * Keeps the most recent fetch and token refresh runs for the admin overview.
 */
class ActivityLog
{
    private const FILE        = 'user-data://meta-feed/activity.json';
    private const MAX_ENTRIES = 100;

    private string $path;

    public function __construct(Grav $grav)
    {
        $this->path = $grav['locator']->findResource(self::FILE, true, true);
    }

    /**
     * @param string $type    "fetch" or "token_refresh"
     * @param string $message Short result description
     */
    public function record(string $type, bool $success, string $message): void
    {
        $entries = $this->all();
        array_unshift($entries, [
            'time'    => time(),
            'type'    => $type,
            'success' => $success,
            'message' => $message,
        ]);

        $dir = \dirname($this->path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents($this->path, json_encode(array_slice($entries, 0, self::MAX_ENTRIES)), LOCK_EX);
    }

    /**
     * @return array<int, array{time: int, type: string, success: bool, message: string}>
     */
    public function all(): array
    {
        if (!is_file($this->path)) {
            return [];
        }

        $entries = json_decode((string)file_get_contents($this->path), true);

        return \is_array($entries) ? $entries : [];
    }
}
