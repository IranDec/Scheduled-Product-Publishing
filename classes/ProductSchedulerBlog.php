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
 * Blog articles of the "ST Blog" module (stblog, Panda / Transformer themes) as a second kind of schedule.
 *
 * An article is online when `st_blog`.`active` and its `st_blog_shop`.`active` are 1 (the blog reads both).
 * Publishing sets both and, with "show as new", sets the article date to the publish time so the blog lists it
 * as the newest article. Schedules use the same table as products (`type` = blog, `id_product` = article ID).
 */
class ProductSchedulerBlog
{
    /** @var bool|null */
    private static $available;

    /** True when ST Blog is installed and its tables exist. */
    public static function available()
    {
        if (self::$available === null) {
            self::$available = Module::isInstalled('stblog')
                && (bool) Db::getInstance()->executeS('SHOW TABLES LIKE \'' . pSQL(_DB_PREFIX_ . 'st_blog_shop') . '\'');
        }

        return self::$available;
    }

    /**
     * Articles of a shop with their state and next waiting schedule, newest first.
     *
     * @return array[] id, name, active, date_add, category, next (row of the next pending schedule or null)
     */
    public static function articles($id_lang, $id_shop)
    {
        if (!self::available()) {
            return [];
        }
        $rows = Db::getInstance()->executeS('SELECT b.`id_st_blog` AS id, bl.`name`, bs.`active` AS active_shop, b.`active`, b.`date_add`,
                cl.`name` AS category
            FROM `' . _DB_PREFIX_ . 'st_blog` b
            INNER JOIN `' . _DB_PREFIX_ . 'st_blog_shop` bs ON bs.`id_st_blog` = b.`id_st_blog` AND bs.`id_shop` = ' . (int) $id_shop . '
            LEFT JOIN `' . _DB_PREFIX_ . 'st_blog_lang` bl ON bl.`id_st_blog` = b.`id_st_blog` AND bl.`id_lang` = ' . (int) $id_lang . '
            LEFT JOIN `' . _DB_PREFIX_ . 'st_blog_category_lang` cl ON cl.`id_st_blog_category` = b.`id_st_blog_category_default` AND cl.`id_lang` = ' . (int) $id_lang . '
            ORDER BY b.`date_add` DESC, b.`id_st_blog` DESC', true, false) ?: [];
        $next = ProductSchedulerRepository::nextPendingFor(ProductSchedulerRepository::TYPE_BLOG, array_column($rows, 'id'));
        foreach ($rows as &$r) {
            $r['id'] = (int) $r['id'];
            $r['active'] = (int) $r['active'] && (int) $r['active_shop'];
            $r['next'] = isset($next[$r['id']]) ? $next[$r['id']] : null;
        }

        return $rows;
    }

    /** [id => name] */
    public static function names(array $ids, $id_lang)
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids || !self::available()) {
            return [];
        }
        $out = [];
        foreach (Db::getInstance()->executeS('SELECT `id_st_blog`, `name` FROM `' . _DB_PREFIX_ . 'st_blog_lang`
            WHERE `id_lang` = ' . (int) $id_lang . ' AND `id_st_blog` IN (' . implode(',', $ids) . ')', true, false) ?: [] as $r) {
            $out[(int) $r['id_st_blog']] = (string) $r['name'];
        }

        return $out;
    }

    /** Article IDs whose name contains $q (list search). */
    public static function search($q, $id_lang)
    {
        if (!self::available() || trim($q) === '') {
            return [];
        }
        $like = '\'%' . pSQL(addcslashes(trim($q), '%_\\')) . '%\'';

        return array_map('intval', array_column(Db::getInstance()->executeS('SELECT DISTINCT `id_st_blog` FROM `' . _DB_PREFIX_ . 'st_blog_lang`
            WHERE `id_lang` = ' . (int) $id_lang . ' AND `name` LIKE ' . $like . ' LIMIT 200', true, false) ?: [], 'id_st_blog'));
    }

    public static function exists($id)
    {
        return self::available() && (bool) Db::getInstance()->getValue('SELECT 1 FROM `' . _DB_PREFIX_ . 'st_blog` WHERE `id_st_blog` = ' . (int) $id, false);
    }

    /** Back-office edit link of an article. */
    public static function editUrl($id)
    {
        try {
            return Context::getContext()->link->getAdminLink('AdminStBlog') . '&id_st_blog=' . (int) $id . '&updatest_blog';
        } catch (Exception $e) {
            return '';
        }
    }

    /**
     * Publishes / unpublishes one article in the given shops (empty = every shop of the article).
     *
     * @return array [bool ok, string message]
     */
    public static function apply($id, $active, $markNew, array $shopIds = [])
    {
        if (!self::exists($id)) {
            return [false, 'Blog article not found (deleted?).'];
        }
        $db = Db::getInstance();
        $now = date('Y-m-d H:i:s');
        $where = '`id_st_blog` = ' . (int) $id . ($shopIds ? ' AND `id_shop` IN (' . implode(',', array_map('intval', $shopIds)) . ')' : '');
        $before = (int) $db->getValue('SELECT MIN(`active`) FROM `' . _DB_PREFIX_ . 'st_blog_shop` WHERE ' . $where, false)
            && (int) $db->getValue('SELECT `active` FROM `' . _DB_PREFIX_ . 'st_blog` WHERE `id_st_blog` = ' . (int) $id, false);
        if ((int) $before === (int) $active && !($active && $markNew)) {
            return [true, $active ? 'Already online - nothing to change.' : 'Already offline - nothing to change.'];
        }
        $db->update('st_blog_shop', ['active' => (int) $active], $where);
        $fields = ['date_upd' => $now];
        if ($active) {
            $fields['active'] = 1;
        } elseif (!(int) $db->getValue('SELECT MAX(`active`) FROM `' . _DB_PREFIX_ . 'st_blog_shop` WHERE `id_st_blog` = ' . (int) $id, false)) {
            $fields['active'] = 0; // offline in every shop
        }
        if ($active && $markNew) {
            $fields['date_add'] = $now;
        }
        $db->update('st_blog', $fields, '`id_st_blog` = ' . (int) $id);

        // Blog blocks (recent / related articles ...) clear their caches on the object hooks.
        try {
            if (class_exists('StBlogClass') || (is_file(_PS_MODULE_DIR_ . 'stblog/classes/StBlogClass.php') && include_once _PS_MODULE_DIR_ . 'stblog/classes/StBlogClass.php')) {
                Hook::exec('actionObjectStBlogClassUpdateAfter', ['object' => new StBlogClass((int) $id)]);
            }
        } catch (Exception $e) {
            // caches are cleared below anyway
        } catch (Throwable $e) {
        }

        return [true, ($active ? 'Published' : 'Unpublished') . ($active && $markNew ? ', shown as new' : '') . '.'];
    }

    /** After blog changes: template caches and full-page caches, so the article shows at once. */
    public static function clearCaches()
    {
        try {
            Tools::clearSmartyCache();
            Media::clearCache();
            foreach (['stadvancedcache', 'stblog'] as $name) {
                $m = Module::isInstalled($name) ? Module::getInstanceByName($name) : null;
                if ($m && method_exists($m, 'clearAllCache')) {
                    $m->clearAllCache();
                }
            }
        } catch (Exception $e) {
            PrestaShopLogger::addLog('[productscheduler] blog cache: ' . $e->getMessage(), 2, null, 'Product', 0, true);
        }
    }

    /**
     * Creates blog schedules.
     *
     * mode "at": every article at $at. mode "series": one article per $everyDays days at $time, starting on $startDate,
     * in the given order; existing waiting blog schedules are kept.
     *
     * @return array created, skipped[], first, last
     *
     * @throws ProductSchedulerError
     */
    public static function schedule(Module $module, array $ids, $action, $mode, $at, $startDate, $time, $everyDays, $markNew, $employee)
    {
        if (!self::available()) {
            throw new ProductSchedulerError($module->l('The blog module (ST Blog) is not installed.', 'productscheduler'));
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            throw new ProductSchedulerError($module->l('Choose at least one article.', 'productscheduler'));
        }
        if (!ProductSchedulerRepository::isValidAction($action)) {
            throw new ProductSchedulerError($module->l('Unknown action.', 'productscheduler'));
        }
        $times = [];
        if ($mode === 'series') {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $startDate) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) $time)) {
                throw new ProductSchedulerError($module->l('Enter a start date and a time.', 'productscheduler'));
            }
            $every = max(1, min(30, (int) $everyDays));
            $first = ProductSchedulerService::validFutureDate($module, $startDate . ' ' . $time);
            foreach ($ids as $i => $id) {
                $times[$id] = date('Y-m-d H:i:00', strtotime($first . ' +' . ($i * $every) . ' days'));
            }
        } else {
            $date = ProductSchedulerService::validFutureDate($module, (string) $at);
            foreach ($ids as $id) {
                $times[$id] = $date;
            }
        }
        $res = ['created' => 0, 'skipped' => [], 'first' => min($times), 'last' => max($times)];
        $id_employee = Validate::isLoadedObject($employee) ? (int) $employee->id : 0;
        foreach ($times as $id => $when) {
            if (!self::exists($id)) {
                $res['skipped'][] = sprintf($module->l('Article %d not found.', 'productscheduler'), $id);
                continue;
            }
            if (ProductSchedulerRepository::pendingExists($id, 0, $action, $when, ProductSchedulerRepository::TYPE_BLOG)) {
                continue;
            }
            $res['created'] += (int) (bool) ProductSchedulerRepository::create($id, 0, $action, $when, $markNew && $action === ProductSchedulerRepository::ACTION_PUBLISH, $id_employee, ProductSchedulerRepository::TYPE_BLOG);
        }
        ProductSchedulerRepository::refreshNextDue();

        return $res;
    }
}
