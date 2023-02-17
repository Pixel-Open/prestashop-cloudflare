<?php
/**
 * Copyright (C) 2023 Pixel Développement
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pixel\Module\Cloudflare\Controller\Admin;

use Pixel\Module\Cloudflare\Helper\Config;
use Pixel\Module\Cloudflare\Model\Api;
use PrestaShopLogger;
use PrestaShopLoggerCore;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

class CloudflareController extends FrameworkBundleAdminController
{
    /**
     * @var Api $api
     */
    private $api;

    /**
     * @param Api $api
     */
    public function __construct(Api $api)
    {
        $this->api = $api;

        parent::__construct();
    }

    /**
     * @param Request $request
     *
     * @return RedirectResponse
     */
    public function clearCacheAction(Request $request): RedirectResponse
    {
        try {
            $result = $this->api->clearCache();
            foreach (($result['errors'] ?? []) as $error) {
                $this->addMessage('error', $error['message']);
            }
            foreach (($result['messages'] ?? []) as $message) {
                $this->addMessage('warning', $message['message']);
            }
            if ($result['success'] ?? false) {
                $this->addMessage('success', 'Cloudflare cache has been flushed');
            }
        } catch (Throwable $throwable) {
            $this->addMessage('error', $throwable->getMessage());
        }

        $redirect = $request->headers->get('referer');
        if (!$redirect) {
            $redirect = 'admin_dashboard';
        }

        return $this->redirect($redirect);
    }

    /**
     * Add message
     *
     * @param string $type
     * @param string $message
     * @return void
     */
    protected function addMessage(string $type, string $message): void
    {
        $this->addFlash($type, $message);
        PrestaShopLogger::addLog(
            $message,
            $type === 'error' ?
                PrestaShopLoggerCore::LOG_SEVERITY_LEVEL_ERROR :
                PrestaShopLoggerCore::LOG_SEVERITY_LEVEL_INFORMATIVE
        );
    }
}
