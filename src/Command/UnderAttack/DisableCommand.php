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

class DisableCommand extends Command
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
        $this->setName('pixel_cloudflare:under-attack:disable')
            ->setDescription('Disable the Cloudflare "I\'m Under Attack" mode');
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
            $this->service->disable('cli');
        } catch (Throwable $throwable) {
            $output->writeln('<error>' . $throwable->getMessage() . '</error>');
            return 1;
        }

        $output->writeln('<info>Under Attack mode disabled.</info>');

        return 0;
    }
}
