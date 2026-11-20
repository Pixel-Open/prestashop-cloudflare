<?php
/**
 * Copyright (C) 2026 Pixel Développement
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pixel\Module\Cloudflare\Command\UnderAttack;

use Pixel\Module\Cloudflare\Model\UnderAttack\Service;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Disable the Under Attack mode at the end of the chosen duration (to run every 5 minutes with the system cron)
 */
class ExpireCommand extends Command
{
    /**
     * @var Service $service
     */
    private $service;

    /**
     * @param Service $service
     */
    public function __construct(Service $service)
    {
        $this->service = $service;
        parent::__construct();
    }

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this->setName('pixel_cloudflare:under-attack:expire')
            ->setDescription(
                'Disable the Cloudflare "I\'m Under Attack" mode if its end date is reached (cron, every 5 minutes)'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $disabled = $this->service->disableIfExpired();
        } catch (Throwable $throwable) {
            // Retried on the next run, the local state is kept
            $output->writeln('<error>' . $throwable->getMessage() . '</error>');
            return 1;
        }

        if ($disabled) {
            $output->writeln('<info>Under Attack mode disabled (end of the duration).</info>');
        } elseif ($output->isVerbose()) {
            $output->writeln('Nothing to do.');
        }

        return 0;
    }
}
