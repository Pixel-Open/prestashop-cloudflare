<?php
/**
 * Copyright (C) 2026 Pixel Développement
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pixel\Module\Cloudflare\Model\UnderAttack;

use Context;
use InvalidArgumentException;
use Pixel\Module\Cloudflare\Exception\ApiException;
use Pixel\Module\Cloudflare\Helper\Config;
use PrestaShopLogger;
use PrestaShopLoggerCore;
use Throwable;

/**
 * Enable / disable the Cloudflare "I'm Under Attack" mode for a limited time
 */
class Service
{
    /**
     * Delay between two automatic deactivation attempts after a failure, in seconds
     */
    const RETRY_DELAY = 300;

    /**
     * @var RuleManager $ruleManager
     */
    private $ruleManager;

    /**
     * @var State $state
     */
    private $state;

    /**
     * @var Config $config
     */
    private $config;

    /**
     * @param RuleManager $ruleManager
     * @param State $state
     * @param Config $config
     */
    public function __construct(RuleManager $ruleManager, State $state, Config $config)
    {
        $this->ruleManager = $ruleManager;
        $this->state = $state;
        $this->config = $config;
    }

    /**
     * Enable the mode (or extend it if already enabled)
     *
     * @param int $duration Duration in minutes
     * @param string $author Who enabled the mode (admin user, cli...)
     *
     * @return int End timestamp
     * @throws ApiException
     * @throws InvalidArgumentException
     */
    public function enable(int $duration, string $author): int
    {
        $maxDuration = $this->config->getUnderAttackMaxDuration();
        if ($duration < 1 || $duration > $maxDuration) {
            throw new InvalidArgumentException(
                $this->trans('The duration must be between 1 and %max% minutes.', ['%max%' => $maxDuration])
            );
        }

        $now = time();
        $until = $now + $duration * 60;

        try {
            $this->ruleManager->save(true);
        } catch (ApiException $exception) {
            $this->log(sprintf('Enable failed (%s): %s', $author, $exception->getMessage()), true);
            throw $exception;
        }
        $this->state->save($now, $until, $author);
        $this->log(sprintf(
            'Under Attack mode enabled by %s for %d minutes (until %s), excluded paths: %s',
            $author,
            $duration,
            $this->formatDate($until),
            implode(', ', $this->config->getUnderAttackExcludedPaths()) ?: 'none'
        ));

        return $until;
    }

    /**
     * Disable the mode
     *
     * @param string $author
     *
     * @return void
     * @throws ApiException
     */
    public function disable(string $author): void
    {
        try {
            $rule = $this->ruleManager->getRule();
            if ($rule !== null && !empty($rule['enabled'])) {
                $this->ruleManager->save(false);
            }
        } catch (ApiException $exception) {
            $this->log(sprintf('Disable failed (%s): %s', $author, $exception->getMessage()), true);
            throw $exception;
        }
        $this->state->clear();
        $this->log(sprintf('Under Attack mode disabled by %s', $author));
    }

    /**
     * Disable the mode if its end date is reached
     *
     * @param bool $throttle Wait RETRY_DELAY seconds between two attempts after a failure
     *
     * @return bool True if the mode has been disabled
     * @throws ApiException
     */
    public function disableIfExpired(bool $throttle = false): bool
    {
        $state = $this->state->get();
        if ($state === null || $state['enabled_until'] > time()) {
            return false;
        }
        if ($throttle && $state['last_attempt'] > time() - self::RETRY_DELAY) {
            return false;
        }

        try {
            $this->disable('cron (end of the duration)');
        } catch (ApiException $exception) {
            // Retried later, the local state is kept
            $this->state->setLastAttempt(time());
            throw $exception;
        }

        return true;
    }

    /**
     * Current status, read from Cloudflare
     *
     * @return array{enabled: bool, rule_exists: bool, enabled_at: int|null, enabled_until: int|null, enabled_by: string|null}
     * @throws ApiException
     */
    public function getStatus(): array
    {
        $rule = $this->ruleManager->getRule();
        $enabled = $rule !== null && !empty($rule['enabled']);
        $state = $enabled ? $this->state->get() : null;

        return [
            'enabled' => $enabled,
            'rule_exists' => $rule !== null,
            'enabled_at' => $state['enabled_at'] ?? null,
            'enabled_until' => $state['enabled_until'] ?? null,
            'enabled_by' => $state['enabled_by'] ?? null,
        ];
    }

    /**
     * Format a timestamp in the shop timezone
     *
     * @param int|null $timestamp
     *
     * @return string
     */
    public function formatDate(?int $timestamp): string
    {
        return $timestamp ? date('d/m/Y H:i', $timestamp) : '';
    }

    /**
     * Add a log in the PrestaShop logs (Advanced Parameters > Logs)
     *
     * @param string $message
     * @param bool $error
     *
     * @return void
     */
    private function log(string $message, bool $error = false): void
    {
        try {
            PrestaShopLogger::addLog(
                '[Cloudflare Under Attack] ' . $message,
                $error ?
                    PrestaShopLoggerCore::LOG_SEVERITY_LEVEL_ERROR :
                    PrestaShopLoggerCore::LOG_SEVERITY_LEVEL_INFORMATIVE,
                null,
                'pixel_cloudflare',
                null,
                true
            );
        } catch (Throwable $throwable) {
            // The action itself must not fail because of the logs
        }
    }

    /**
     * @param string $message
     * @param array $parameters
     *
     * @return string
     */
    private function trans(string $message, array $parameters = []): string
    {
        try {
            return Context::getContext()->getTranslator()->trans($message, $parameters, 'Modules.Pixelcloudflare.Admin');
        } catch (Throwable $throwable) {
            return strtr($message, $parameters);
        }
    }
}
