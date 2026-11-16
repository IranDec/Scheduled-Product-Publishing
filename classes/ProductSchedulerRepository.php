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
 * Database access for the schedule table. One row = one action (publish / unpublish) for one
 * product at one time, for one shop (id_shop > 0) or for every shop of the product (id_shop = 0).
 * Dates are stored in the shop's time zone (PS_TIMEZONE), like every PrestaShop date.
 */
class ProductSchedulerRepository
{
    const TABLE = 'productscheduler';

    const ACTION_PUBLISH = 'publish';
    const ACTION_UNPUBLISH = 'unpublish';

    const STATUS_PENDING = 'pending';
    const STATUS_RUNNING = 'running';
    const STATUS_DONE = 'done';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    public static function table()
    {
        return _DB_PREFIX_ . self::TABLE;
    }

    public static function installTable()
    {
        return Db::getInstance()->execute('CREATE TABLE IF NOT EXISTS `' . self::table() . '` (
            `id_productscheduler` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_product` INT(11) UNSIGNED NOT NULL,
            `id_shop` INT(11) UNSIGNED NOT NULL DEFAULT 0,
            `action` VARCHAR(16) NOT NULL,
            `scheduled_at` DATETIME NOT NULL,
            `mark_new` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
            `status` VARCHAR(16) NOT NULL DEFAULT \'pending\',
            `attempts` TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,
            `message` VARCHAR(255) NOT NULL DEFAULT \'\',
            `id_employee` INT(11) UNSIGNED NOT NULL DEFAULT 0,
            `executed_at` DATETIME NULL DEFAULT NULL,
            `date_add` DATETIME NOT NULL,
            `date_upd` DATETIME NOT NULL,
            PRIMARY KEY (`id_productscheduler`),
            KEY `idx_due` (`status`, `scheduled_at`),
            KEY `idx_product` (`id_product`, `status`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
    }

    public static function dropTable()
    {
        return Db::getInstance()->execute('DROP TABLE IF EXISTS `' . self::table() . '`');
    }

    public static function isValidAction($action)
    {
        return in_array($action, [self::ACTION_PUBLISH, self::ACTION_UNPUBLISH], true);
    }

    // -----------------------------------------------------------------------
    // Write
    // -----------------------------------------------------------------------

    public static function create($id_product, $id_shop, $action, $scheduledAt, $markNew, $id_employee)
    {
        $now = date('Y-m-d H:i:s');
        $ok = Db::getInstance()->insert(self::TABLE, [
            'id_product' => (int) $id_product,
            'id_shop' => (int) $id_shop,
            'action' => pSQL($action),
            'scheduled_at' => pSQL($scheduledAt),
            'mark_new' => (int) (bool) $markNew,
            'status' => self::STATUS_PENDING,
            'attempts' => 0,
            'message' => '',
            'id_employee' => (int) $id_employee,
            'date_add' => $now,
            'date_upd' => $now,
        ]);

        return $ok ? (int) Db::getInstance()->Insert_ID() : 0;
    }

    /** True when the same pending action already exists (double click, repeated bulk import). */
    public static function pendingExists($id_product, $id_shop, $action, $scheduledAt)
    {
        return (bool) Db::getInstance()->getValue('SELECT 1 FROM `' . self::table() . '`
            WHERE `id_product` = ' . (int) $id_product . ' AND `id_shop` = ' . (int) $id_shop . '
              AND `action` = \'' . pSQL($action) . '\' AND `scheduled_at` = \'' . pSQL($scheduledAt) . '\'
              AND `status` = \'' . self::STATUS_PENDING . '\'', false);
    }

    /** Takes a pending row for execution; false when another process was faster. */
    public static function claim($id)
    {
        Db::getInstance()->execute('UPDATE `' . self::table() . '`
            SET `status` = \'' . self::STATUS_RUNNING . '\', `attempts` = `attempts` + 1, `date_upd` = \'' . pSQL(date('Y-m-d H:i:s')) . '\'
            WHERE `id_productscheduler` = ' . (int) $id . ' AND `status` = \'' . self::STATUS_PENDING . '\'');

        return Db::getInstance()->Affected_Rows() === 1;
    }

    public static function finish($id, $ok, $message)
    {
        $now = date('Y-m-d H:i:s');

        return Db::getInstance()->update(self::TABLE, [
            'status' => $ok ? self::STATUS_DONE : self::STATUS_FAILED,
            'message' => pSQL(Tools::substr((string) $message, 0, 255)),
            'executed_at' => $now,
            'date_upd' => $now,
        ], '`id_productscheduler` = ' . (int) $id . ' AND `status` = \'' . self::STATUS_RUNNING . '\'');
    }

    /**
     * Rows left in "running" by a process that died (time limit, server restart): retried up to
     * $maxAttempts times, then marked failed.
     */
    public static function recoverStale($minutes, $maxAttempts)
    {
        $limit = date('Y-m-d H:i:s', time() - 60 * (int) $minutes);
        $now = date('Y-m-d H:i:s');
        $db = Db::getInstance();
        $db->execute('UPDATE `' . self::table() . '` SET `status` = \'' . self::STATUS_FAILED . '\',
                `message` = \'Interrupted several times - not retried.\', `executed_at` = \'' . pSQL($now) . '\', `date_upd` = \'' . pSQL($now) . '\'
            WHERE `status` = \'' . self::STATUS_RUNNING . '\' AND `date_upd` < \'' . pSQL($limit) . '\' AND `attempts` >= ' . (int) $maxAttempts);
        $db->execute('UPDATE `' . self::table() . '` SET `status` = \'' . self::STATUS_PENDING . '\', `date_upd` = \'' . pSQL($now) . '\'
            WHERE `status` = \'' . self::STATUS_RUNNING . '\' AND `date_upd` < \'' . pSQL($limit) . '\'');
    }

    public static function cancel($id)
    {
        Db::getInstance()->execute('UPDATE `' . self::table() . '`
            SET `status` = \'' . self::STATUS_CANCELLED . '\', `date_upd` = \'' . pSQL(date('Y-m-d H:i:s')) . '\'
            WHERE `id_productscheduler` = ' . (int) $id . ' AND `status` = \'' . self::STATUS_PENDING . '\'');

        return Db::getInstance()->Affected_Rows() === 1;
    }

    public static function reschedule($id, $scheduledAt)
    {
        Db::getInstance()->execute('UPDATE `' . self::table() . '`
            SET `scheduled_at` = \'' . pSQL($scheduledAt) . '\', `date_upd` = \'' . pSQL(date('Y-m-d H:i:s')) . '\'
            WHERE `id_productscheduler` = ' . (int) $id . ' AND `status` = \'' . self::STATUS_PENDING . '\'');

        return Db::getInstance()->Affected_Rows() === 1;
    }

    /** Only finished entries (done / failed / cancelled) can be deleted; pending ones are cancelled. */
    public static function delete($id)
    {
        Db::getInstance()->execute('DELETE FROM `' . self::table() . '`
            WHERE `id_productscheduler` = ' . (int) $id . '
              AND `status` IN (\'' . self::STATUS_DONE . '\', \'' . self::STATUS_FAILED . '\', \'' . self::STATUS_CANCELLED . '\')');

        return Db::getInstance()->Affected_Rows() === 1;
    }

    public static function deleteForProduct($id_product)
    {
        return Db::getInstance()->delete(self::TABLE, '`id_product` = ' . (int) $id_product);
    }

    public static function purgeHistory($days)
    {
        if ((int) $days <= 0) {
            return true;
        }

        return Db::getInstance()->delete(self::TABLE, '`status` IN (\'' . self::STATUS_DONE . '\', \'' . self::STATUS_FAILED . '\', \'' . self::STATUS_CANCELLED . '\')
            AND `date_upd` < \'' . pSQL(date('Y-m-d H:i:s', time() - 86400 * (int) $days)) . '\'');
    }

    /** Keeps the earliest pending time in the configuration, so a shop visit can check it without a query. */
    public static function refreshNextDue()
    {
        $next = Db::getInstance()->getValue('SELECT MIN(`scheduled_at`) FROM `' . self::table() . '`
            WHERE `status` = \'' . self::STATUS_PENDING . '\'', false);

        return Configuration::updateGlobalValue('PRODUCTSCHEDULER_NEXT_DUE', $next ? (string) $next : '');
    }

    // -----------------------------------------------------------------------
    // Read
    // -----------------------------------------------------------------------

    public static function get($id)
    {
        $row = Db::getInstance()->getRow('SELECT * FROM `' . self::table() . '` WHERE `id_productscheduler` = ' . (int) $id, false);

        return $row ?: null;
    }

    public static function due($now, $limit)
    {
        return Db::getInstance()->executeS('SELECT * FROM `' . self::table() . '`
            WHERE `status` = \'' . self::STATUS_PENDING . '\' AND `scheduled_at` <= \'' . pSQL($now) . '\'
            ORDER BY `scheduled_at` ASC, `id_productscheduler` ASC
            LIMIT ' . max(1, (int) $limit), true, false) ?: [];
    }

    /** Shops the product is assigned to. */
    public static function productShopIds($id_product)
    {
        $ids = [];
        foreach (Db::getInstance()->executeS('SELECT `id_shop` FROM `' . _DB_PREFIX_ . 'product_shop`
            WHERE `id_product` = ' . (int) $id_product . ' ORDER BY `id_shop`') ?: [] as $row) {
            $ids[] = (int) $row['id_shop'];
        }

        return $ids;
    }

    /** [id_shop => ['name' => .., 'active' => bool]] for the product's shops. */
    public static function productShopStates($id_product)
    {
        $out = [];
        foreach (Db::getInstance()->executeS('SELECT ps.`id_shop`, ps.`active`, s.`name`
            FROM `' . _DB_PREFIX_ . 'product_shop` ps
            LEFT JOIN `' . _DB_PREFIX_ . 'shop` s ON s.`id_shop` = ps.`id_shop`
            WHERE ps.`id_product` = ' . (int) $id_product . ' ORDER BY ps.`id_shop`', true, false) ?: [] as $row) {
            $out[(int) $row['id_shop']] = [
                'name' => (string) $row['name'],
                'active' => (bool) $row['active'],
            ];
        }

        return $out;
    }

    private static function shopFilterSql($shopIds = null, $alias = 's')
    {
        if ($shopIds === null) {
            return '';
        }
        $shopIds = array_map('intval', $shopIds);
        $shopIds[] = 0;

        return ' AND ' . $alias . '.`id_shop` IN (' . implode(',', array_unique($shopIds)) . ')';
    }

    private static function selectSql($id_lang)
    {
        return 'SELECT s.*, pl.`name` AS product_name, p.`reference`, p.`id_shop_default`,
                    e.`firstname`, e.`lastname`, sh.`name` AS shop_name
            FROM `' . self::table() . '` s
            LEFT JOIN `' . _DB_PREFIX_ . 'product` p ON p.`id_product` = s.`id_product`
            LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON pl.`id_product` = s.`id_product`
                AND pl.`id_lang` = ' . (int) $id_lang . '
                AND pl.`id_shop` = IF(s.`id_shop` > 0, s.`id_shop`, p.`id_shop_default`)
            LEFT JOIN `' . _DB_PREFIX_ . 'employee` e ON e.`id_employee` = s.`id_employee`
            LEFT JOIN `' . _DB_PREFIX_ . 'shop` sh ON sh.`id_shop` = s.`id_shop`';
    }

    public static function forProduct($id_product, $shopIds = null, $limit = 60)
    {
        $id_lang = (int) Context::getContext()->language->id;

        return Db::getInstance()->executeS(self::selectSql($id_lang) . '
            WHERE s.`id_product` = ' . (int) $id_product . self::shopFilterSql($shopIds) . '
            ORDER BY (s.`status` IN (\'' . self::STATUS_PENDING . '\', \'' . self::STATUS_RUNNING . '\')) DESC,
                     s.`scheduled_at` DESC, s.`id_productscheduler` DESC
            LIMIT ' . max(1, (int) $limit), true, false) ?: [];
    }

    /**
     * Filtered, paginated list for the list page.
     *
     * @param array $f status, action, q (name / reference / ID)
     *
     * @return array [rows, total]
     */
    public static function getList(array $f, $shopIds, $page, $perPage)
    {
        $id_lang = (int) Context::getContext()->language->id;
        $where = ' WHERE 1' . self::shopFilterSql($shopIds);

        $status = isset($f['status']) ? (string) $f['status'] : '';
        if ($status === 'upcoming') {
            $where .= ' AND s.`status` IN (\'' . self::STATUS_PENDING . '\', \'' . self::STATUS_RUNNING . '\')';
        } elseif (in_array($status, [self::STATUS_DONE, self::STATUS_FAILED, self::STATUS_CANCELLED], true)) {
            $where .= ' AND s.`status` = \'' . pSQL($status) . '\'';
        }
        if (isset($f['action']) && self::isValidAction($f['action'])) {
            $where .= ' AND s.`action` = \'' . pSQL($f['action']) . '\'';
        }
        $q = isset($f['q']) ? trim((string) $f['q']) : '';
        if ($q !== '') {
            $like = '\'%' . pSQL(addcslashes($q, '%_\\')) . '%\'';
            $where .= ' AND (pl.`name` LIKE ' . $like . ' OR p.`reference` LIKE ' . $like
                . (ctype_digit($q) ? ' OR s.`id_product` = ' . (int) $q : '') . ')';
        }

        $order = $status === 'upcoming'
            ? ' ORDER BY s.`scheduled_at` ASC, s.`id_productscheduler` ASC'
            : ' ORDER BY (s.`status` IN (\'' . self::STATUS_PENDING . '\', \'' . self::STATUS_RUNNING . '\')) DESC,
                        IF(s.`status` IN (\'' . self::STATUS_PENDING . '\', \'' . self::STATUS_RUNNING . '\'), s.`scheduled_at`, NULL) ASC,
                        s.`scheduled_at` DESC, s.`id_productscheduler` DESC';

        $db = Db::getInstance();
        $total = (int) $db->getValue('SELECT COUNT(*) FROM `' . self::table() . '` s
            LEFT JOIN `' . _DB_PREFIX_ . 'product` p ON p.`id_product` = s.`id_product`
            LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON pl.`id_product` = s.`id_product`
                AND pl.`id_lang` = ' . $id_lang . '
                AND pl.`id_shop` = IF(s.`id_shop` > 0, s.`id_shop`, p.`id_shop_default`)' . $where, false);

        $rows = $db->executeS(self::selectSql($id_lang) . $where . $order
            . ' LIMIT ' . max(0, ((int) $page - 1) * (int) $perPage) . ', ' . max(1, (int) $perPage), true, false) ?: [];

        return [$rows, $total];
    }

    /** [status => count] for the visible shops; "upcoming" = pending + running. */
    public static function countByStatus($shopIds = null)
    {
        $counts = [
            self::STATUS_PENDING => 0,
            self::STATUS_RUNNING => 0,
            self::STATUS_DONE => 0,
            self::STATUS_FAILED => 0,
            self::STATUS_CANCELLED => 0,
        ];
        foreach (Db::getInstance()->executeS('SELECT s.`status`, COUNT(*) AS n FROM `' . self::table() . '` s
            WHERE 1' . self::shopFilterSql($shopIds) . ' GROUP BY s.`status`', true, false) ?: [] as $row) {
            $counts[$row['status']] = (int) $row['n'];
        }
        $counts['upcoming'] = $counts[self::STATUS_PENDING] + $counts[self::STATUS_RUNNING];

        return $counts;
    }

    /** Product search for the list page's product picker. */
    public static function searchProducts($q, $id_lang, $id_shop, $limit = 15)
    {
        $q = trim((string) $q);
        if ($q === '') {
            return [];
        }
        $like = '\'%' . pSQL(addcslashes($q, '%_\\')) . '%\'';

        return Db::getInstance()->executeS('SELECT p.`id_product`, pl.`name`, p.`reference`, ps.`active`
            FROM `' . _DB_PREFIX_ . 'product` p
            INNER JOIN `' . _DB_PREFIX_ . 'product_shop` ps ON ps.`id_product` = p.`id_product` AND ps.`id_shop` = ' . (int) $id_shop . '
            LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON pl.`id_product` = p.`id_product`
                AND pl.`id_lang` = ' . (int) $id_lang . ' AND pl.`id_shop` = ' . (int) $id_shop . '
            WHERE pl.`name` LIKE ' . $like . ' OR p.`reference` LIKE ' . $like
                . (ctype_digit($q) ? ' OR p.`id_product` = ' . (int) $q : '') . '
            ORDER BY (p.`id_product` = ' . (int) $q . ') DESC, pl.`name` ASC
            LIMIT ' . max(1, (int) $limit), true, false) ?: [];
    }
}
