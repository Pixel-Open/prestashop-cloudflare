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
}
