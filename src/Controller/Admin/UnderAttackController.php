<?php
/**
 * Copyright (C) 2026 Pixel Développement
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pixel\Module\Cloudflare\Controller\Admin;

use Context;
use Link;
use Pixel\Module\Cloudflare\Exception\ApiException;
use Pixel\Module\Cloudflare\Helper\Config;
use Pixel\Module\Cloudflare\Model\UnderAttack\Service;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class UnderAttackController extends FrameworkBundleAdminController
{
    const TAB_CLASS_NAME = 'AdminPixelCloudflareUnderAttack';
    const TRANSLATION_DOMAIN = 'Modules.Pixelcloudflare.Admin';

    /**
     * Durations offered in the form, in minutes (filtered by the configured maximum)
     */
    const DURATIONS = [15, 30, 60, 120, 240, 480, 720, 1440];

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
    }

    /**
     * Under Attack mode status and activation form
     *
     * @param Request $request
     *
     * @return Response
     */
    public function indexAction(Request $request): Response
    {
        if (!$this->isGranted('read', self::TAB_CLASS_NAME)) {
            return $this->accessDenied();
        }

        $status = null;
        $error = null;

        if ($this->config->isApiConfigured()) {
            try {
                // Fallback when the cron is not configured: disable the mode if its end date is reached
                $this->service->disableIfExpired(true);
            } catch (Throwable $throwable) {
                $this->addFlash('error', $throwable->getMessage());
            }
            try {
                $status = $this->service->getStatus();
            } catch (ApiException $exception) {
                $error = $exception->getMessage();
            }
        }

        $until = $status['enabled_until'] ?? null;

        return $this->render('@Modules/pixel_cloudflare/views/templates/admin/under_attack.html.twig', [
            'layoutTitle' => $this->trans('Cloudflare - Under Attack mode', self::TRANSLATION_DOMAIN),
            'enableSidebar' => true,
            'apiConfigured' => $this->config->isApiConfigured(),
            'error' => $error,
            'status' => $status,
            'enabledAt' => $this->service->formatDate($status['enabled_at'] ?? null),
            'enabledUntil' => $this->service->formatDate($until),
            'overdue' => $until !== null && $until < time() - 600,
            'durations' => $this->getDurationOptions(),
            'defaultDuration' => $this->config->getUnderAttackDefaultDuration(),
            'excludedPaths' => $this->config->getUnderAttackExcludedPaths(),
            'knownBotsAllowed' => $this->config->isUnderAttackKnownBotsAllowed(),
            'canUpdate' => $this->isGranted('update', self::TAB_CLASS_NAME),
            'configUrl' => $this->getConfigUrl(),
        ]);
    }

    /**
     * Enable the mode for the selected duration
     *
     * @param Request $request
     *
     * @return RedirectResponse
     */
    public function enableAction(Request $request): RedirectResponse
    {
        if (!$this->isGranted('update', self::TAB_CLASS_NAME)) {
            return $this->accessDenied();
        }

        try {
            $until = $this->service->enable((int)$request->request->get('duration'), $this->getAuthor());
            $this->addFlash(
                'success',
                $this->trans(
                    'Under Attack mode enabled, automatically disabled on %date%.',
                    self::TRANSLATION_DOMAIN,
                    ['%date%' => $this->service->formatDate($until)]
                )
            );
        } catch (Throwable $throwable) {
            $this->addFlash('error', $throwable->getMessage());
        }

        return $this->redirectToRoute('admin_cloudflare_under_attack');
    }

    /**
     * Disable the mode
     *
     * @param Request $request
     *
     * @return RedirectResponse
     */
    public function disableAction(Request $request): RedirectResponse
    {
        if (!$this->isGranted('update', self::TAB_CLASS_NAME)) {
            return $this->accessDenied();
        }

        try {
            $this->service->disable($this->getAuthor());
            $this->addFlash('success', $this->trans('Under Attack mode disabled.', self::TRANSLATION_DOMAIN));
        } catch (Throwable $throwable) {
            $this->addFlash('error', $throwable->getMessage());
        }

        return $this->redirectToRoute('admin_cloudflare_under_attack');
    }

    /**
     * @return array<int, string> Minutes => label
     */
    private function getDurationOptions(): array
    {
        $max = $this->config->getUnderAttackMaxDuration();
        $durations = [];
        foreach (self::DURATIONS as $minutes) {
            if ($minutes <= $max) {
                $durations[] = $minutes;
            }
        }
        $durations[] = $this->config->getUnderAttackDefaultDuration();
        $durations = array_unique($durations);
        sort($durations);

        $options = [];
        foreach ($durations as $minutes) {
            $options[$minutes] = $minutes % 60 === 0
                ? $this->trans('%count% h', self::TRANSLATION_DOMAIN, ['%count%' => $minutes / 60])
                : $this->trans('%count% min', self::TRANSLATION_DOMAIN, ['%count%' => $minutes]);
        }

        return $options;
    }

    /**
     * Who enables / disables the mode
     *
     * @return string
     */
    private function getAuthor(): string
    {
        $employee = Context::getContext()->employee;
        if (!$employee || !$employee->id) {
            return 'admin ?';
        }

        return trim(sprintf('admin %s %s (#%d)', $employee->firstname, $employee->lastname, $employee->id));
    }

    /**
     * Module configuration page URL
     *
     * @return string
     */
    private function getConfigUrl(): string
    {
        /** @var Link $link */
        $link = Context::getContext()->link;

        return $link->getAdminLink('AdminModules', true, [], ['configure' => 'pixel_cloudflare']);
    }

    /**
     * @return RedirectResponse
     */
    private function accessDenied(): RedirectResponse
    {
        $this->addFlash('error', $this->trans('Access denied.', 'Admin.Notifications.Error'));

        return $this->redirectToRoute('admin_dashboard');
    }
}
