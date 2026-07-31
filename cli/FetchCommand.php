<?php
declare(strict_types=1);

namespace Grav\Plugin\Console;

use Grav\Common\Grav;
use Grav\Console\ConsoleCommand;
use Grav\Plugin\MetaFeed\PostFetcher;

class FetchCommand extends ConsoleCommand
{
    protected function configure(): void
    {
        $this
            ->setName('fetch')
            ->setDescription('Fetch posts from all configured Meta accounts');
    }

    protected function serve(): int
    {
        require_once \dirname(__DIR__) . '/classes/Autoloader.php';
        \Grav\Plugin\MetaFeed\Autoloader::register();

        $grav = Grav::instance();
        $grav['config']->init();
        $grav->fireEvent('onPluginsInitialized');

        $this->output->writeln('<info>Fetching Meta posts...</info>');

        try {
            $result = (new PostFetcher($grav))->fetchAll();
        } catch (\Exception $e) {
            $this->output->writeln('<red>Error: ' . $e->getMessage() . '</red>');
            return 1;
        }

        foreach ($result['errors'] as $error) {
            $this->output->writeln('<red>' . $error . '</red>');
        }

        $this->output->writeln(sprintf('<green>%d new post(s) fetched.</green>', $result['added']));

        return $result['errors'] ? 1 : 0;
    }
}
