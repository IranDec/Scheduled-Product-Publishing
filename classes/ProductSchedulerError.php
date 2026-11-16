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
 * A problem the employee caused and can fix (invalid date, no shop chosen, ...). Its message is
 * already translated and safe to show; any other exception is logged and shown as a generic error.
 */
class ProductSchedulerError extends Exception
{
}
