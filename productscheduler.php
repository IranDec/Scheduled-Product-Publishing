<?php
/**
 * Scheduled Product Publishing
 *
 * Publishes or unpublishes products automatically at the date and time you choose:
 * per product (product editor > Modules tab), in bulk (Catalog > Scheduled publishing)
 * and per shop (multistore).
 *
 * @author    Mohammad Babaei <https://adschi.com>
 * @copyright 2026 Mohammad Babaei - https://adschi.com
 * @license   MIT
 * @version   1.0.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/ProductSchedulerError.php';
require_once __DIR__ . '/classes/ProductSchedulerRepository.php';
require_once __DIR__ . '/classes/ProductSchedulerService.php';
require_once __DIR__ . '/classes/ProductSchedulerRunner.php';

class ProductScheduler extends Module
{
    // @author Mohammad Babaei | https://adschi.com
    const MODULE_VERSION = '1.0.0';
    const AUTHOR_NAME = 'Mohammad Babaei';
    const AUTHOR_URL = 'https://adschi.com';
    const AUTHOR_SITE = 'Adschi.com';
    const ADMIN_TAB = 'AdminProductScheduler';

    /** Settings (global, not per shop: one runner serves every shop) and their install defaults. */
    private static $settings = [
        'PRODUCTSCHEDULER_VISIT_RUN' => 1,
        'PRODUCTSCHEDULER_NOTIFY' => 0,
        'PRODUCTSCHEDULER_NOTIFY_EMAIL' => '',
        'PRODUCTSCHEDULER_HISTORY_DAYS' => 180,
        'PRODUCTSCHEDULER_BATCH' => 100,
    ];

    /** Runtime state written by the runner. */
    private static $stateKeys = [
        'PRODUCTSCHEDULER_CRON_TOKEN',
        'PRODUCTSCHEDULER_NEXT_DUE',
        'PRODUCTSCHEDULER_LAST_RUN',
        'PRODUCTSCHEDULER_LAST_SOURCE',
        'PRODUCTSCHEDULER_LAST_CRON',
        'PRODUCTSCHEDULER_LAST_TRY',
        'PRODUCTSCHEDULER_LAST_PURGE',
    ];

    public function __construct()
    {
        $this->name = 'productscheduler';
        $this->tab = 'administration';
        $this->version = self::MODULE_VERSION;
        $this->author = self::AUTHOR_NAME;
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '1.7.0.0',
            'max' => '9.99.99',
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Scheduled Product Publishing');
        $this->description = $this->l('Publish or unpublish products automatically at the date and time you choose - per product, in bulk and per shop.');
        $this->confirmUninstall = $this->l('All schedules and their history will be deleted. Continue?');
    }

    // -----------------------------------------------------------------------
    // Install / Uninstall
    // -----------------------------------------------------------------------

    public function install()
    {
        if (Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }

        return parent::install()
            && ProductSchedulerRepository::installTable()
            && $this->installConfiguration()
            && $this->installTab()
            && $this->registerHooks();
    }

    public function uninstall()
    {
        if (!parent::uninstall()) {
            return false;
        }

        ProductSchedulerRepository::dropTable();
        foreach (array_merge(array_keys(self::$settings), self::$stateKeys) as $key) {
            Configuration::deleteByName($key);
        }
        $this->uninstallTab();

        return true;
    }

    private function registerHooks()
    {
        $hooks = [
            'displayAdminProductsExtra',
            'actionAdminControllerSetMedia',
            'actionFrontControllerSetMedia',
            'actionProductDelete',
        ];
        foreach ($hooks as $hook) {
            if (!$this->registerHook($hook)) {
                return false;
            }
        }

        return true;
    }

    private function installConfiguration()
    {
        foreach (self::$settings as $key => $default) {
            if (Configuration::getGlobalValue($key) === false) {
                Configuration::updateGlobalValue($key, $default);
            }
        }
        if (!Configuration::getGlobalValue('PRODUCTSCHEDULER_CRON_TOKEN')) {
            self::regenerateCronToken();
        }
        ProductSchedulerRepository::refreshNextDue();

        return true;
    }

    private function installTab()
    {
        if ((int) Tab::getIdFromClassName(self::ADMIN_TAB)) {
            return true;
        }

        $tab = new Tab();
        $tab->active = 1;
        $tab->class_name = self::ADMIN_TAB;
        $tab->module = $this->name;
        $tab->id_parent = (int) Tab::getIdFromClassName('AdminCatalog');
        $tab->name = [];
        foreach (Language::getLanguages(false) as $lang) {
            $tab->name[(int) $lang['id_lang']] = $lang['iso_code'] === 'de'
                ? 'Geplante Veröffentlichung'
                : 'Scheduled publishing';
        }

        return (bool) $tab->add();
    }

    private function uninstallTab()
    {
        $id_tab = (int) Tab::getIdFromClassName(self::ADMIN_TAB);
        if ($id_tab) {
            $tab = new Tab($id_tab);
            $tab->delete();
        }
    }

    // -----------------------------------------------------------------------
    // Cron token
    // -----------------------------------------------------------------------

    public static function regenerateCronToken()
    {
        $token = Tools::passwdGen(40);
        Configuration::updateGlobalValue('PRODUCTSCHEDULER_CRON_TOKEN', $token);

        return $token;
    }

    public static function isValidCronToken($token)
    {
        $expected = (string) Configuration::getGlobalValue('PRODUCTSCHEDULER_CRON_TOKEN');

        return $expected !== '' && is_string($token) && $token !== '' && hash_equals($expected, $token);
    }

    public function getCronUrl()
    {
        return $this->context->link->getModuleLink(
            $this->name,
            'cron',
            ['token' => (string) Configuration::getGlobalValue('PRODUCTSCHEDULER_CRON_TOKEN')],
            true
        );
    }

    // -----------------------------------------------------------------------
    // Hooks
    // -----------------------------------------------------------------------

    /** Fallback runner: on shop visits, runs due schedules after the response was sent. */
    public function hookActionFrontControllerSetMedia()
    {
        $this->runOnVisitIfDue();
    }

    public function hookActionAdminControllerSetMedia()
    {
        $this->runOnVisitIfDue();
    }

    public function hookActionProductDelete($params)
    {
        $id_product = isset($params['id_product']) ? (int) $params['id_product'] : 0;
        if (!$id_product && isset($params['product']) && is_object($params['product'])) {
            $id_product = (int) $params['product']->id;
        }
        if ($id_product) {
            ProductSchedulerRepository::deleteForProduct($id_product);
            ProductSchedulerRepository::refreshNextDue();
        }
    }

    public function hookDisplayAdminProductsExtra($params)
    {
        $id_product = (int) (isset($params['id_product']) ? $params['id_product'] : Tools::getValue('id_product'));
        $canEdit = $id_product > 0 && $this->employeeCanSchedule();

        $config = [
            'mode' => 'product',
            'ajaxUrl' => $this->context->link->getAdminLink(self::ADMIN_TAB),
            'token' => $this->adminToken(),
            'idProduct' => $id_product,
            'serverNow' => date('Y-m-d H:i:s'),
            'i18n' => $this->jsTranslations(),
            'data' => $canEdit ? $this->productTabData($id_product) : null,
        ];

        $this->context->smarty->assign([
            'pps_id_product' => $id_product,
            'pps_can_edit' => $canEdit,
            'pps_multistore' => Shop::isFeatureActive(),
            'pps_shops' => $id_product ? $this->productShopsForEmployee($id_product) : [],
            'pps_config_json' => json_encode($config),
            'pps_manage_url' => $this->context->link->getAdminLink(self::ADMIN_TAB),
            'pps_css_url' => $this->getPathUri() . 'views/css/admin.css?v=' . self::MODULE_VERSION,
            'pps_js_url' => $this->getPathUri() . 'views/js/admin.js?v=' . self::MODULE_VERSION,
        ]);
        $this->assignFooterVars();

        return $this->display(__FILE__, 'views/templates/admin/product_tab.tpl');
    }

    private function runOnVisitIfDue()
    {
        if (!(int) Configuration::getGlobalValue('PRODUCTSCHEDULER_VISIT_RUN')) {
            return;
        }
        $next = (string) Configuration::getGlobalValue('PRODUCTSCHEDULER_NEXT_DUE');
        if ($next === '' || $next > date('Y-m-d H:i:s')) {
            return;
        }
        // At most one attempt per minute across all visitors.
        if (time() - (int) Configuration::getGlobalValue('PRODUCTSCHEDULER_LAST_TRY') < 60) {
            return;
        }
        Configuration::updateGlobalValue('PRODUCTSCHEDULER_LAST_TRY', time());
        ProductSchedulerRunner::deferAfterResponse();
    }

    // -----------------------------------------------------------------------
    // Data for the back-office screens (product tab + list page)
    // -----------------------------------------------------------------------

    /** Super admins, or employees allowed to edit products and to use this module's page. */
    public function employeeCanSchedule($action = 'edit')
    {
        $employee = $this->context->employee;
        if (!Validate::isLoadedObject($employee)) {
            return false;
        }
        if ((int) $employee->id_profile === (int) _PS_ADMIN_PROFILE_) {
            return true;
        }

        return self::profileCan($employee, $action, self::ADMIN_TAB)
            && self::profileCan($employee, $action, 'AdminProducts');
    }

    private static function profileCan($employee, $action, $tabClass)
    {
        $id_tab = (int) Tab::getIdFromClassName($tabClass);
        if (!$id_tab) {
            return $tabClass !== self::ADMIN_TAB;
        }
        $access = Profile::getProfileAccess((int) $employee->id_profile, $id_tab);

        return is_array($access) && isset($access[$action]) && (string) $access[$action] === '1';
    }

    /** The product's shops the current employee may manage: [['id' => .., 'name' => .., 'active' => ..]]. */
    public function productShopsForEmployee($id_product)
    {
        $states = ProductSchedulerRepository::productShopStates($id_product);
        $allowed = ProductSchedulerService::employeeShopIds($this->context->employee);
        $out = [];
        foreach ($states as $id_shop => $state) {
            if (in_array($id_shop, $allowed, true)) {
                $out[] = ['id' => $id_shop, 'name' => $state['name'], 'active' => $state['active']];
            }
        }

        return $out;
    }

    public function productTabData($id_product)
    {
        $id_product = (int) $id_product;
        $rows = ProductSchedulerRepository::forProduct($id_product, $this->visibleShopIds(), 60);
        $upcoming = [];
        $history = [];
        foreach ($rows as $row) {
            $formatted = $this->formatRow($row);
            if (in_array($row['status'], [ProductSchedulerRepository::STATUS_PENDING, ProductSchedulerRepository::STATUS_RUNNING], true)) {
                $upcoming[] = $formatted;
            } elseif (count($history) < 15) {
                $history[] = $formatted;
            }
        }
        usort($upcoming, function ($a, $b) {
            return strcmp($a['scheduled_at'], $b['scheduled_at']);
        });

        return [
            'upcoming' => $upcoming,
            'history' => $history,
            'shops' => $this->productShopsForEmployee($id_product),
            'multistore' => (bool) Shop::isFeatureActive(),
            'runner' => $this->runnerStatus(),
        ];
    }

    /** Shops whose schedules the current context shows (null = all). Rows for "all shops" are always shown. */
    public function visibleShopIds()
    {
        if (!Shop::isFeatureActive() || Shop::getContext() === Shop::CONTEXT_ALL) {
            return null;
        }

        return array_map('intval', Shop::getContextListShopID());
    }

    public function formatRow(array $row)
    {
        $ts = strtotime($row['scheduled_at']);
        $shopName = (int) $row['id_shop'] === 0
            ? $this->l('All shops of the product')
            : (isset($row['shop_name']) && $row['shop_name'] !== null ? $row['shop_name'] : '#' . (int) $row['id_shop']);
        $employee = trim((isset($row['firstname']) ? $row['firstname'] : '') . ' ' . (isset($row['lastname']) ? $row['lastname'] : ''));

        return [
            'id' => (int) $row['id_productscheduler'],
            'id_product' => (int) $row['id_product'],
            'product_name' => isset($row['product_name']) ? (string) $row['product_name'] : '',
            'reference' => isset($row['reference']) ? (string) $row['reference'] : '',
            'id_shop' => (int) $row['id_shop'],
            'shop_name' => $shopName,
            'action' => $row['action'],
            'action_label' => $row['action'] === ProductSchedulerRepository::ACTION_PUBLISH ? $this->l('Publish') : $this->l('Unpublish'),
            'scheduled_at' => $row['scheduled_at'],
            'input_value' => date('Y-m-d\TH:i', $ts),
            'when' => $this->formatDate($row['scheduled_at']),
            'in_seconds' => $ts - time(),
            'mark_new' => (bool) $row['mark_new'],
            'status' => $row['status'],
            'status_label' => $this->statusLabel($row['status']),
            'executed' => $row['executed_at'] ? $this->formatDate($row['executed_at']) : '',
            'message' => (string) $row['message'],
            'employee' => $employee !== '' ? $employee : ((int) $row['id_employee'] ? '#' . (int) $row['id_employee'] : ''),
        ];
    }

    public function statusLabel($status)
    {
        switch ($status) {
            case ProductSchedulerRepository::STATUS_PENDING:
                return $this->l('Waiting');
            case ProductSchedulerRepository::STATUS_RUNNING:
                return $this->l('Running');
            case ProductSchedulerRepository::STATUS_DONE:
                return $this->l('Done');
            case ProductSchedulerRepository::STATUS_FAILED:
                return $this->l('Failed');
            case ProductSchedulerRepository::STATUS_CANCELLED:
                return $this->l('Cancelled');
        }

        return (string) $status;
    }

    /** Date in the employee's language format, without seconds. */
    public function formatDate($datetime)
    {
        $ts = strtotime((string) $datetime);
        if (!$ts) {
            return '';
        }
        $format = isset($this->context->language->date_format_full) && $this->context->language->date_format_full
            ? $this->context->language->date_format_full
            : 'Y-m-d H:i:s';

        return date(str_replace([':s', '.s'], '', $format), $ts);
    }

    /** How the runner works right now, for the product tab, the list page and the settings. */
    public function runnerStatus()
    {
        $lastRun = (string) Configuration::getGlobalValue('PRODUCTSCHEDULER_LAST_RUN');
        $lastCron = (string) Configuration::getGlobalValue('PRODUCTSCHEDULER_LAST_CRON');
        $nextDue = (string) Configuration::getGlobalValue('PRODUCTSCHEDULER_NEXT_DUE');
        $cronOk = $lastCron !== '' && strtotime($lastCron) >= time() - 15 * 60;
        $visit = (bool) (int) Configuration::getGlobalValue('PRODUCTSCHEDULER_VISIT_RUN');

        if ($cronOk) {
            $level = 'success';
            $text = sprintf($this->l('Cron job active - last check %s.'), $this->formatDate($lastCron));
        } elseif ($visit) {
            $level = 'warning';
            $text = $this->l('No cron job detected: schedules run when someone visits the shop or the back office, which can be a few minutes late. Set up the cron job in the module settings for exact times.');
        } else {
            $level = 'danger';
            $text = $this->l('No cron job detected and running on visits is turned off: schedules will not run. Set up the cron job in the module settings.');
        }

        return [
            'level' => $level,
            'text' => $text,
            'cron_ok' => $cronOk,
            'visit' => $visit,
            'last_run' => $lastRun !== '' ? $this->formatDate($lastRun) : '',
            'last_source' => (string) Configuration::getGlobalValue('PRODUCTSCHEDULER_LAST_SOURCE'),
            'next_due' => $nextDue !== '' ? $this->formatDate($nextDue) : '',
            'next_due_in' => $nextDue !== '' ? strtotime($nextDue) - time() : null,
        ];
    }

    /** Strings used by views/js/admin.js. */
    public function jsTranslations()
    {
        return [
            'noUpcoming' => $this->l('No upcoming schedule for this product.'),
            'noHistory' => $this->l('Nothing has run yet.'),
            'runNow' => $this->l('Run now'),
            'cancel' => $this->l('Cancel'),
            'delete' => $this->l('Delete'),
            'confirmCancel' => $this->l('Cancel this schedule?'),
            'confirmRun' => $this->l('Run this schedule now? The product changes immediately.'),
            'confirmDelete' => $this->l('Delete this entry from the history?'),
            'chooseDate' => $this->l('Choose a date and time.'),
            'chooseShop' => $this->l('Choose at least one shop.'),
            'saving' => $this->l('Saving...'),
            'saved' => $this->l('Schedule saved.'),
            'serverError' => $this->l('The server did not answer correctly. Reload the page and try again.'),
            'online' => $this->l('Online'),
            'offline' => $this->l('Offline'),
            'now' => $this->l('now'),
            'inPrefix' => $this->l('in'),
            'agoSuffix' => $this->l('ago'),
            'overdue' => $this->l('due - runs with the next check'),
            'unitDay' => $this->l('d'),
            'unitHour' => $this->l('h'),
            'unitMin' => $this->l('min'),
            'markedNew' => $this->l('shown as new'),
            'by' => $this->l('by'),
            'executed' => $this->l('executed'),
            'nextRun' => $this->l('Next scheduled change:'),
            'lastRun' => $this->l('Last run:'),
            'noResults' => $this->l('No product found.'),
            'remove' => $this->l('Remove'),
            'copied' => $this->l('Copied'),
        ];
    }

    // -----------------------------------------------------------------------
    // Module settings page
    // -----------------------------------------------------------------------

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitProductSchedulerSettings')) {
            $output .= $this->processSettings();
        } elseif (Tools::isSubmit('submitProductSchedulerToken')) {
            if (!$this->isValidFormKey()) {
                $output .= $this->displayError($this->l('Your session expired. Reload the page and try again.'));
            } else {
                self::regenerateCronToken();
                $output .= $this->displayConfirmation($this->l('A new cron URL was created. Update your cron job with it - the old URL no longer works.'));
            }
        }

        $output .= $this->renderStatusPanel();
        $output .= $this->renderSettingsForm();
        $output .= $this->renderFooter();

        return $output;
    }

    /**
     * CSRF token of the module's back-office requests (list page forms, product tab). PrestaShop's
     * own admin token cannot be compared on 9.x (it is a randomized Symfony token there), so the
     * module signs the employee with the shop's secret key. Changing the password changes it.
     */
    public function adminToken()
    {
        $employee = $this->context->employee;
        if (!Validate::isLoadedObject($employee)) {
            return '';
        }

        return hash_hmac('sha256', 'productscheduler-admin|' . (int) $employee->id . '|' . $employee->passwd, _COOKIE_KEY_);
    }

    public function isValidAdminToken($token)
    {
        $expected = $this->adminToken();

        return $expected !== '' && is_string($token) && $token !== '' && hash_equals($expected, $token);
    }

    /** Extra CSRF key for the settings forms (on top of the AdminModules token in the URL). */
    public function formKey()
    {
        $id = Validate::isLoadedObject($this->context->employee) ? (int) $this->context->employee->id : 0;

        return hash_hmac('sha256', 'productscheduler-settings|' . $id . '|' . date('Y-m-d'), _COOKIE_KEY_);
    }

    private function isValidFormKey()
    {
        $key = (string) Tools::getValue('pps_form_key');
        $id = Validate::isLoadedObject($this->context->employee) ? (int) $this->context->employee->id : 0;
        $yesterday = hash_hmac('sha256', 'productscheduler-settings|' . $id . '|' . date('Y-m-d', time() - 86400), _COOKIE_KEY_);

        return $key !== '' && (hash_equals($this->formKey(), $key) || hash_equals($yesterday, $key));
    }

    private function processSettings()
    {
        if (!$this->isValidFormKey()) {
            return $this->displayError($this->l('Your session expired. Reload the page and try again.'));
        }

        $errors = [];
        $email = trim((string) Tools::getValue('PRODUCTSCHEDULER_NOTIFY_EMAIL'));
        if ($email !== '' && !Validate::isEmail($email)) {
            $errors[] = $this->l('The notification e-mail address is not valid.');
        }
        $days = (int) Tools::getValue('PRODUCTSCHEDULER_HISTORY_DAYS');
        if ($days < 0 || $days > 3650) {
            $errors[] = $this->l('Keep history: enter 0 to 3650 days.');
        }
        $batch = (int) Tools::getValue('PRODUCTSCHEDULER_BATCH');
        if ($batch < 1 || $batch > 1000) {
            $errors[] = $this->l('Changes per run: enter 1 to 1000.');
        }
        if ($errors) {
            return $this->displayError(implode('<br>', array_map('htmlspecialchars', $errors)));
        }

        Configuration::updateGlobalValue('PRODUCTSCHEDULER_VISIT_RUN', (int) (bool) Tools::getValue('PRODUCTSCHEDULER_VISIT_RUN'));
        Configuration::updateGlobalValue('PRODUCTSCHEDULER_NOTIFY', (int) (bool) Tools::getValue('PRODUCTSCHEDULER_NOTIFY'));
        Configuration::updateGlobalValue('PRODUCTSCHEDULER_NOTIFY_EMAIL', $email);
        Configuration::updateGlobalValue('PRODUCTSCHEDULER_HISTORY_DAYS', $days);
        Configuration::updateGlobalValue('PRODUCTSCHEDULER_BATCH', $batch);

        return $this->displayConfirmation($this->l('Settings saved.'));
    }

    private function settingsUrl()
    {
        return $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name
            . '&token=' . Tools::getAdminTokenLite('AdminModules');
    }

    private function renderStatusPanel()
    {
        $counts = ProductSchedulerRepository::countByStatus();
        $cronUrl = $this->getCronUrl();

        $this->context->smarty->assign([
            'pps_runner' => $this->runnerStatus(),
            'pps_counts' => $counts,
            'pps_cron_url' => $cronUrl,
            'pps_cron_line' => '* * * * * curl -fsS "' . $cronUrl . '" >/dev/null 2>&1',
            'pps_cli_line' => '* * * * * php ' . $this->getLocalPath() . 'cron_cli.php >/dev/null 2>&1',
            'pps_manage_url' => $this->context->link->getAdminLink(self::ADMIN_TAB),
            'pps_settings_url' => $this->settingsUrl(),
            'pps_form_key' => $this->formKey(),
            'pps_css_url' => $this->getPathUri() . 'views/css/admin.css?v=' . self::MODULE_VERSION,
            'pps_js_url' => $this->getPathUri() . 'views/js/admin.js?v=' . self::MODULE_VERSION,
            'pps_config_json' => json_encode(['mode' => 'settings', 'i18n' => $this->jsTranslations()]),
        ]);

        return $this->display(__FILE__, 'views/templates/admin/configure.tpl');
    }

    private function renderSettingsForm()
    {
        $switch = function ($name, $label, $desc) {
            return [
                'type' => 'switch',
                'label' => $label,
                'name' => $name,
                'is_bool' => true,
                'desc' => $desc,
                'values' => [
                    ['id' => $name . '_on', 'value' => 1, 'label' => $this->l('Yes')],
                    ['id' => $name . '_off', 'value' => 0, 'label' => $this->l('No')],
                ],
            ];
        };

        $form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    $switch(
                        'PRODUCTSCHEDULER_VISIT_RUN',
                        $this->l('Also run on visits'),
                        $this->l('Fallback when no cron job is set up: due schedules run after a page of the shop or the back office was delivered (at most once per minute). With a cron job you can leave this on - it does no harm.')
                    ),
                    $switch(
                        'PRODUCTSCHEDULER_NOTIFY',
                        $this->l('E-mail after each run'),
                        $this->l('Sends a short report of the products that were published or unpublished, and of failures.')
                    ),
                    [
                        'type' => 'text',
                        'label' => $this->l('Report e-mail'),
                        'name' => 'PRODUCTSCHEDULER_NOTIFY_EMAIL',
                        'class' => 'fixed-width-xxl',
                        'desc' => $this->l('Empty = the shop e-mail address.'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Keep history'),
                        'name' => 'PRODUCTSCHEDULER_HISTORY_DAYS',
                        'class' => 'fixed-width-sm',
                        'suffix' => $this->l('days'),
                        'desc' => $this->l('Finished, failed and cancelled entries older than this are removed. 0 = keep forever.'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Changes per run'),
                        'name' => 'PRODUCTSCHEDULER_BATCH',
                        'class' => 'fixed-width-sm',
                        'desc' => $this->l('Maximum number of schedules one run executes; the rest follows in the next run.'),
                    ],
                    [
                        'type' => 'hidden',
                        'name' => 'pps_form_key',
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                    'class' => 'btn btn-default pull-right',
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');
        $helper->allow_employee_form_lang = (int) Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitProductSchedulerSettings';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->fields_value = [
            'PRODUCTSCHEDULER_VISIT_RUN' => (int) Configuration::getGlobalValue('PRODUCTSCHEDULER_VISIT_RUN'),
            'PRODUCTSCHEDULER_NOTIFY' => (int) Configuration::getGlobalValue('PRODUCTSCHEDULER_NOTIFY'),
            'PRODUCTSCHEDULER_NOTIFY_EMAIL' => (string) Configuration::getGlobalValue('PRODUCTSCHEDULER_NOTIFY_EMAIL'),
            'PRODUCTSCHEDULER_HISTORY_DAYS' => (int) Configuration::getGlobalValue('PRODUCTSCHEDULER_HISTORY_DAYS'),
            'PRODUCTSCHEDULER_BATCH' => (int) Configuration::getGlobalValue('PRODUCTSCHEDULER_BATCH'),
            'pps_form_key' => $this->formKey(),
        ];

        return $helper->generateForm([$form]);
    }

    public function assignFooterVars()
    {
        $this->context->smarty->assign([
            'pps_footer_name' => $this->displayName,
            'pps_footer_version' => self::MODULE_VERSION,
            'pps_footer_author' => self::AUTHOR_NAME,
            'pps_footer_url' => self::AUTHOR_URL,
            'pps_footer_site' => self::AUTHOR_SITE,
            'pps_footer_logo' => $this->getPathUri() . 'logo.png',
            'pps_footer_year' => date('Y'),
        ]);
    }

    public function renderFooter()
    {
        $this->assignFooterVars();

        return $this->display(__FILE__, 'views/templates/admin/_footer.tpl');
    }
}
