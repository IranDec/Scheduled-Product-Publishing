<?php
/**
 * Command-line runner (no web request, no token needed):
 *
 *   php /path/to/shop/modules/productscheduler/cron_cli.php
 *
 * Run it as the shop's system user (the one that owns the files). Exit code 0 = ok.
 *
 * @author    Mohammad Babaei <https://adschi.com>
 * @copyright 2026 Mohammad Babaei - https://adschi.com
 * @license   MIT
 */

if (PHP_SAPI !== 'cli') {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

if (!isset($_SERVER['REQUEST_METHOD'])) {
    $_SERVER['REQUEST_METHOD'] = 'GET';
}
if (!isset($_SERVER['REMOTE_ADDR'])) {
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
}

require_once dirname(__FILE__) . '/../../config/config.inc.php';

$module = Module::getInstanceByName('productscheduler');
if (!$module || !$module->active) {
    fwrite(STDERR, "productscheduler is not installed or disabled.\n");
    exit(1);
}

$context = Context::getContext();
if (!Validate::isLoadedObject($context->currency)) {
    $context->currency = Currency::getDefaultCurrency();
}

$summary = ProductSchedulerRunner::runDue('cli');
echo json_encode($summary) . "\n";
exit($summary['ok'] ? 0 : 2);
