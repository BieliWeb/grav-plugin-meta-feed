<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Events\FlexRegisterEvent;
use Twig\TwigFunction;

class MetaFeedPlugin extends Plugin
{
    public $features = [
        'blueprints' => 0,
    ];

    public static function getSubscribedEvents(): array
    {
        return [
            'onPluginsInitialized'   => [['onPluginsInitialized', 0]],
            'onSchedulerInitialized' => [['onSchedulerInitialized', 0]],
            FlexRegisterEvent::class => [['onRegisterFlex', 0]],
        ];
    }

    public function onPluginsInitialized(): void
    {
        $this->registerAutoloader();

        if ($this->isAdmin()) {
            return;
        }

        $this->enable([
            'onTwigInitialized' => ['onTwigInitialized', 0],
        ]);
    }

    public function onRegisterFlex($event): void
    {
        $event->flex->addDirectoryType('post', 'blueprints://flex-objects/post.yaml');
    }

    public function onTwigInitialized(): void
    {
        $this->grav['twig']->twig()->addFunction(
            new TwigFunction('meta_feed_posts', [$this, 'getPostsForTwig'])
        );
    }

    public function onSchedulerInitialized($event): void
    {
        $scheduler = $event['scheduler'];

        $scheduler->addFunction(
            'meta-feed.fetch',
            function (): void {
                $this->registerAutoloader();
                (new \Grav\Plugin\MetaFeed\PostFetcher($this->grav))->fetchAll();
            },
            '0 6 * * *',
            'Meta Feed: Fetch posts from Meta API'
        );

        $scheduler->addFunction(
            'meta-feed.refresh-tokens',
            function (): void {
                $this->registerAutoloader();
                (new \Grav\Plugin\MetaFeed\TokenManager($this->grav))->refreshAll();
            },
            '0 5 * * *',
            'Meta Feed: Refresh expiring tokens'
        );
    }

    public function getPostsForTwig(string $provider = '', int $limit = 0): array
    {
        $flex = $this->grav['flex'] ?? null;
        if (!$flex) {
            return [];
        }

        $directory = $flex->getDirectory('post');
        if (!$directory) {
            return [];
        }

        $posts = [];
        foreach ($directory->getCollection() as $post) {
            if (!(bool)($post['active'] ?? true)) {
                continue;
            }
            if ($provider !== '' && ($post['provider'] ?? '') !== $provider) {
                continue;
            }
            $posts[] = $post;
        }

        usort($posts, static fn($a, $b) => strcmp(
            (string)($b['published_at'] ?? ''),
            (string)($a['published_at'] ?? '')
        ));

        return $limit > 0 ? array_slice($posts, 0, $limit) : $posts;
    }

    private function registerAutoloader(): void
    {
        static $registered = false;
        if ($registered) {
            return;
        }
        $registered = true;

        spl_autoload_register(function (string $class): void {
            $prefix = 'Grav\\Plugin\\MetaFeed\\';
            if (!str_starts_with($class, $prefix)) {
                return;
            }
            $file = __DIR__ . '/classes/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (file_exists($file)) {
                require_once $file;
            }
        });
    }
}
