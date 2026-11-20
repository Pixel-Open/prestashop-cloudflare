<?php
/**
 * Copyright (C) 2026 Pixel Développement
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Under Attack mode: default configurations and admin tab
 *
 * @param Pixel_cloudflare $module
 *
 * @return bool
 */
function upgrade_module_1_4_0($module)
{
    return $module->registerUnderAttackConfigurations() &&
        $module->installUnderAttackTab();
}
