<?php
/**
 * 1.1.0: cron schedule settings (frequency, time, expression, call method).
 *
 * @author    Mohammad Babaei <https://adschi.com>
 * @copyright 2026 Mohammad Babaei - https://adschi.com
 * @license   MIT
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_1_0($module)
{
    // Adds only the settings that do not exist yet; existing values and schedules stay as they are.
    return $module->installConfiguration();
}
