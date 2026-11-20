<?php
/**
 * Copyright (C) 2026 Pixel Développement
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pixel\Module\Cloudflare\Model\UnderAttack;

use InvalidArgumentException;

/**
 * Build the rule expression: every request except the excluded paths (and the known bots, if allowed)
 */
class ExpressionBuilder
{
    /**
     * Allowed characters in an excluded path (no quote nor backslash, which would break the expression)
     */
    const PATH_PATTERN = '#^[A-Za-z0-9/_.*-]+$#';

    /**
     * @param string[] $excludedPaths Cloudflare wildcard patterns
     * @param bool $allowKnownBots Never challenge the bots known by Cloudflare (search engines...)
     *
     * @return string
     * @throws InvalidArgumentException
     */
    public function build(array $excludedPaths, bool $allowKnownBots = true): string
    {
        // cf.client.bot: known good bots (Googlebot, Bingbot...)
        $conditions = $allowKnownBots ? ['cf.client.bot'] : [];
        foreach (array_unique(array_filter(array_map('trim', $excludedPaths))) as $path) {
            if (!self::isValidPath($path)) {
                throw new InvalidArgumentException(sprintf('Invalid excluded path: %s', $path));
            }
            $conditions[] = sprintf('http.request.uri.path wildcard "%s"', $path);
        }

        return $conditions ? sprintf('not (%s)', implode(' or ', $conditions)) : 'true';
    }

    /**
     * @param string $path
     *
     * @return bool
     */
    public static function isValidPath(string $path): bool
    {
        return (bool)preg_match(self::PATH_PATTERN, $path);
    }
}
