<?php
declare(strict_types=1);

namespace Grav\Plugin\Console;

use Grav\Common\Grav;
use Grav\Console\ConsoleCommand;
use Grav\Plugin\MetaFeed\TokenManager;

class RefreshTokensCommand extends ConsoleCommand
{
    protected function configure(): void
    {
        $this
            ->setName('refresh-tokens')
            ->setDescription('Refresh access tokens that are close to expiry');
    }

    protected function serve(): int
    {
        require_once \dirname(__DIR__) . '/classes/Autoloader.php';
        \Grav\Plugin\MetaFeed\Autoloader::register();

        $grav = Grav::instance();
        $grav['config']->init();
        $grav->fireEvent('onPluginsInitialized');

        $this->output->writeln('<info>Checking tokens...</info>');

        try {
            (new TokenManager($grav))->refreshAll();
            $this->output->writeln('<green>Done.</green>');
            return 0;
        } catch (\Exception $e) {
            $this->output->writeln('<red>Error: ' . $e->getMessage() . '</red>');
            return 1;
        }
    }
}
