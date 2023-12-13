<?php
/**
 * Copyright (C) 2023 Pixel Développement
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

use Pixel\Module\Cloudflare\Model\Api;
use PrestaShop\PrestaShop\Core\Addon\Theme\ThemeProviderInterface;
use PrestaShop\PrestaShop\Core\Exception\ContainerNotFoundException;
use PrestaShop\PrestaShop\Core\Module\WidgetInterface;

class Pixel_cloudflare extends Module
{
    /**
     * Module's constructor.
     */
    public function __construct()
    {
        $this->name = 'pixel_cloudflare';
        $this->version = '1.2.0';
        $this->author = 'Pixel Open';
        $this->tab = 'administration';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans(
            'Cloudflare',
            [],
            'Modules.Pixelcloudflare.Admin'
        );
        $this->description = $this->trans(
            'Cloudflare API features in Prestashop.',
            [],
            'Modules.Pixelcloudflare.Admin'
        );
        $this->ps_versions_compliancy = [
            'min' => '1.7.6.0',
            'max' => _PS_VERSION_,
        ];
    }

    /***************************/
    /** MODULE INITIALIZATION **/
    /***************************/

    /**
     * Install the module
     *
     * @return bool
     */
    public function install(): bool
    {
        return parent::install() &&
            $this->registerHook('displayDashboardToolbarTopMenu') &&
            $this->registerHook('actionClearCompileCache');
    }

    /**
     * Uninstall the module
     *
     * @return bool
     */
    public function uninstall(): bool
    {
        return parent::uninstall() && $this->deleteConfigurations();
    }

    /**
     * Use the new translation system
     *
     * @return bool
     */
    public function isUsingNewTranslationSystem(): bool
    {
        return true;
    }

    /***********/
    /** HOOKS **/
    /***********/

    /**
     * Clear Cloudflare cache
     *
     * @param mixed[] $params
     *
     * @return void
     * @throws Exception
     */
    public function hookActionClearCompileCache(array $params): void
    {
        try {
            $result = $this->get('pixel.cloudflare.api')->clearCache();
            foreach (($result['messages'] ?? []) as $message) {
                PrestaShopLogger::addLog(
                    $message,
                    ($result['status'] ?? 0) === 1 ?
                        PrestaShopLoggerCore::LOG_SEVERITY_LEVEL_INFORMATIVE :
                        PrestaShopLoggerCore::LOG_SEVERITY_LEVEL_ERROR
                );
            }
        } catch (Throwable $throwable) {
            PrestaShopLogger::addLog(
                $this->trans('Unable to clear Cloudflare cache', [],'Modules.Pixelcloudflare.Admin'),
                PrestaShopLoggerCore::LOG_SEVERITY_LEVEL_ERROR
            );
        }
    }

    /**
     * Add toolbar buttons
     *
     * @param mixed[] $params
     *
     * @return string
     * @throws Exception
     */
    public function hookDisplayDashboardToolbarTopMenu(array $params): string
    {
        $controller = $this->context->controller;
        $allowed = $controller->controller_type === 'admin' && $controller->php_self === 'AdminPerformance';

        if (!$allowed) {
            return '';
        }

        $buttons = [
            [
                'label' => $this->trans('Clear Cloudflare Cache', [], 'Modules.Pixelcloudflare.Admin'),
                'route' => 'admin_cloudflare_clear_cache',
                'class' => 'btn btn-info',
                'icon'  => 'delete'
            ]
        ];

        return $this->get('twig')->render('@Modules/pixel_cloudflare/views/templates/admin/toolbar.html.twig', [
            'buttons' => $buttons,
        ]);
    }

    /*******************/
    /** CONFIGURATION **/
    /*******************/

    /**
     * Retrieve config fields
     *
     * @return array[]
     */
    protected function getConfigFields(): array
    {
        return [
            'CLOUDFLARE_ZONE_ID' => [
                'type'     => 'text',
                'label'    => $this->trans('Zone ID', [], 'Modules.Pixelcloudflare.Admin'),
                'name'     => 'CLOUDFLARE_ZONE_ID',
                'size'     => 20,
                'required' => true,
            ],
            'CLOUDFLARE_API_AUTHENTICATION_MODE' => [
                'type'     => 'select',
                'label'    => $this->trans('Authentication mode', [], 'Modules.Pixelcloudflare.Admin'),
                'name'     => 'CLOUDFLARE_API_AUTHENTICATION_MODE',
                'required' => true,
                'options' => [
                    'query' => [
                        [
                            'value' => 'api_token',
                            'name'  => $this->trans('API Token', [], 'Modules.Pixelcloudflare.Admin'),
                        ],
                        [
                            'value' => 'api_key',
                            'name'  => $this->trans('Global API Key', [], 'Modules.Pixelcloudflare.Admin'),
                        ],
                    ],
                    'id'   => 'value',
                    'name' => 'name',
                ],
            ],
            'CLOUDFLARE_API_TOKEN' => [
                'type'     => 'text',
                'label'    => $this->trans('API Token', [], 'Modules.Pixelcloudflare.Admin'),
                'name'     => 'CLOUDFLARE_API_TOKEN',
                'size'     => 20,
                'required' => false,
                'desc'     => $this->trans(
                    'A valid token from your Cloudflare Account with permission on "Cache Purge" for "Zone".',
                    [],
                    'Modules.Pixelcloudflare.Admin'
                ),
            ],
            'CLOUDFLARE_API_KEY' => [
                'type'     => 'text',
                'label'    => $this->trans('Global API Key', [], 'Modules.Pixelcloudflare.Admin'),
                'name'     => 'CLOUDFLARE_API_KEY',
                'size'     => 20,
                'required' => false,
            ],
            'CLOUDFLARE_ACCOUNT_EMAIL' => [
                'type'     => 'text',
                'label'    => $this->trans('Account Email', [], 'Modules.Pixelcloudflare.Admin'),
                'name'     => 'CLOUDFLARE_ACCOUNT_EMAIL',
                'size'     => 20,
                'required' => false,
            ],
            'API_AUTOMATICALLY_MINIFY' => [
                'type'     => 'select',
                'multiple' => true,
                'label'    => $this->trans('Automatically minify', [], 'Modules.Pixelcloudflare.Admin'),
                'name'     => 'API_AUTOMATICALLY_MINIFY[]',
                'required' => false,
                'options' => [
                    'query' => [
                        [
                            'value' => 'js',
                            'name'  => $this->trans('JavaScript', [], 'Modules.Pixelcloudflare.Admin'),
                        ],
                        [
                            'value' => 'css',
                            'name'  => $this->trans('CSS', [], 'Modules.Pixelcloudflare.Admin'),
                        ],
                        [
                            'value' => 'html',
                            'name'  => $this->trans('HTML', [], 'Modules.Pixelcloudflare.Admin'),
                        ],
                    ],
                    'id'   => 'value',
                    'name' => 'name',
                ],
                'desc' => Configuration::get('CLOUDFLARE_API_AUTHENTICATION_MODE') === 'api_token' ?
                    $this->trans(
                    'A valid token with permission on "Zone Settings" for "Zone" is required (Read and Edit).',
                    [],
                    'Modules.Pixelcloudflare.Admin'
                    ) : ''
                ,
            ]
        ];
    }

    /**
     * This method handles the module's configuration page
     *
     * @return string
     * @throws ContainerNotFoundException
     */
    public function getContent(): string
    {
        $output = '';

        /** @var Api $api */
        $api = $this->getContainer()->get('pixel.cloudflare.api');

        if (Tools::isSubmit('submit' . $this->name)) {
            foreach ($this->getConfigFields() as $code => $field) {
                $value = Tools::getValue($code);

                // Cloudflare API settings
                if ($code === 'API_AUTOMATICALLY_MINIFY') {
                    if (is_array($value)) {
                        $api->patchMinifySetting($value);
                    }
                    continue;
                }

                // Prestashop settings
                if ($field['required'] && empty($value)) {
                    return $this->displayError(
                            $this->trans('%field% is empty', ['%field%' => $field['label']], 'Modules.Pixelcloudflare.Admin')
                        ) . $this->displayForm();
                }
                if ($value && ($field['multiple'] ?? false) === true) {
                    $value = join(',', $value);
                }
                Configuration::updateValue($code, $value);
            }

            $output = $this->displayConfirmation($this->trans('Settings updated', [], 'Modules.Pixelcloudflare.Admin'));
        }

        return $output . $this->displayForm();
    }

    /**
     * Builds the configuration form
     *
     * @return string
     * @throws ContainerNotFoundException
     */
    public function displayForm(): string
    {
        $form = [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Settings', [], 'Modules.Pixelcloudflare.Admin'),
                ],
                'input' => $this->getConfigFields(),
                'submit' => [
                    'title' => $this->trans('Save', [], 'Modules.Pixelcloudflare.Admin'),
                    'class' => 'btn btn-default pull-right',
                ],
            ],
        ];

        $helper = new HelperForm();

        $helper->table = $this->table;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&' . http_build_query(['configure' => $this->name]);
        $helper->submit_action = 'submit' . $this->name;

        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');

        /** @var Api $api */
        $api = $this->getContainer()->get('pixel.cloudflare.api');

        foreach ($this->getConfigFields() as $code => $field) {
            // Prestashop settings
            $value = Tools::getValue($code, Configuration::get($code));

            if (!is_array($value) && ($field['multiple'] ?? false) === true) {
                $value = explode(',', (string)$value);
            }

            // Cloudflare API settings
            if ($code === 'API_AUTOMATICALLY_MINIFY') {
                $value = [];
                $result = $api->GetMinifySetting();
                foreach (($result['result']['value'] ?? []) as $type => $state) {
                    if (strtolower($state) === 'on') {
                        $value[] = $type;
                    }
                }
            }

            $helper->fields_value[$field['name']] = $value;
        }

        $form = $helper->generateForm([$form]);
        $script = $this->get('twig')->render('@Modules/pixel_cloudflare/views/templates/admin/config/js.twig');

        return $form . $script;
    }

    /**
     * Delete configurations
     *
     * @return bool
     */
    protected function deleteConfigurations(): bool
    {
        foreach ($this->getConfigFields() as $key => $options) {
            Configuration::deleteByName($key);
        }

        return true;
    }
}
