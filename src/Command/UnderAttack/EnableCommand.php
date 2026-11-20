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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class EnableCommand extends Command
{
    const OPTION_DURATION = 'duration';

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
        $this->setName('pixel_cloudflare:under-attack:enable')
            ->setDescription('Enable the Cloudflare "I\'m Under Attack" mode for a limited time')
            ->addOption(
                self::OPTION_DURATION,
                'd',
                InputOption::VALUE_REQUIRED,
                'Duration in minutes (default: configured default duration)'
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
        $duration = $input->getOption(self::OPTION_DURATION);
        $duration = $duration === null ? $this->config->getUnderAttackDefaultDuration() : (int)$duration;

        try {
            $until = $this->service->enable($duration, 'cli');
        } catch (Throwable $throwable) {
            $output->writeln('<error>' . $throwable->getMessage() . '</error>');
            return 1;
        }

        $output->writeln(sprintf(
            '<info>Under Attack mode enabled for %d minutes, automatically disabled at %s.</info>',
            $duration,
            $this->service->formatDate($until)
        ));

        return 0;
    }
}
