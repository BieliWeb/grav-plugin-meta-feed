<?php
declare(strict_types=1);

namespace Grav\Plugin\MetaFeed;

/**
 * Minimal PSR-4 autoloader for the plugin's classes/ directory.
 * Used instead of Composer, since the plugin has no external dependencies.
 */
final class Autoloader
{
    private const PREFIX = 'Grav\\Plugin\\MetaFeed\\';

    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;

        $baseDir = \dirname(__DIR__) . '/classes/';

        spl_autoload_register(static function (string $class) use ($baseDir): void {
            if (!str_starts_with($class, self::PREFIX)) {
                return;
            }
            $file = $baseDir . str_replace('\\', '/', substr($class, \strlen(self::PREFIX))) . '.php';
            if (is_file($file)) {
                require_once $file;
            }
        });
    }
}
