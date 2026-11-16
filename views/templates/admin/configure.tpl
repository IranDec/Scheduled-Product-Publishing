{**
 * Module settings: runner status and cron job set-up (the settings form follows below it).
 * @author Mohammad Babaei | https://adschi.com
 *}
<link rel="stylesheet" href="{$pps_css_url|escape:'html':'UTF-8'}">
<div class="pps pps-settings" data-pps-config="{$pps_config_json|escape:'html':'UTF-8'}">

    <div class="panel pps-panel">
        <div class="panel-heading"><i class="icon-time"></i> {l s='Status' mod='productscheduler'}</div>
        <div class="pps-kpis">
            <a class="pps-kpi" href="{$pps_manage_url|escape:'html':'UTF-8'}">
                <span class="pps-kpi-value">{$pps_counts.upcoming|intval}</span>
                <span class="pps-kpi-label">{l s='Waiting' mod='productscheduler'}</span>
            </a>
            <div class="pps-kpi">
                <span class="pps-kpi-value pps-kpi-small">{if $pps_runner.next_due}{$pps_runner.next_due|escape:'html':'UTF-8'}{else}&mdash;{/if}</span>
                <span class="pps-kpi-label">{l s='Next change' mod='productscheduler'}</span>
            </div>
            <div class="pps-kpi">
                <span class="pps-kpi-value pps-kpi-small">{if $pps_runner.last_run}{$pps_runner.last_run|escape:'html':'UTF-8'}{else}&mdash;{/if}</span>
                <span class="pps-kpi-label">{l s='Last run' mod='productscheduler'}{if $pps_runner.last_source} ({$pps_runner.last_source|escape:'html':'UTF-8'}){/if}</span>
            </div>
            <div class="pps-kpi{if $pps_counts.failed > 0} pps-kpi-bad{/if}">
                <span class="pps-kpi-value">{$pps_counts.failed|intval}</span>
                <span class="pps-kpi-label">{l s='Failed' mod='productscheduler'}</span>
            </div>
        </div>
        <div class="alert alert-{$pps_runner.level|escape:'html':'UTF-8'} pps-runner-alert">{$pps_runner.text|escape:'html':'UTF-8'}</div>
        <a class="btn btn-primary" href="{$pps_manage_url|escape:'html':'UTF-8'}">
            <i class="icon-list"></i> {l s='Open the schedule list' mod='productscheduler'}
        </a>
    </div>

    <div class="panel pps-panel">
        <div class="panel-heading"><i class="icon-terminal"></i> {l s='Cron job (recommended)' mod='productscheduler'}</div>
        <p class="pps-lead">
            {l s='A cron job calls the module every minute, so products go online and offline at the exact time. Add one of these lines to the crontab of your server (or call the URL from your hosting panel cron or an online cron service).' mod='productscheduler'}
        </p>

        <label class="pps-label" for="pps-cron-url">{l s='Cron URL' mod='productscheduler'}</label>
        <div class="input-group pps-copy-group">
            <input type="text" class="form-control pps-mono" id="pps-cron-url" value="{$pps_cron_url|escape:'html':'UTF-8'}" readonly>
            <span class="input-group-btn"><button type="button" class="btn btn-default" data-pps-copy="#pps-cron-url">{l s='Copy' mod='productscheduler'}</button></span>
        </div>

        <label class="pps-label" for="pps-cron-line">{l s='Crontab line (every minute, via URL)' mod='productscheduler'}</label>
        <div class="input-group pps-copy-group">
            <input type="text" class="form-control pps-mono" id="pps-cron-line" value="{$pps_cron_line|escape:'html':'UTF-8'}" readonly>
            <span class="input-group-btn"><button type="button" class="btn btn-default" data-pps-copy="#pps-cron-line">{l s='Copy' mod='productscheduler'}</button></span>
        </div>

        <label class="pps-label" for="pps-cli-line">{l s='Or: crontab line for the command line (run as the shop user)' mod='productscheduler'}</label>
        <div class="input-group pps-copy-group">
            <input type="text" class="form-control pps-mono" id="pps-cli-line" value="{$pps_cli_line|escape:'html':'UTF-8'}" readonly>
            <span class="input-group-btn"><button type="button" class="btn btn-default" data-pps-copy="#pps-cli-line">{l s='Copy' mod='productscheduler'}</button></span>
        </div>

        <form method="post" action="{$pps_settings_url|escape:'html':'UTF-8'}" class="pps-token-form">
            <input type="hidden" name="pps_form_key" value="{$pps_form_key|escape:'html':'UTF-8'}">
            <span class="help-block">{l s='Keep the URL secret. If it was shared by mistake, create a new one - the old one stops working at once.' mod='productscheduler'}</span>
            <button type="submit" name="submitProductSchedulerToken" value="1" class="btn btn-default"
                    data-pps-confirm="{l s='Create a new cron URL? You must update your cron job afterwards.' mod='productscheduler'}">
                <i class="icon-refresh"></i> {l s='Create a new cron URL' mod='productscheduler'}
            </button>
        </form>
    </div>
</div>
<script src="{$pps_js_url|escape:'html':'UTF-8'}"></script>
