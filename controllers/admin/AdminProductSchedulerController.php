<?php
/**
 * @author    Mohammad Babaei <https://adschi.com>
 * @copyright 2026 Mohammad Babaei - https://adschi.com
 * @license   MIT
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Catalog > Scheduled publishing: every schedule of the visible shops (filter, add in bulk,
 * reschedule, cancel, run now, delete history) and the JSON endpoint of the product tab.
 *
 * Every change needs: POST, this controller's token, "edit" permission on this page and on
 * Catalog > Products, and access to the schedule's shops.
 */
class AdminProductSchedulerController extends ModuleAdminController
{
    const PER_PAGE_OPTIONS = [20, 50, 100, 300];

    /** @var ProductScheduler */
    public $module;

    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
    }

    private function ppsT($string)
    {
        return $this->module->l($string, 'productscheduler');
    }

    /** The module's CSRF token (see ProductScheduler::adminToken()), POST body only. */
    private function ppsValidToken()
    {
        return $this->module->isValidAdminToken(isset($_POST['pps_token']) ? $_POST['pps_token'] : '');
    }

    private function ppsIsPost()
    {
        return isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) === 'POST';
    }

    // -----------------------------------------------------------------------
    // Requests
    // -----------------------------------------------------------------------

    public function postProcess()
    {
        if (Tools::getValue('ppsAjax')) {
            $this->ppsAjaxDispatch();
        }

        $action = '';
        foreach (['submitPpsAdd', 'submitPpsBlog', 'submitPpsRow', 'submitPpsBulk', 'submitPpsRunDue'] as $submit) {
            if (Tools::isSubmit($submit)) {
                $action = $submit;
                break;
            }
        }
        if ($action === '') {
            return parent::postProcess();
        }

        try {
            $this->ppsGuard();
            switch ($action) {
                case 'submitPpsAdd':
                    $this->ppsFlash($this->ppsProcessAdd(), 'success');
                    break;
                case 'submitPpsRow':
                    $this->ppsFlash($this->ppsProcessRow(), 'success');
                    break;
                case 'submitPpsBlog':
                    $this->ppsFlash($this->ppsProcessBlog(), 'success');
                    break;
                case 'submitPpsBulk':
                    $this->ppsFlash($this->ppsProcessBulk(), 'success');
                    break;
                case 'submitPpsRunDue':
                    $summary = ProductSchedulerRunner::runDue('manual');
                    $this->ppsFlash($this->ppsSummaryText($summary), $summary['failed'] ? 'warning' : 'success');
                    break;
            }
        } catch (ProductSchedulerError $e) {
            $this->ppsFlash($e->getMessage(), 'danger');
        } catch (Exception $e) {
            PrestaShopLogger::addLog('[productscheduler] ' . $e->getMessage(), 3, null, 'Product', 0, true);
            $this->ppsFlash($this->ppsT('Something went wrong. The details were written to the PrestaShop log.'), 'danger');
        }

        Tools::redirectAdmin($this->ppsListUrl($this->ppsCurrentFilters()));
    }

    /** @throws ProductSchedulerError */
    private function ppsGuard()
    {
        if (!$this->ppsIsPost() || !$this->ppsValidToken()) {
            throw new ProductSchedulerError($this->ppsT('Your session expired. Reload the page and try again.'));
        }
        if (!$this->module->employeeCanSchedule('edit')) {
            throw new ProductSchedulerError($this->ppsT('You do not have permission to change schedules.'));
        }
    }

    private function ppsProcessAdd()
    {
        $ids = array_merge(
            explode(',', (string) Tools::getValue('pps_product_ids')),
            preg_split('/[\s,;]+/', (string) Tools::getValue('pps_product_ids_extra'))
        );
        $result = ProductSchedulerService::addSchedules(
            $this->module,
            $ids,
            (string) Tools::getValue('pps_action'),
            (string) Tools::getValue('pps_at'),
            Tools::getValue('pps_end_on') ? (string) Tools::getValue('pps_end_at') : '',
            $this->ppsRequestedShops(),
            (bool) Tools::getValue('pps_mark_new'),
            $this->context->employee
        );

        return $this->ppsAddResultText($result);
    }

    /** Blog articles: one time for all, or a series (one article every N days at a fixed time). */
    private function ppsProcessBlog()
    {
        $ids = Tools::getValue('pps_blog_ids');
        $ids = is_array($ids) ? array_map('intval', $ids) : [];
        // the order fields decide the order of a series
        $order = Tools::getValue('pps_blog_order');
        if (is_array($order)) {
            usort($ids, function ($a, $b) use ($order) {
                return [(int) (isset($order[$a]) ? $order[$a] : 999), $a] <=> [(int) (isset($order[$b]) ? $order[$b] : 999), $b];
            });
        }
        $mode = Tools::getValue('pps_blog_mode') === 'series' ? 'series' : 'at';
        $res = ProductSchedulerBlog::schedule(
            $this->module,
            $ids,
            (string) Tools::getValue('pps_blog_action'),
            $mode,
            (string) Tools::getValue('pps_blog_at'),
            (string) Tools::getValue('pps_blog_start'),
            (string) Tools::getValue('pps_blog_time'),
            (int) Tools::getValue('pps_blog_every', 1),
            (bool) Tools::getValue('pps_blog_new'),
            $this->context->employee
        );
        $text = $this->ppsAddResultText($res);
        if ($res['created'] && $mode === 'series') {
            $text .= ' ' . sprintf($this->ppsT('From %1$s to %2$s.'), $this->module->formatDate($res['first']), $this->module->formatDate($res['last']));
        }

        return $text;
    }

    private function ppsAddResultText(array $result)
    {
        $text = sprintf($this->ppsT('%d schedule(s) created.'), $result['created']);
        if ($result['created'] === 0 && !$result['skipped']) {
            $text = $this->ppsT('These schedules already exist - nothing was added.');
        }
        if ($result['skipped']) {
            $text .= ' ' . implode(' ', $result['skipped']);
        }

        return $text;
    }

    /** 'all' or the chosen shop IDs (without multistore always 'all'). */
    private function ppsRequestedShops()
    {
        if (!Shop::isFeatureActive() || Tools::getValue('pps_shop_all')) {
            return 'all';
        }
        $shops = Tools::getValue('pps_shops');

        return is_array($shops) ? array_map('intval', $shops) : [];
    }

    /** One row: "cancel:12", "run:12", "delete:12", "reschedule:12". */
    private function ppsProcessRow()
    {
        $parts = explode(':', (string) Tools::getValue('pps_row'), 2);
        $op = $parts[0];
        $id = isset($parts[1]) ? (int) $parts[1] : 0;
        $row = $this->ppsManageableRow($id);

        switch ($op) {
            case 'cancel':
                $ok = ProductSchedulerRepository::cancel($id);
                ProductSchedulerRepository::refreshNextDue();

                return $ok ? $this->ppsT('Schedule cancelled.') : $this->ppsNotPendingText();
            case 'reschedule':
                $date = ProductSchedulerService::validFutureDate($this->module, (string) Tools::getValue('pps_at_' . $id));
                $ok = ProductSchedulerRepository::reschedule($id, $date);
                ProductSchedulerRepository::refreshNextDue();

                return $ok ? sprintf($this->ppsT('New time saved: %s.'), $this->module->formatDate($date)) : $this->ppsNotPendingText();
            case 'run':
                if ($row['status'] !== ProductSchedulerRepository::STATUS_PENDING) {
                    return $this->ppsNotPendingText();
                }

                return $this->ppsSummaryText(ProductSchedulerRunner::runDue('manual', $id));
            case 'delete':
                return ProductSchedulerRepository::delete($id)
                    ? $this->ppsT('Entry deleted.')
                    : $this->ppsT('Only finished, failed or cancelled entries can be deleted. Cancel a waiting schedule first.');
        }

        throw new ProductSchedulerError($this->ppsT('Unknown action.'));
    }

    private function ppsProcessBulk()
    {
        $op = (string) Tools::getValue('pps_bulk_action');
        $ids = Tools::getValue('pps_ids');
        $ids = is_array($ids) ? array_values(array_unique(array_filter(array_map('intval', $ids)))) : [];
        if (!$ids) {
            throw new ProductSchedulerError($this->ppsT('Select at least one entry.'));
        }
        if (!in_array($op, ['cancel', 'delete'], true)) {
            throw new ProductSchedulerError($this->ppsT('Choose a bulk action.'));
        }

        $done = 0;
        foreach (array_slice($ids, 0, 1000) as $id) {
            $row = ProductSchedulerRepository::get($id);
            if (!$row || !ProductSchedulerService::canManageRow($row, $this->context->employee)) {
                continue;
            }
            $done += (int) ($op === 'cancel' ? ProductSchedulerRepository::cancel($id) : ProductSchedulerRepository::delete($id));
        }
        ProductSchedulerRepository::refreshNextDue();

        return $op === 'cancel'
            ? sprintf($this->ppsT('%d schedule(s) cancelled.'), $done)
            : sprintf($this->ppsT('%d entries deleted.'), $done);
    }

    /** @throws ProductSchedulerError */
    private function ppsManageableRow($id)
    {
        $row = $id ? ProductSchedulerRepository::get($id) : null;
        if (!$row) {
            throw new ProductSchedulerError($this->ppsT('This schedule no longer exists.'));
        }
        if (!ProductSchedulerService::canManageRow($row, $this->context->employee)) {
            throw new ProductSchedulerError($this->ppsT('You do not have access to the shop(s) of this schedule.'));
        }

        return $row;
    }

    private function ppsNotPendingText()
    {
        return $this->ppsT('This schedule is no longer waiting (it already ran or was cancelled).');
    }

    private function ppsSummaryText(array $summary)
    {
        if ($summary['busy']) {
            return $this->ppsT('Another run is in progress. Try again in a minute.');
        }
        if (!$summary['processed']) {
            return $this->ppsT('Nothing was due.');
        }

        return sprintf($this->ppsT('Run finished: %1$d done, %2$d failed.'), $summary['done'], $summary['failed']);
    }

    private function ppsFlash($message, $type)
    {
        $this->context->cookie->__set('pps_flash', json_encode(['m' => (string) $message, 't' => (string) $type]));
        $this->context->cookie->write();
    }

    private function ppsTakeFlash()
    {
        $raw = isset($this->context->cookie->pps_flash) ? (string) $this->context->cookie->pps_flash : '';
        if ($raw === '') {
            return null;
        }
        $this->context->cookie->__unset('pps_flash');
        $this->context->cookie->write();
        $data = json_decode($raw, true);

        return is_array($data) && isset($data['m'], $data['t'])
            && in_array($data['t'], ['success', 'warning', 'danger'], true) ? $data : null;
    }

    // -----------------------------------------------------------------------
    // JSON endpoint (product tab, product picker)
    // -----------------------------------------------------------------------

    private function ppsAjaxDispatch()
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        $op = (string) Tools::getValue('ppsAction');

        try {
            if (!$this->ppsIsPost() || !$this->ppsValidToken()) {
                throw new ProductSchedulerError($this->ppsT('Your session expired. Reload the page and try again.'));
            }
            $readOnly = in_array($op, ['search', 'product'], true);
            if (!$this->module->employeeCanSchedule($readOnly ? 'view' : 'edit')) {
                throw new ProductSchedulerError($this->ppsT('You do not have permission to change schedules.'));
            }

            $message = '';
            $id_product = (int) Tools::getValue('id_product');
            switch ($op) {
                case 'search':
                    $data = $this->ppsSearchProducts((string) Tools::getValue('q'));
                    break;
                case 'product':
                    $data = $this->module->productTabData($id_product);
                    break;
                case 'add':
                    $result = ProductSchedulerService::addSchedules(
                        $this->module,
                        [$id_product],
                        (string) Tools::getValue('pps_action'),
                        (string) Tools::getValue('pps_at'),
                        (string) Tools::getValue('pps_end_at'),
                        $this->ppsRequestedShops(),
                        (bool) Tools::getValue('pps_mark_new'),
                        $this->context->employee
                    );
                    if (!$result['created'] && $result['skipped']) {
                        throw new ProductSchedulerError(implode(' ', $result['skipped']));
                    }
                    $message = $this->ppsAddResultText($result);
                    $data = $this->module->productTabData($id_product);
                    break;
                case 'cancel':
                case 'run':
                case 'delete':
                    $row = $this->ppsManageableRow((int) Tools::getValue('id'));
                    if ((int) $row['id_product'] !== $id_product) {
                        throw new ProductSchedulerError($this->ppsT('This schedule no longer exists.'));
                    }
                    $_POST['pps_row'] = $op . ':' . (int) $row['id_productscheduler'];
                    $message = $this->ppsProcessRow();
                    $data = $this->module->productTabData($id_product);
                    break;
                default:
                    throw new ProductSchedulerError($this->ppsT('Unknown action.'));
            }
            $out = ['success' => true, 'message' => $message, 'data' => $data];
        } catch (ProductSchedulerError $e) {
            $out = ['success' => false, 'error' => $e->getMessage()];
        } catch (Exception $e) {
            PrestaShopLogger::addLog('[productscheduler] ' . $e->getMessage(), 3, null, 'Product', 0, true);
            $out = ['success' => false, 'error' => $this->ppsT('Something went wrong. The details were written to the PrestaShop log.')];
        }

        die(json_encode($out));
    }

    private function ppsSearchProducts($q)
    {
        $out = [];
        $id_shop = (int) $this->context->shop->id;
        foreach (ProductSchedulerRepository::searchProducts($q, (int) $this->context->language->id, $id_shop) as $row) {
            $out[] = [
                'id' => (int) $row['id_product'],
                'name' => (string) $row['name'],
                'reference' => (string) $row['reference'],
                'active' => (bool) $row['active'],
            ];
        }

        return $out;
    }

    // -----------------------------------------------------------------------
    // List page
    // -----------------------------------------------------------------------

    /** List state from the URL (forms post to the current list URL, so it survives every action). */
    private function ppsCurrentFilters()
    {
        $get = function ($key, $default) {
            $value = isset($_GET[$key]) ? $_GET[$key] : $default;

            return is_string($value) || is_int($value) ? (string) $value : $default;
        };

        $status = $get('f_status', 'upcoming');
        $action = $get('f_action', '');
        $perPage = (int) $get('n', 50);

        return [
            'f_status' => in_array($status, ['upcoming', 'all', 'done', 'failed', 'cancelled'], true) ? $status : 'upcoming',
            'f_action' => ProductSchedulerRepository::isValidAction($action) ? $action : '',
            'f_type' => in_array($get('f_type', ''), [ProductSchedulerRepository::TYPE_PRODUCT, ProductSchedulerRepository::TYPE_BLOG], true) ? $get('f_type', '') : '',
            'f_q' => Tools::substr(trim($get('f_q', '')), 0, 100),
            'page' => max(1, (int) $get('page', 1)),
            'n' => in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : 50,
        ];
    }

    private function ppsListUrl(array $filters)
    {
        return $this->context->link->getAdminLink('AdminProductScheduler') . '&' . http_build_query(array_filter($filters, function ($v) {
            return $v !== '' && $v !== null;
        }));
    }

    public function initContent()
    {
        $filters = $this->ppsCurrentFilters();
        $shopIds = $this->module->visibleShopIds();
        list($rows, $total) = ProductSchedulerRepository::getList(
            ['status' => $filters['f_status'], 'action' => $filters['f_action'], 'type' => $filters['f_type'], 'q' => $filters['f_q']],
            $shopIds,
            $filters['page'],
            $filters['n']
        );
        $pages = max(1, (int) ceil($total / $filters['n']));

        $id_lang = (int) $this->context->language->id;
        $blogNames = ProductSchedulerBlog::names(array_column(array_filter($rows, function ($r) { return isset($r['type']) && $r['type'] === ProductSchedulerRepository::TYPE_BLOG; }), 'id_product'), $id_lang);
        $items = [];
        foreach ($rows as $row) {
            $item = $this->module->formatRow($row);
            if ($item['type'] === ProductSchedulerRepository::TYPE_BLOG) {
                $item['product_name'] = isset($blogNames[$item['id_product']]) ? $blogNames[$item['id_product']] : '';
                $item['shop_name'] = $this->ppsT('All shops');
                $item['edit_url'] = ProductSchedulerBlog::editUrl($item['id_product']);
                $item['can_manage'] = ProductSchedulerService::canManageRow($row, $this->context->employee);
                $items[] = $item;
                continue;
            }
            $item['edit_url'] = $this->ppsProductEditUrl((int) $row['id_product']);
            $item['can_manage'] = ProductSchedulerService::canManageRow($row, $this->context->employee);
            $items[] = $item;
        }

        $pageUrls = [];
        for ($p = max(1, $filters['page'] - 3); $p <= min($pages, $filters['page'] + 3); ++$p) {
            $pageUrls[$p] = $this->ppsListUrl(array_merge($filters, ['page' => $p]));
        }
        $statusTabs = [];
        foreach (['upcoming', 'done', 'failed', 'cancelled', 'all'] as $status) {
            $statusTabs[$status] = $this->ppsListUrl(array_merge($filters, ['f_status' => $status, 'page' => 1]));
        }

        // The filter form is a GET form: the controller/token query of the page URL goes into hidden fields.
        $baseUrl = $this->context->link->getAdminLink('AdminProductScheduler');
        $filterAction = strtok($baseUrl, '?');
        $filterHidden = [];
        parse_str((string) parse_url($baseUrl, PHP_URL_QUERY), $filterHidden);

        $employee = $this->context->employee;
        $shops = [];
        if (Shop::isFeatureActive()) {
            $allowed = ProductSchedulerService::employeeShopIds($employee);
            foreach (Shop::getShops(false) as $shop) {
                if (in_array((int) $shop['id_shop'], $allowed, true)) {
                    $shops[] = ['id' => (int) $shop['id_shop'], 'name' => $shop['name']];
                }
            }
        }

        // blog articles (ST Blog) for the planning panel
        $blog = ['available' => ProductSchedulerBlog::available(), 'articles' => []];
        if ($blog['available']) {
            foreach (ProductSchedulerBlog::articles($id_lang, (int) $this->context->shop->id) as $a) {
                $a['date'] = $this->module->formatDate($a['date_add']);
                $a['edit_url'] = ProductSchedulerBlog::editUrl($a['id']);
                $a['next_at'] = $a['next'] ? (string) $a['next']['scheduled_at'] : '';
                if ($a['next']) {
                    $a['next'] = ['id' => (int) $a['next']['id_productscheduler'], 'when' => $this->module->formatDate($a['next']['scheduled_at']), 'action' => $a['next']['action']];
                }
                $blog['articles'][] = $a;
            }
            // planning order: offline and not planned (oldest written first), then planned (by date), then online (newest first)
            usort($blog['articles'], function ($a, $b) {
                $group = function ($x) { return $x['active'] ? 2 : ($x['next'] ? 1 : 0); };
                if ($group($a) !== $group($b)) {
                    return $group($a) <=> $group($b);
                }
                if ($group($a) === 0) {
                    return [$a['date_add'], $a['id']] <=> [$b['date_add'], $b['id']];
                }
                if ($group($a) === 1) {
                    return strcmp($a['next_at'], $b['next_at']);
                }

                return [$b['date_add'], $b['id']] <=> [$a['date_add'], $a['id']];
            });
            // a new series continues the day after the last planned article (or tomorrow)
            $last = ProductSchedulerRepository::lastPendingPublish(ProductSchedulerRepository::TYPE_BLOG);
            $blog['start'] = date('Y-m-d', strtotime(($last !== '' ? substr($last, 0, 10) : date('Y-m-d')) . ' +1 day'));
            $blog['time'] = $last !== '' ? substr($last, 11, 5) : '10:00';
            $blog['unplanned'] = count(array_filter($blog['articles'], function ($a) { return !$a['active'] && !$a['next']; }));
        }

        $this->context->smarty->assign([
            'pps_blog' => $blog,
            'pps_type_tabs' => [
                '' => $this->ppsListUrl(array_merge($filters, ['f_type' => '', 'page' => 1])),
                'product' => $this->ppsListUrl(array_merge($filters, ['f_type' => 'product', 'page' => 1])),
                'blog' => $this->ppsListUrl(array_merge($filters, ['f_type' => 'blog', 'page' => 1])),
            ],
            'pps_items' => $items,
            'pps_total' => $total,
            'pps_filters' => $filters,
            'pps_page' => $filters['page'],
            'pps_pages' => $pages,
            'pps_page_urls' => $pageUrls,
            'pps_first_url' => $this->ppsListUrl(array_merge($filters, ['page' => 1])),
            'pps_last_url' => $this->ppsListUrl(array_merge($filters, ['page' => $pages])),
            'pps_per_page_options' => self::PER_PAGE_OPTIONS,
            'pps_status_tabs' => $statusTabs,
            'pps_counts' => ProductSchedulerRepository::countByStatus($shopIds),
            'pps_runner' => $this->module->runnerStatus(),
            'pps_flash' => $this->ppsTakeFlash(),
            'pps_can_edit' => $this->module->employeeCanSchedule('edit'),
            'pps_multistore' => Shop::isFeatureActive(),
            'pps_shops' => $shops,
            'pps_form_url' => $this->ppsListUrl($filters),
            // Loaded from the template: addCSS() on 1.7 drops files with a version query.
            'pps_css_url' => $this->module->getPathUri() . 'views/css/admin.css?v=' . ProductScheduler::MODULE_VERSION,
            'pps_js_url' => $this->module->getPathUri() . 'views/js/admin.js?v=' . ProductScheduler::MODULE_VERSION,
            'pps_filter_action' => $filterAction,
            'pps_filter_hidden' => $filterHidden,
            'pps_token' => $this->module->adminToken(),
            'pps_settings_url' => $this->context->link->getAdminLink('AdminModules', true, [], ['configure' => $this->module->name]),
            'pps_min_input' => date('Y-m-d\TH:i'),
            'pps_config_json' => json_encode([
                'mode' => 'list',
                'ajaxUrl' => $this->context->link->getAdminLink('AdminProductScheduler'),
                'token' => $this->module->adminToken(),
                'serverNow' => date('Y-m-d H:i:s'),
                'i18n' => $this->module->jsTranslations(),
            ]),
        ]);
        $this->module->assignFooterVars();

        $this->content = $this->context->smarty->fetch($this->module->getLocalPath() . 'views/templates/admin/manage.tpl');
        parent::initContent();
    }

    private function ppsProductEditUrl($id_product)
    {
        try {
            return $this->context->link->getAdminLink('AdminProducts', true, ['id_product' => (int) $id_product], ['id_product' => (int) $id_product, 'updateproduct' => 1]);
        } catch (Exception $e) {
            return $this->context->link->getAdminLink('AdminProducts') . '&id_product=' . (int) $id_product . '&updateproduct';
        }
    }
}
