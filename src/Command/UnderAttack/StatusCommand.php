<?php
/**
 * Copyright (C) 2026 Pixel Développement
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pixel\Module\Cloudflare\Command\UnderAttack;

use Pixel\Module\Cloudflare\Helper\Config;
use Pixel\Module\Cloudflare\Model\UnderAttack\Service;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class StatusCommand extends Command
{
    /**
     * @var Service $service
     */
    private $service;

    /**
     * @var Config $config
     */
    private $config;

    /**
     * @param Service $service
     * @param Config $config
     */
    public function __construct(Service $service, Config $config)
    {
        $this->service = $service;
        $this->config = $config;
        parent::__construct();
    }

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this->setName('pixel_cloudflare:under-attack:status')
            ->setDescription('Show the Cloudflare "I\'m Under Attack" mode status');
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
            $status = $this->service->getStatus();
        } catch (Throwable $throwable) {
            $output->writeln('<error>' . $throwable->getMessage() . '</error>');
            return 1;
        }

        if (!$status['enabled']) {
            $output->writeln('<info>Under Attack mode: disabled</info>');
        } elseif ($status['enabled_until'] === null) {
            $output->writeln('<comment>Under Attack mode: ENABLED, without end date (enabled from Cloudflare)</comment>');
        } else {
            $output->writeln(sprintf(
                '<comment>Under Attack mode: ENABLED by %s on %s, until %s</comment>',
                $status['enabled_by'],
                $this->service->formatDate($status['enabled_at']),
                $this->service->formatDate($status['enabled_until'])
            ));
        }
        $output->writeln(
            'Excluded paths: ' . (implode(', ', $this->config->getUnderAttackExcludedPaths()) ?: 'none')
        );
        $output->writeln('Known bots allowed: ' . ($this->config->isUnderAttackKnownBotsAllowed() ? 'yes' : 'no'));

        return 0;
    }
}
