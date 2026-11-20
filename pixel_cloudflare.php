<?php
/**
 * Copyright (C) 2025 Pixel Développement
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

use Pixel\Module\Cloudflare\Exception\ApiException;
use Pixel\Module\Cloudflare\Helper\Config;
use Pixel\Module\Cloudflare\Model\UnderAttack\ExpressionBuilder;

class Pixel_cloudflare extends Module
{
    const UNDER_ATTACK_TAB = 'AdminPixelCloudflareUnderAttack';

    /**
     * Module's constructor.
     */
    public function __construct()
    {
        $this->name = 'pixel_cloudflare';
        $this->version = '1.4.0';
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
            'min' => '1.7.7.0',
            'max' => _PS_VERSION_,
        ];

        $tabNames = [];
        foreach (Language::getLanguages(false) as $lang) {
            $tabNames[$lang['locale']] = 'Cloudflare Under Attack';
        }

        $this->tabs = [
            [
                'route_name' => 'admin_cloudflare_under_attack',
                'class_name' => self::UNDER_ATTACK_TAB,
                'visible' => true,
                'name' => $tabNames,
                'parent_class_name' => 'AdminAdvancedParameters',
                'wording' => 'Cloudflare Under Attack',
                'wording_domain' => 'Modules.Pixelcloudflare.Admin',
            ],
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
            $this->registerUnderAttackConfigurations() &&
            $this->registerHook('displayDashboardToolbarTopMenu') &&
            $this->registerHook('actionClearCompileCache');
    }

    /**
     * Add the Under Attack mode default configurations, without overriding the existing values
     *
     * @return bool
     */
    public function registerUnderAttackConfigurations(): bool
    {
        foreach (Config::getUnderAttackDefaults() as $key => $value) {
            if (Configuration::hasKey($key)) {
                continue;
            }
            Configuration::updateValue($key, $value);
        }

        return true;
    }

    /**
     * Add the "Advanced Parameters > Cloudflare Under Attack" tab if missing (module upgrade)
     *
     * @return bool
     */
    public function installUnderAttackTab(): bool
    {
        if (Tab::getIdFromClassName(self::UNDER_ATTACK_TAB)) {
            return true;
        }

        $tab = new Tab();
        $tab->class_name = self::UNDER_ATTACK_TAB;
        $tab->route_name = 'admin_cloudflare_under_attack';
        $tab->module = $this->name;
        $tab->active = true;
        $tab->id_parent = (int)Tab::getIdFromClassName('AdminAdvancedParameters');
        $tab->wording = 'Cloudflare Under Attack';
        $tab->wording_domain = 'Modules.Pixelcloudflare.Admin';
        foreach (Language::getLanguages(false) as $lang) {
            $tab->name[(int)$lang['id_lang']] = 'Cloudflare Under Attack';
        }

        return (bool)$tab->add();
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
            foreach (($result['errors'] ?? []) as $error) {
                if (!isset($error['message'])) {
                    continue;
                }
                PrestaShopLogger::addLog(
                    (string)$error['message'],
                    PrestaShopLoggerCore::LOG_SEVERITY_LEVEL_ERROR
                );
            }
            foreach (($result['messages'] ?? []) as $message) {
                if (!isset($message['message'])) {
                    continue;
                }
                PrestaShopLogger::addLog(
                    (string)$message['message'],
                    PrestaShopLoggerCore::LOG_SEVERITY_LEVEL_INFORMATIVE
                );
            }
            if ($result['success'] ?? false) {
                PrestaShopLogger::addLog(
                    $this->trans('Cloudflare cache has been flushed', [],'Modules.Pixelcloudflare.Admin'),
                    PrestaShopLoggerCore::LOG_SEVERITY_LEVEL_INFORMATIVE
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
                    'A valid token from your Cloudflare Account restricted to the zone, with the permissions "Zone: Read", "Cache Purge: Purge" and "Config Rules: Edit" (Under Attack mode).',
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
        ];
    }

    /**
     * Retrieve Under Attack mode config fields
     *
     * @return array[]
     */
    protected function getUnderAttackConfigFields(): array
    {
        return [
            Config::UNDER_ATTACK_DEFAULT_DURATION => [
                'type'     => 'text',
                'label'    => $this->trans('Default duration (minutes)', [], 'Modules.Pixelcloudflare.Admin'),
                'name'     => Config::UNDER_ATTACK_DEFAULT_DURATION,
                'class'    => 'fixed-width-sm',
                'required' => true,
            ],
            Config::UNDER_ATTACK_MAX_DURATION => [
                'type'     => 'text',
                'label'    => $this->trans('Maximum duration (minutes)', [], 'Modules.Pixelcloudflare.Admin'),
                'name'     => Config::UNDER_ATTACK_MAX_DURATION,
                'class'    => 'fixed-width-sm',
                'required' => true,
                'desc'     => $this->trans(
                    'The mode is automatically disabled at the end of the chosen duration (checked every 5 minutes by the cron).',
                    [],
                    'Modules.Pixelcloudflare.Admin'
                ),
            ],
            Config::UNDER_ATTACK_ALLOW_KNOWN_BOTS => [
                'type'     => 'switch',
                'label'    => $this->trans('Allow known bots', [], 'Modules.Pixelcloudflare.Admin'),
                'name'     => Config::UNDER_ATTACK_ALLOW_KNOWN_BOTS,
                'required' => false,
                'is_bool'  => true,
                'values'   => [
                    [
                        'id'    => 'active_on',
                        'value' => 1,
                        'label' => $this->trans('Yes', [], 'Admin.Global'),
                    ],
                    [
                        'id'    => 'active_off',
                        'value' => 0,
                        'label' => $this->trans('No', [], 'Admin.Global'),
                    ],
                ],
                'desc'     => $this->trans(
                    'Never challenge the bots known by Cloudflare (Googlebot, Bingbot...), to avoid any impact on search engine indexing. Not available on the Cloudflare Free plan: the activation is refused by Cloudflare.',
                    [],
                    'Modules.Pixelcloudflare.Admin'
                ),
            ],
            Config::UNDER_ATTACK_EXCLUDED_PATHS => [
                'type'     => 'textarea',
                'label'    => $this->trans('Excluded paths', [], 'Modules.Pixelcloudflare.Admin'),
                'name'     => Config::UNDER_ATTACK_EXCLUDED_PATHS,
                'rows'     => 8,
                'required' => false,
                'desc'     => $this->trans(
                    'One path per line, never challenged (server-to-server calls: webservice, payment notifications...). * matches any characters, e.g. /api/* or */module/*/*webhook* (with or without language code). Allowed characters: letters, digits, / _ - . *',
                    [],
                    'Modules.Pixelcloudflare.Admin'
                ),
            ],
        ];
    }

    /**
     * Validate and normalize the Under Attack mode values
     *
     * @return array<string, string> Config key => value
     * @throws InvalidArgumentException
     */
    protected function getUnderAttackSubmittedValues(): array
    {
        $fields = $this->getUnderAttackConfigFields();
        $values = [];

        foreach ([Config::UNDER_ATTACK_DEFAULT_DURATION, Config::UNDER_ATTACK_MAX_DURATION] as $key) {
            $value = trim((string)Tools::getValue($key));
            if (!ctype_digit($value) || (int)$value < 1) {
                throw new InvalidArgumentException(
                    $this->trans(
                        '%field% must be a number greater than 0',
                        ['%field%' => $fields[$key]['label']],
                        'Modules.Pixelcloudflare.Admin'
                    )
                );
            }
            $values[$key] = (string)(int)$value;
        }
        if ((int)$values[Config::UNDER_ATTACK_DEFAULT_DURATION] > (int)$values[Config::UNDER_ATTACK_MAX_DURATION]) {
            throw new InvalidArgumentException(
                $this->trans(
                    'The default duration must be lower than or equal to the maximum duration',
                    [],
                    'Modules.Pixelcloudflare.Admin'
                )
            );
        }

        $values[Config::UNDER_ATTACK_ALLOW_KNOWN_BOTS] = Tools::getValue(Config::UNDER_ATTACK_ALLOW_KNOWN_BOTS) ? '1' : '0';

        $paths = Config::splitPaths((string)Tools::getValue(Config::UNDER_ATTACK_EXCLUDED_PATHS));
        foreach ($paths as $path) {
            if (!ExpressionBuilder::isValidPath($path)) {
                throw new InvalidArgumentException(
                    $this->trans(
                        'Invalid excluded path "%path%": only letters, digits and / _ - . * are allowed.',
                        ['%path%' => $path],
                        'Modules.Pixelcloudflare.Admin'
                    )
                );
            }
        }
        $values[Config::UNDER_ATTACK_EXCLUDED_PATHS] = implode("\n", $paths);

        return $values;
    }

    /**
     * Check the API access and push the excluded paths to the existing Under Attack rule
     *
     * @return string
     */
    protected function syncUnderAttackRule(): string
    {
        if (!$this->get('pixel.cloudflare.config')->isApiConfigured()) {
            return '';
        }

        try {
            $ruleManager = $this->get('pixel.cloudflare.under_attack.rule_manager');
            $output = $this->displayConfirmation(
                $this->trans(
                    'Cloudflare API access checked (zone %zone%).',
                    ['%zone%' => $ruleManager->checkAccess()],
                    'Modules.Pixelcloudflare.Admin'
                )
            );
            if ($ruleManager->syncExpression()) {
                $output .= $this->displayConfirmation(
                    $this->trans(
                        'The excluded paths have been updated on the Cloudflare rule.',
                        [],
                        'Modules.Pixelcloudflare.Admin'
                    )
                );
            }

            return $output;
        } catch (ApiException $exception) {
            return $this->displayError($exception->getMessage());
        }
    }

    /**
     * This method handles the module's configuration page
     *
     * @return string
     */
    public function getContent(): string
    {
        $output = '';

        if (Tools::isSubmit('submit' . $this->name)) {
            try {
                $underAttackValues = $this->getUnderAttackSubmittedValues();
            } catch (InvalidArgumentException $exception) {
                return $this->displayError($exception->getMessage()) . $this->displayForm();
            }

            foreach ($this->getConfigFields() as $code => $field) {
                $value = Tools::getValue($code);

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
            foreach ($underAttackValues as $code => $value) {
                Configuration::updateValue($code, $value);
            }

            $output = $this->displayConfirmation($this->trans('Settings updated', [], 'Modules.Pixelcloudflare.Admin'));
            $output .= $this->syncUnderAttackRule();
        }

        return $output . $this->displayForm();
    }

    /**
     * Builds the configuration form
     *
     * @return string
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
        $underAttackForm = [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Under Attack mode', [], 'Modules.Pixelcloudflare.Admin'),
                    'icon'  => 'icon-shield',
                ],
                'description' => $this->trans(
                    'Enable the mode from Advanced Parameters > Cloudflare Under Attack, or with the pixel_cloudflare:under-attack:enable command.',
                    [],
                    'Modules.Pixelcloudflare.Admin'
                ),
                'input' => $this->getUnderAttackConfigFields(),
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
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name;
        $helper->submit_action = 'submit' . $this->name;

        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');

        foreach (array_merge($this->getConfigFields(), $this->getUnderAttackConfigFields()) as $code => $field) {
            // Prestashop settings
            $value = Tools::getValue($code, Configuration::get($code));

            if (!is_array($value) && ($field['multiple'] ?? false) === true) {
                $value = explode(',', (string)$value);
            }

            $helper->fields_value[$field['name']] = $value;
        }

        $form = $helper->generateForm([$form, $underAttackForm]);
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
        foreach (array_merge($this->getConfigFields(), $this->getUnderAttackConfigFields()) as $key => $options) {
            Configuration::deleteByName($key);
        }
        Configuration::deleteByName(Config::UNDER_ATTACK_STATE);

        return true;
    }
}
