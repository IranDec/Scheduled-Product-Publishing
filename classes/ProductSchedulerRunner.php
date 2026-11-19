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
 * Executes due schedules. Started by the cron URL, the CLI script, a shop / back-office visit
 * (after the response was sent) or the "Run now" buttons.
 *
 * - One run at a time (MySQL named lock), every row is claimed before it is executed.
 * - Products are changed through Product::update() in the target shop's context, so PrestaShop
 *   and other modules see a normal product save (hooks, caches, search index). When the object
 *   cannot be saved (e.g. old invalid product data) only the status columns are written.
 * - A run that dies leaves rows in "running"; they are retried after 15 minutes (3 tries).
 * - Blog articles (ST Blog) are switched by ProductSchedulerBlog; the caches are cleared once per run.
 */
class ProductSchedulerRunner
{
    const STALE_MINUTES = 15;
    const MAX_ATTEMPTS = 3;

    /** @var bool */
    private static $deferred = false;

    /** Runs due schedules at the end of this request, after the visitor got the page. */
    public static function deferAfterResponse()
    {
        if (self::$deferred) {
            return;
        }
        self::$deferred = true;
        register_shutdown_function([__CLASS__, 'runDeferred']);
    }

    public static function runDeferred()
    {
        ignore_user_abort(true);
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        } elseif (function_exists('litespeed_finish_request')) {
            @litespeed_finish_request();
        }
        @set_time_limit(120);

        try {
            self::runDue('visit');
        } catch (Exception $e) {
            self::log('visit run failed: ' . $e->getMessage(), 3);
        } catch (Throwable $e) {
            self::log('visit run failed: ' . $e->getMessage(), 3);
        }
    }

    /**
     * @param string $source cron | cli | visit | manual
     * @param int $onlyId run just this pending schedule (the "Run now" button), even if not due yet
     *
     * @return array summary: processed, done, failed, busy, items[]
     */
    public static function runDue($source, $onlyId = 0)
    {
        $now = date('Y-m-d H:i:s');
        $summary = ['ok' => true, 'source' => $source, 'time' => $now, 'busy' => false, 'processed' => 0, 'done' => 0, 'failed' => 0, 'items' => []];

        if ($source === 'cron' || $source === 'cli') {
            Configuration::updateGlobalValue('PRODUCTSCHEDULER_LAST_CRON', $now);
        }
        if (!self::lock()) {
            $summary['busy'] = true;

            return $summary;
        }

        try {
            ProductSchedulerRepository::recoverStale(self::STALE_MINUTES, self::MAX_ATTEMPTS);

            if ($onlyId) {
                $row = ProductSchedulerRepository::get($onlyId);
                $rows = $row && $row['status'] === ProductSchedulerRepository::STATUS_PENDING ? [$row] : [];
            } else {
                $batch = (int) Configuration::getGlobalValue('PRODUCTSCHEDULER_BATCH');
                $rows = ProductSchedulerRepository::due($now, $batch >= 1 && $batch <= 1000 ? $batch : 100);
            }

            $blogChanged = false;
            foreach ($rows as $row) {
                if (!ProductSchedulerRepository::claim((int) $row['id_productscheduler'])) {
                    continue;
                }
                $isBlog = isset($row['type']) && $row['type'] === ProductSchedulerRepository::TYPE_BLOG;
                list($ok, $message) = $isBlog ? self::executeBlog($row) : self::execute($row);
                $blogChanged = $blogChanged || ($isBlog && $ok);
                ProductSchedulerRepository::finish((int) $row['id_productscheduler'], $ok, $message);

                ++$summary['processed'];
                ++$summary[$ok ? 'done' : 'failed'];
                $summary['items'][] = [
                    'id' => (int) $row['id_productscheduler'],
                    'type' => $isBlog ? ProductSchedulerRepository::TYPE_BLOG : ProductSchedulerRepository::TYPE_PRODUCT,
                    'id_product' => (int) $row['id_product'],
                    'id_shop' => (int) $row['id_shop'],
                    'action' => $row['action'],
                    'scheduled_at' => $row['scheduled_at'],
                    'ok' => $ok,
                    'message' => $message,
                ];
            }

            if ($blogChanged) {
                ProductSchedulerBlog::clearCaches();
            }
            Configuration::updateGlobalValue('PRODUCTSCHEDULER_LAST_RUN', $now);
            Configuration::updateGlobalValue('PRODUCTSCHEDULER_LAST_SOURCE', $source);

            // History clean-up, once a day.
            if ((string) Configuration::getGlobalValue('PRODUCTSCHEDULER_LAST_PURGE') !== date('Y-m-d')) {
                ProductSchedulerRepository::purgeHistory((int) Configuration::getGlobalValue('PRODUCTSCHEDULER_HISTORY_DAYS'));
                Configuration::updateGlobalValue('PRODUCTSCHEDULER_LAST_PURGE', date('Y-m-d'));
            }
        } catch (Exception $e) {
            $summary['ok'] = false;
            self::log('run failed: ' . $e->getMessage(), 3);
        } catch (Throwable $e) {
            $summary['ok'] = false;
            self::log('run failed: ' . $e->getMessage(), 3);
        }

        ProductSchedulerRepository::refreshNextDue();
        self::unlock();

        if ($summary['items']) {
            self::notify($summary);
        }

        return $summary;
    }

    /** Blog article (ST Blog): online / offline in the row's shop or every shop. @return array [bool ok, string message] */
    private static function executeBlog(array $row)
    {
        if (!ProductSchedulerBlog::available()) {
            return [false, 'The blog module (ST Blog) is not installed.'];
        }
        try {
            return ProductSchedulerBlog::apply(
                (int) $row['id_product'],
                $row['action'] === ProductSchedulerRepository::ACTION_PUBLISH ? 1 : 0,
                (int) $row['mark_new'],
                (int) $row['id_shop'] > 0 ? [(int) $row['id_shop']] : []
            );
        } catch (Exception $e) {
            return [false, 'Error: ' . $e->getMessage()];
        } catch (Throwable $e) {
            return [false, 'Error: ' . $e->getMessage()];
        }
    }

    /** @return array [bool ok, string message] */
    private static function execute(array $row)
    {
        $id_product = (int) $row['id_product'];
        $active = $row['action'] === ProductSchedulerRepository::ACTION_PUBLISH ? 1 : 0;
        $markNew = $active && (int) $row['mark_new'];

        if (!ProductSchedulerRepository::isValidAction($row['action'])) {
            return [false, 'Unknown action.'];
        }
        $states = ProductSchedulerRepository::productShopStates($id_product);
        if (!$states) {
            return [false, 'Product not found (deleted?).'];
        }
        if ((int) $row['id_shop'] > 0) {
            if (!isset($states[(int) $row['id_shop']])) {
                return [false, 'The product is no longer assigned to this shop.'];
            }
            $shops = [(int) $row['id_shop']];
        } else {
            $shops = array_keys($states);
        }

        $changed = [];
        $notes = [];
        $context = self::saveShopContext();
        try {
            foreach ($shops as $id_shop) {
                if ((int) $states[$id_shop]['active'] === $active && !$markNew) {
                    continue;
                }
                $note = self::applyToShop($id_product, $id_shop, $active, $markNew);
                $changed[] = $id_shop;
                if ($note !== '') {
                    $notes[] = $note;
                }
            }
        } catch (Exception $e) {
            self::restoreShopContext($context);

            return [false, 'Error: ' . $e->getMessage()];
        } catch (Throwable $e) {
            self::restoreShopContext($context);

            return [false, 'Error: ' . $e->getMessage()];
        }
        self::restoreShopContext($context);

        if (!$changed) {
            return [true, $active ? 'Already online - nothing to change.' : 'Already offline - nothing to change.'];
        }
        if ($active) {
            self::reindex($id_product);
        }

        $message = ($active ? 'Published' : 'Unpublished')
            . (count($shops) > 1 || (int) $row['id_shop'] > 0 ? ' (shops: ' . implode(', ', $changed) . ')' : '')
            . ($markNew ? ', shown as new' : '') . '.';

        return [true, $message . ($notes ? ' ' . implode(' ', array_unique($notes)) : '')];
    }

    /** @return string note for the log ('' = normal save) */
    private static function applyToShop($id_product, $id_shop, $active, $markNew)
    {
        $now = date('Y-m-d H:i:s');
        Shop::setContext(Shop::CONTEXT_SHOP, (int) $id_shop);

        $product = new Product((int) $id_product, false, null, (int) $id_shop);
        if (!Validate::isLoadedObject($product)) {
            throw new Exception('product could not be loaded in shop ' . (int) $id_shop);
        }
        $product->active = (int) $active;
        if ($markNew) {
            $product->date_add = $now;
        }
        $product->id_shop_list = [(int) $id_shop];

        try {
            if ($product->update()) {
                return '';
            }
            $reason = 'update() returned false';
        } catch (Exception $e) {
            $reason = $e->getMessage();
        } catch (Throwable $e) {
            $reason = $e->getMessage();
        }

        // Fallback: write the status columns directly and tell other modules about it.
        $db = Db::getInstance();
        $fields = ['active' => (int) $active, 'date_upd' => $now];
        if ($markNew) {
            $fields['date_add'] = $now;
        }
        $db->update('product_shop', $fields, '`id_product` = ' . (int) $id_product . ' AND `id_shop` = ' . (int) $id_shop);
        if ((int) $product->id_shop_default === (int) $id_shop) {
            $db->update('product', $fields, '`id_product` = ' . (int) $id_product);
        }
        self::log('product ' . (int) $id_product . ' saved with the status fallback (' . $reason . ')', 2);
        try {
            Hook::exec('actionProductUpdate', ['id_product' => (int) $id_product, 'product' => new Product((int) $id_product)]);
        } catch (Exception $e) {
            self::log('actionProductUpdate after fallback: ' . $e->getMessage(), 2);
        }

        return 'Saved with the status fallback (' . Tools::substr($reason, 0, 80) . ').';
    }

    private static function reindex($id_product)
    {
        try {
            if (!Configuration::get('PS_SEARCH_INDEXATION')) {
                return;
            }
            $visibility = (string) Db::getInstance()->getValue('SELECT `visibility` FROM `' . _DB_PREFIX_ . 'product`
                WHERE `id_product` = ' . (int) $id_product, false);
            if (in_array($visibility, ['both', 'search'], true)) {
                Search::indexation(false, (int) $id_product);
            }
        } catch (Exception $e) {
            self::log('search index of product ' . (int) $id_product . ': ' . $e->getMessage(), 2);
        }
    }

    private static function saveShopContext()
    {
        $type = Shop::getContext();
        $id = null;
        if ($type === Shop::CONTEXT_SHOP) {
            $id = Shop::getContextShopID();
        } elseif ($type === Shop::CONTEXT_GROUP) {
            $id = Shop::getContextShopGroupID();
        }

        return [$type, $id];
    }

    private static function restoreShopContext(array $saved)
    {
        try {
            Shop::setContext($saved[0], $saved[1]);
        } catch (Exception $e) {
            self::log('could not restore the shop context: ' . $e->getMessage(), 2);
        }
    }

    // -----------------------------------------------------------------------
    // Lock
    // -----------------------------------------------------------------------

    private static function lockName()
    {
        return 'pps_' . md5(_DB_NAME_ . '|' . _DB_PREFIX_);
    }

    private static function lock()
    {
        $result = Db::getInstance()->getValue('SELECT GET_LOCK(\'' . self::lockName() . '\', 0)', false);

        // 0 = held by another run. NULL/false (lock functions unavailable): rows are still claimed one by one.
        return (string) $result !== '0';
    }

    private static function unlock()
    {
        Db::getInstance()->getValue('SELECT RELEASE_LOCK(\'' . self::lockName() . '\')', false);
    }

    // -----------------------------------------------------------------------
    // Report e-mail
    // -----------------------------------------------------------------------

    private static function notify(array $summary)
    {
        if ((int) Configuration::getGlobalValue('PRODUCTSCHEDULER_NOTIFY')) {
            self::sendReport($summary);
        }
    }

    /** The report address: the configured one, or the shop e-mail address ('' when none is valid). */
    public static function reportRecipient()
    {
        $to = trim((string) Configuration::getGlobalValue('PRODUCTSCHEDULER_NOTIFY_EMAIL'));
        if ($to === '') {
            $to = (string) Configuration::get('PS_SHOP_EMAIL');
        }

        return Validate::isEmail($to) ? $to : '';
    }

    /**
     * Sends the run report (also the settings page's test e-mail). Items may carry a 'name' for
     * products that do not exist (test e-mail). Never throws.
     *
     * @return bool sent
     */
    public static function sendReport(array $summary)
    {
        try {
            $to = self::reportRecipient();
            $module = Module::getInstanceByName('productscheduler');
            if ($to === '' || !$module) {
                return false;
            }
            $mailDir = _PS_MODULE_DIR_ . 'productscheduler/mails/';
            $id_lang = (int) Configuration::get('PS_LANG_DEFAULT');
            if (!is_file($mailDir . Language::getIsoById($id_lang) . '/productscheduler_report.txt')) {
                $id_lang = (int) Language::getIdByIso('en');
            }
            if (!$id_lang) {
                return false;
            }

            $publishLabel = $module->l('Publish', 'productscheduler');
            $unpublishLabel = $module->l('Unpublish', 'productscheduler');
            $okLabel = $module->l('Done', 'productscheduler');
            $failLabel = $module->l('Failed', 'productscheduler');
            $cell = 'padding:10px 12px;border-bottom:1px solid #edf1f3;font-size:13px;vertical-align:top;';
            $pill = 'display:inline-block;padding:2px 10px;border-radius:999px;font-size:12px;font-weight:bold;';

            $htmlRows = '';
            $textRows = '';
            $blogLabel = $module->l('Blog article', 'productscheduler');
            $blogIds = [];
            foreach ($summary['items'] as $item) {
                if (isset($item['type']) && $item['type'] === ProductSchedulerRepository::TYPE_BLOG) {
                    $blogIds[] = (int) $item['id_product'];
                }
            }
            $blogNames = ProductSchedulerBlog::names($blogIds, $id_lang);
            foreach ($summary['items'] as $item) {
                $isBlog = isset($item['type']) && $item['type'] === ProductSchedulerRepository::TYPE_BLOG;
                $name = isset($item['name']) ? $item['name'] : ($isBlog
                    ? $blogLabel . ': ' . (isset($blogNames[(int) $item['id_product']]) ? $blogNames[(int) $item['id_product']] : '#' . (int) $item['id_product'])
                    : Product::getProductName((int) $item['id_product'], null, $id_lang));
                $publish = $item['action'] === ProductSchedulerRepository::ACTION_PUBLISH;
                $label = $publish ? $publishLabel : $unpublishLabel;
                $state = $item['ok'] ? $okLabel : $failLabel;
                $htmlRows .= '<tr>'
                    . '<td style="' . $cell . '"><strong>' . htmlspecialchars((string) $name, ENT_QUOTES, 'UTF-8') . '</strong>'
                    . ((int) $item['id_product'] ? '<br><span style="color:#8a9ba1;font-size:12px">ID ' . (int) $item['id_product'] . '</span>' : '') . '</td>'
                    . '<td style="' . $cell . '"><span style="' . $pill . ($publish ? 'background:#e8f5e9;color:#2e7d32' : 'background:#eceff1;color:#455a64') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span></td>'
                    . '<td style="' . $cell . '"><span style="' . $pill . ($item['ok'] ? 'background:#e8f5e9;color:#2e7d32' : 'background:#fdecea;color:#c62828') . '">' . htmlspecialchars($state, ENT_QUOTES, 'UTF-8') . '</span></td>'
                    . '<td style="' . $cell . 'color:#5b6b71">' . htmlspecialchars($module->messageText($item['message']), ENT_QUOTES, 'UTF-8') . '</td>'
                    . '</tr>';
                $textRows .= '- ' . ((int) $item['id_product'] ? '#' . (int) $item['id_product'] . ' ' : '') . $name . ' | ' . $label . ' | ' . $state . ' | ' . $module->messageText($item['message']) . "\n";
            }

            $base = Tools::getShopDomainSsl(true) . __PS_BASE_URI__;
            $adminUrl = self::adminListUrl($base);

            return (bool) Mail::Send(
                $id_lang,
                'productscheduler_report',
                ($summary['source'] === 'test' ? '[TEST] ' : '') . $module->l('Scheduled publishing report', 'productscheduler')
                    . ': ' . (int) $summary['done'] . ' / ' . (int) $summary['processed'],
                [
                    '{run_time}' => $summary['time'],
                    '{done}' => (int) $summary['done'],
                    '{failed}' => (int) $summary['failed'],
                    '{processed}' => (int) $summary['processed'],
                    '{rows_html}' => $htmlRows,
                    '{rows_txt}' => $textRows,
                    '{module_name}' => $module->displayName,
                    '{module_version}' => $module->version,
                    '{module_logo}' => $base . 'modules/productscheduler/logo.png',
                    '{author_name}' => ProductScheduler::AUTHOR_NAME,
                    '{author_url}' => ProductScheduler::AUTHOR_URL,
                    '{author_site}' => ProductScheduler::AUTHOR_SITE,
                    '{admin_url}' => $adminUrl !== '' ? $adminUrl : $base,
                    '{year}' => date('Y'),
                ],
                self::reportRecipient(),
                null,
                null,
                null,
                null,
                null,
                $mailDir,
                false,
                (int) Configuration::get('PS_SHOP_DEFAULT')
            );
        } catch (Exception $e) {
            self::log('report e-mail not sent: ' . $e->getMessage(), 2);
        } catch (Throwable $e) {
            self::log('report e-mail not sent: ' . $e->getMessage(), 2);
        }

        return false;
    }

    /** Back-office list page (without token: PrestaShop asks once to confirm the link). */
    private static function adminListUrl($base)
    {
        $adminDir = defined('_PS_ADMIN_DIR_') ? basename(_PS_ADMIN_DIR_) : '';
        if ($adminDir === '') {
            // Cron / shop request: find the back-office folder.
            foreach (glob(_PS_ROOT_DIR_ . '/*/index.php') ?: [] as $file) {
                if (is_file(dirname($file) . '/init.php') && is_dir(dirname($file) . '/themes')) {
                    $adminDir = basename(dirname($file));
                    break;
                }
            }
        }

        return $adminDir !== '' ? $base . $adminDir . '/index.php?controller=AdminProductScheduler' : '';
    }

    private static function log($message, $severity)
    {
        try {
            PrestaShopLogger::addLog('[productscheduler] ' . $message, (int) $severity, null, 'Product', 0, true);
        } catch (Exception $e) {
            // Logging must never break a run.
        }
    }
}
