<?php
namespace Grav\Plugin;

use Grav\Common\Grav;
use Grav\Common\Plugin;
use Grav\Common\Scheduler\Scheduler;
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
            'onTwigInitialized'    => ['onTwigInitialized', 0],
            'onTwigTemplatePaths'  => ['onTwigTemplatePaths', 0],
        ]);
    }

    public function onTwigTemplatePaths(): void
    {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
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
        /** @var Scheduler $scheduler */
        $scheduler = $event['scheduler'];
        $config    = $this->grav['config'];

        $job = $scheduler->addFunction(
            'Grav\Plugin\MetaFeedPlugin::fetchJob',
            [],
            'meta-feed-fetch'
        );
        $job->at($config->get('plugins.meta-feed.fetch_at', '0 * * * *'));
        $job->output('logs/meta-feed-fetch.out');
        $job->backlink('/plugins/meta-feed');

        $job = $scheduler->addFunction(
            'Grav\Plugin\MetaFeedPlugin::refreshTokensJob',
            [],
            'meta-feed-refresh-tokens'
        );
        $job->at($config->get('plugins.meta-feed.refresh_at', '0 5 * * 1'));
        $job->output('logs/meta-feed-refresh-tokens.out');
        $job->backlink('/plugins/meta-feed');
    }

    /**
     * Scheduler entry point. Runs in a separate process, so it cannot rely on
     * plugin instance state.
     */
    public static function fetchJob(): void
    {
        $grav = Grav::instance();
        self::bootstrap();
        (new \Grav\Plugin\MetaFeed\PostFetcher($grav))->fetchAll();
    }

    /**
     * Scheduler entry point. See fetchJob().
     */
    public static function refreshTokensJob(): void
    {
        $grav = Grav::instance();
        self::bootstrap();
        (new \Grav\Plugin\MetaFeed\TokenManager($grav))->refreshAll();
    }

    private static function bootstrap(): void
    {
        require_once __DIR__ . '/classes/Autoloader.php';
        \Grav\Plugin\MetaFeed\Autoloader::register();
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
        self::bootstrap();
    }
}
