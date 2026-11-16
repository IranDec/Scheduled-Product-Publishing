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
 * Cron URL: index.php?fc=module&module=productscheduler&controller=cron&token=...
 * Runs due schedules and answers with a JSON summary. Works in maintenance mode (it answers
 * before the shop's page logic runs). A wrong token gets 403 without any work.
 */
class ProductSchedulerCronModuleFrontController extends ModuleFrontController
{
    public $auth = false;
    public $ajax = true;

    public function init()
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('X-Robots-Tag: noindex, nofollow');

        if (!$this->module || !$this->module->active) {
            $this->reply(503, ['ok' => false, 'error' => 'Module disabled.']);
        }
        if (!ProductScheduler::isValidCronToken((string) Tools::getValue('token'))) {
            $this->reply(403, ['ok' => false, 'error' => 'Invalid token.']);
        }

        // Some modules read the currency in their product hooks; the page logic that sets it does not run here.
        if (!Validate::isLoadedObject($this->context->currency)) {
            $this->context->currency = Currency::getDefaultCurrency();
        }

        ignore_user_abort(true);
        @set_time_limit(300);
        $summary = ProductSchedulerRunner::runDue('cron');
        unset($summary['items']);
        $this->reply(200, $summary);
    }

    private function reply($code, array $data)
    {
        if (function_exists('http_response_code')) {
            http_response_code((int) $code);
        }
        echo json_encode($data);
        exit;
    }
}
