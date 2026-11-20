<?php
/**
 * Copyright (C) 2026 Pixel Développement
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pixel\Module\Cloudflare\Model\UnderAttack;

use Configuration;
use Pixel\Module\Cloudflare\Helper\Config;

/**
 * Local state of the activation done from PrestaShop (end date, author), stored in the configuration table
 */
class State
{
    /**
     * @param int $enabledAt
     * @param int $enabledUntil
     * @param string $enabledBy
     *
     * @return void
     */
    public function save(int $enabledAt, int $enabledUntil, string $enabledBy): void
    {
        $this->write([
            'enabled_at' => $enabledAt,
            'enabled_until' => $enabledUntil,
            'enabled_by' => $enabledBy,
        ]);
    }

    /**
     * @return array{enabled_at: int, enabled_until: int, enabled_by: string, last_attempt: int}|null
     */
    public function get(): ?array
    {
        $data = json_decode((string)Configuration::getGlobalValue(Config::UNDER_ATTACK_STATE), true);
        if (!is_array($data) || empty($data['enabled_until'])) {
            return null;
        }

        return [
            'enabled_at' => (int)($data['enabled_at'] ?? 0),
            'enabled_until' => (int)$data['enabled_until'],
            'enabled_by' => (string)($data['enabled_by'] ?? ''),
            'last_attempt' => (int)($data['last_attempt'] ?? 0),
        ];
    }

    /**
     * Store the date of the last failed automatic deactivation
     *
     * @param int $timestamp
     *
     * @return void
     */
    public function setLastAttempt(int $timestamp): void
    {
        $state = $this->get();
        if ($state === null) {
            return;
        }
        $state['last_attempt'] = $timestamp;
        $this->write($state);
    }

    /**
     * @return void
     */
    public function clear(): void
    {
        Configuration::deleteByName(Config::UNDER_ATTACK_STATE);
    }

    /**
     * @param array $data
     *
     * @return void
     */
    private function write(array $data): void
    {
        Configuration::updateGlobalValue(Config::UNDER_ATTACK_STATE, json_encode($data));
    }
}
