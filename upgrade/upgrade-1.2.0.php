<?php
/**
 * 1.2.0: blog articles (ST Blog) can be scheduled too - column `type` in the schedule table.
 *
 * @author    Mohammad Babaei <https://adschi.com>
 * @copyright 2026 Mohammad Babaei - https://adschi.com
 * @license   MIT
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_2_0($module)
{
    // Existing schedules stay products (column default).
    return ProductSchedulerRepository::addTypeColumn() && $module->installConfiguration();
}
