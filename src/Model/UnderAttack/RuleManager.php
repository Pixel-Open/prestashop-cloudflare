<?php
/**
 * Copyright (C) 2026 Pixel Développement
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pixel\Module\Cloudflare\Model\UnderAttack;

use InvalidArgumentException;
use Pixel\Module\Cloudflare\Exception\ApiException;
use Pixel\Module\Cloudflare\Helper\Config;
use Pixel\Module\Cloudflare\Model\Api;

/**
 * Manage the zone Configuration Rule setting the "I'm Under Attack" security level.
 *
 * The rule lives in the http_config_settings phase entrypoint ruleset and is identified by its "ref",
 * so it is found again even if created by another environment or with an unknown ID.
 */
class RuleManager
{
    const PHASE = 'http_config_settings';
    const RULE_REF = 'pixel_prestashop_under_attack';
    const RULE_DESCRIPTION = 'PrestaShop - Under Attack mode (managed by pixel_cloudflare, do not edit)';

    /**
     * @var Api $api
     */
    private $api;

    /**
     * @var Config $config
     */
    private $config;

    /**
     * @var ExpressionBuilder $expressionBuilder
     */
    private $expressionBuilder;

    /**
     * @param Api $api
     * @param Config $config
     * @param ExpressionBuilder $expressionBuilder
     */
    public function __construct(Api $api, Config $config, ExpressionBuilder $expressionBuilder)
    {
        $this->api = $api;
        $this->config = $config;
        $this->expressionBuilder = $expressionBuilder;
    }

    /**
     * Retrieve the managed rule, null if it does not exist
     *
     * @return array|null
     * @throws ApiException
     */
    public function getRule(): ?array
    {
        return $this->load()['rule'];
    }

    /**
     * Create or update the managed rule with the current excluded paths
     *
     * @param bool $enabled
     *
     * @return void
     * @throws ApiException
     */
    public function save(bool $enabled): void
    {
        $body = $this->buildRule($enabled);
        $data = $this->load();

        if ($data['rule'] !== null) {
            $this->api->zoneRequest(
                'PATCH',
                sprintf('rulesets/%s/rules/%s', $data['ruleset_id'], $data['rule']['id']),
                $body
            );
        } elseif ($data['ruleset_id'] !== null) {
            $this->api->zoneRequest('POST', sprintf('rulesets/%s/rules', $data['ruleset_id']), $body);
        } else {
            // No entrypoint ruleset yet for the phase: create it with the rule
            $this->api->zoneRequest('PUT', $this->getEntrypointUri(), ['rules' => [$body]]);
        }
    }

    /**
     * Update the excluded paths of an existing rule, keeping its state
     *
     * @return bool False if the rule does not exist yet
     * @throws ApiException
     */
    public function syncExpression(): bool
    {
        $rule = $this->getRule();
        if ($rule === null) {
            return false;
        }
        $this->save((bool)($rule['enabled'] ?? false));

        return true;
    }

    /**
     * Check the access to the zone and to its Configuration Rules
     *
     * @return string Zone name
     * @throws ApiException
     */
    public function checkAccess(): string
    {
        $zone = $this->api->zoneRequest('GET', '');
        $this->load();

        return (string)($zone['name'] ?? '');
    }

    /**
     * @return array{ruleset_id: string|null, rule: array|null}
     * @throws ApiException
     */
    private function load(): array
    {
        try {
            $ruleset = $this->api->zoneRequest('GET', $this->getEntrypointUri());
        } catch (ApiException $exception) {
            if ($exception->isNotFound()) {
                return ['ruleset_id' => null, 'rule' => null];
            }
            throw $exception;
        }

        $rulesetId = isset($ruleset['id']) ? (string)$ruleset['id'] : null;
        foreach ($ruleset['rules'] ?? [] as $rule) {
            if (($rule['ref'] ?? '') === self::RULE_REF) {
                return ['ruleset_id' => $rulesetId, 'rule' => $rule];
            }
        }

        return ['ruleset_id' => $rulesetId, 'rule' => null];
    }

    /**
     * @param bool $enabled
     *
     * @return array
     * @throws ApiException
     */
    private function buildRule(bool $enabled): array
    {
        try {
            $expression = $this->expressionBuilder->build(
                $this->config->getUnderAttackExcludedPaths(),
                $this->config->isUnderAttackKnownBotsAllowed()
            );
        } catch (InvalidArgumentException $exception) {
            throw new ApiException($exception->getMessage(), 0, $exception);
        }

        return [
            'ref' => self::RULE_REF,
            'description' => self::RULE_DESCRIPTION,
            'expression' => $expression,
            'action' => 'set_config',
            'action_parameters' => ['security_level' => 'under_attack'],
            'enabled' => $enabled,
        ];
    }

    /**
     * @return string
     */
    private function getEntrypointUri(): string
    {
        return sprintf('rulesets/phases/%s/entrypoint', self::PHASE);
    }
}
