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
 * Validates and creates schedules for the product tab and the list page: dates, shops the
 * employee may manage, duplicates, and the optional "unpublish again" end date.
 */
class ProductSchedulerService
{
    const MAX_PRODUCTS = 500;
    const MAX_YEARS_AHEAD = 5;

    /** "2026-11-20T08:00" / "2026-11-20 08:00[:ss]" -> "2026-11-20 08:00:00" (shop time zone), or false. */
    public static function parseDateTime($value)
    {
        $value = trim((string) $value);
        foreach (['Y-m-d\TH:i', 'Y-m-d H:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i:s'] as $format) {
            $date = DateTime::createFromFormat('!' . $format, $value);
            if ($date && $date->format($format) === $value) {
                return $date->format('Y-m-d H:i:00');
            }
        }

        return false;
    }

    /** Shops the employee may manage (all shops for super admins or without multistore). */
    public static function employeeShopIds($employee)
    {
        if (!Shop::isFeatureActive() || (Validate::isLoadedObject($employee) && $employee->isSuperAdmin())) {
            return array_map('intval', Shop::getShops(false, null, true));
        }
        if (!Validate::isLoadedObject($employee)) {
            return [];
        }

        return array_map('intval', $employee->getAssociatedShops());
    }

    /**
     * Target shops for one product: [0] = every shop of the product (also shops it is added to
     * later), otherwise the chosen shops the product is in and the employee may manage.
     *
     * @param string|int[] $selection 'all' or shop IDs
     */
    public static function resolveShops($id_product, $selection, $employee)
    {
        $productShops = ProductSchedulerRepository::productShopIds($id_product);
        if (!$productShops) {
            return [];
        }
        if (!Shop::isFeatureActive()) {
            return [0];
        }
        $allowed = self::employeeShopIds($employee);
        if ($selection === 'all') {
            if (!array_diff($productShops, $allowed)) {
                return [0];
            }
            $selection = $productShops;
        }

        return array_values(array_intersect(array_map('intval', (array) $selection), $productShops, $allowed));
    }

    /**
     * @param int[] $productIds
     * @param string $at "Y-m-d H:i" style date of the action
     * @param string $endAt optional date of the opposite action ('' = none)
     * @param string|int[] $shops 'all' or shop IDs
     *
     * @return array ['created' => int, 'skipped' => string[]]
     *
     * @throws ProductSchedulerError
     */
    public static function addSchedules(Module $module, array $productIds, $action, $at, $endAt, $shops, $markNew, $employee)
    {
        if (!ProductSchedulerRepository::isValidAction($action)) {
            throw new ProductSchedulerError($module->l('Choose publish or unpublish.', 'productscheduler'));
        }
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        if (!$productIds) {
            throw new ProductSchedulerError($module->l('Choose at least one product.', 'productscheduler'));
        }
        if (count($productIds) > self::MAX_PRODUCTS) {
            throw new ProductSchedulerError(sprintf($module->l('At most %d products at once.', 'productscheduler'), self::MAX_PRODUCTS));
        }

        $start = self::validFutureDate($module, $at);
        $end = '';
        if (trim((string) $endAt) !== '') {
            $end = self::validFutureDate($module, $endAt);
            if ($end <= $start) {
                throw new ProductSchedulerError($module->l('The second date must be after the first one.', 'productscheduler'));
            }
        }
        if ($shops !== 'all' && !array_filter(array_map('intval', (array) $shops))) {
            throw new ProductSchedulerError($module->l('Choose at least one shop.', 'productscheduler'));
        }

        $opposite = $action === ProductSchedulerRepository::ACTION_PUBLISH
            ? ProductSchedulerRepository::ACTION_UNPUBLISH
            : ProductSchedulerRepository::ACTION_PUBLISH;
        $markNew = $markNew && $action === ProductSchedulerRepository::ACTION_PUBLISH;
        $id_employee = Validate::isLoadedObject($employee) ? (int) $employee->id : 0;

        $created = 0;
        $skipped = [];
        foreach ($productIds as $id_product) {
            $targets = self::resolveShops($id_product, $shops, $employee);
            if (!$targets) {
                $skipped[] = sprintf($module->l('Product %d: not found or not in the chosen shops.', 'productscheduler'), $id_product);
                continue;
            }
            foreach ($targets as $id_shop) {
                $created += self::createOnce($id_product, $id_shop, $action, $start, $markNew, $id_employee);
                if ($end !== '') {
                    $created += self::createOnce($id_product, $id_shop, $opposite, $end, false, $id_employee);
                }
            }
        }
        ProductSchedulerRepository::refreshNextDue();

        return ['created' => $created, 'skipped' => $skipped];
    }

    /** @throws ProductSchedulerError */
    public static function validFutureDate(Module $module, $value)
    {
        $date = self::parseDateTime($value);
        if ($date === false) {
            throw new ProductSchedulerError($module->l('Enter a valid date and time.', 'productscheduler'));
        }
        // The current minute is still accepted: it runs with the next check.
        if (strtotime($date) < strtotime(date('Y-m-d H:i:00'))) {
            throw new ProductSchedulerError($module->l('The date is in the past. Choose a time from now on.', 'productscheduler'));
        }
        if (strtotime($date) > strtotime('+' . self::MAX_YEARS_AHEAD . ' years')) {
            throw new ProductSchedulerError(sprintf($module->l('Choose a date within the next %d years.', 'productscheduler'), self::MAX_YEARS_AHEAD));
        }

        return $date;
    }

    private static function createOnce($id_product, $id_shop, $action, $date, $markNew, $id_employee)
    {
        if (ProductSchedulerRepository::pendingExists($id_product, $id_shop, $action, $date)) {
            return 0;
        }

        return ProductSchedulerRepository::create($id_product, $id_shop, $action, $date, $markNew, $id_employee) ? 1 : 0;
    }

    /**
     * May the employee change this schedule? Rows for one shop need that shop; rows for every
     * shop ("all shops of the product") need every shop of the product.
     */
    public static function canManageRow(array $row, $employee)
    {
        $allowed = self::employeeShopIds($employee);
        if ((int) $row['id_shop'] > 0) {
            return in_array((int) $row['id_shop'], $allowed, true);
        }

        return !array_diff(ProductSchedulerRepository::productShopIds((int) $row['id_product']), $allowed);
    }
}
