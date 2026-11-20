<?php
/**
 * Copyright (C) 2023 Pixel Développement
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pixel\Module\Cloudflare\Helper;

use Configuration;

class Config
{
    const UNDER_ATTACK_DEFAULT_DURATION = 'CLOUDFLARE_UNDER_ATTACK_DEFAULT_DURATION';
    const UNDER_ATTACK_MAX_DURATION = 'CLOUDFLARE_UNDER_ATTACK_MAX_DURATION';
    const UNDER_ATTACK_ALLOW_KNOWN_BOTS = 'CLOUDFLARE_UNDER_ATTACK_ALLOW_KNOWN_BOTS';
    const UNDER_ATTACK_EXCLUDED_PATHS = 'CLOUDFLARE_UNDER_ATTACK_EXCLUDED_PATHS';
    const UNDER_ATTACK_STATE = 'CLOUDFLARE_UNDER_ATTACK_STATE';

    /**
     * Under Attack mode default values
     *
     * @return array<string, string>
     */
    public static function getUnderAttackDefaults(): array
    {
        return [
            self::UNDER_ATTACK_DEFAULT_DURATION => '120',
            self::UNDER_ATTACK_MAX_DURATION     => '1440',
            self::UNDER_ATTACK_ALLOW_KNOWN_BOTS => '0',
            self::UNDER_ATTACK_EXCLUDED_PATHS   => implode("\n", [
                '/api/*',
                '*/module/*/*webhook*',
                '*/module/*/*ipn*',
                '*/module/*/*notification*',
            ]),
        ];
    }

    /**
     * Retrieve API URL
     *
     * @return string
     */
    public function getApiUrl(): string
    {
        return 'https://api.cloudflare.com/client/v4/';
    }

    /**
     * Retrieve Authentication mode
     *
     * @return string|null
     */
    public function getAuthMode(): ?string
    {
        return Configuration::get('CLOUDFLARE_API_AUTHENTICATION_MODE') ?: null;
    }

    /**
     * Retrieve API token
     *
     * @return string|null
     */
    public function getApiToken(): ?string
    {
        return Configuration::get('CLOUDFLARE_API_TOKEN') ?: null;
    }

    /**
     * Retrieve API key
     *
     * @return string|null
     */
    public function getApiKey(): ?string
    {
        return Configuration::get('CLOUDFLARE_API_KEY') ?: null;
    }

    /**
     * Retrieve Zone ID
     *
     * @return string|null
     */
    public function getZoneId(): ?string
    {
        return Configuration::get('CLOUDFLARE_ZONE_ID') ?: null;
    }

    /**
     * Retrieve Account Email
     *
     * @return string|null
     */
    public function getEmail(): ?string
    {
        return Configuration::get('CLOUDFLARE_ACCOUNT_EMAIL') ?: null;
    }

    /**
     * Check if the API connection parameters are set
     *
     * @return bool
     */
    public function isApiConfigured(): bool
    {
        if (!$this->getZoneId()) {
            return false;
        }
        if ($this->getAuthMode() === 'api_key') {
            return $this->getApiKey() && $this->getEmail();
        }

        return $this->getAuthMode() === 'api_token' && $this->getApiToken();
    }

    /**
     * Default Under Attack duration, in minutes
     *
     * @return int
     */
    public function getUnderAttackDefaultDuration(): int
    {
        return min(
            max(1, (int)Configuration::get(self::UNDER_ATTACK_DEFAULT_DURATION)),
            $this->getUnderAttackMaxDuration()
        );
    }

    /**
     * Maximum Under Attack duration, in minutes
     *
     * @return int
     */
    public function getUnderAttackMaxDuration(): int
    {
        return max(1, (int)Configuration::get(self::UNDER_ATTACK_MAX_DURATION));
    }

    /**
     * Whether the bots known by Cloudflare (search engines...) are never challenged
     *
     * @return bool
     */
    public function isUnderAttackKnownBotsAllowed(): bool
    {
        return (bool)Configuration::get(self::UNDER_ATTACK_ALLOW_KNOWN_BOTS);
    }

    /**
     * Paths never challenged when Under Attack is enabled (Cloudflare wildcard patterns)
     *
     * @return string[]
     */
    public function getUnderAttackExcludedPaths(): array
    {
        return self::splitPaths((string)Configuration::get(self::UNDER_ATTACK_EXCLUDED_PATHS));
    }

    /**
     * Split a list of paths (one per line), trimmed and without duplicates
     *
     * @param string $value
     *
     * @return string[]
     */
    public static function splitPaths(string $value): array
    {
        return array_values(array_unique(array_filter(array_map('trim', preg_split('/\R/', $value) ?: []))));
    }
}
