{**
 * Module settings: runner status, cron schedule builder and test e-mail (the settings form follows).
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
        <div class="pps-button-row">
            <a class="btn btn-primary" href="{$pps_manage_url|escape:'html':'UTF-8'}">
                <i class="icon-list"></i> {l s='Open the schedule list' mod='productscheduler'}
            </a>
            <form method="post" action="{$pps_settings_url|escape:'html':'UTF-8'}">
                <input type="hidden" name="pps_form_key" value="{$pps_form_key|escape:'html':'UTF-8'}">
                <button type="submit" name="submitProductSchedulerTestMail" value="1" class="btn btn-default"
                        {if !$pps_report_to}disabled{/if}
                        data-pps-confirm="{l s='Send an example report to %s?' sprintf=[$pps_report_to] mod='productscheduler'}">
                    <i class="icon-envelope"></i> {l s='Send a test report e-mail' mod='productscheduler'}
                </button>
            </form>
        </div>
    </div>

    <div class="panel pps-panel">
        <div class="panel-heading"><i class="icon-terminal"></i> {l s='Cron job (recommended)' mod='productscheduler'}</div>
        <p class="pps-lead">
            {l s='The cron job starts the module regularly; a product changes at the first run after its scheduled time. Choose how often and how it is called - the line to copy updates as you choose. Then put it into the crontab of your server or the cron jobs of your hosting panel.' mod='productscheduler'}
        </p>

        <form method="post" action="{$pps_settings_url|escape:'html':'UTF-8'}" data-pps-cron
              data-pps-cron-commands="{$pps_cron_commands_json|escape:'html':'UTF-8'}">
            <input type="hidden" name="pps_form_key" value="{$pps_form_key|escape:'html':'UTF-8'}">
            <div class="pps-grid">
                <div class="pps-field">
                    <label for="pps-cron-mode">{l s='How often' mod='productscheduler'}</label>
                    <select id="pps-cron-mode" name="PRODUCTSCHEDULER_CRON_MODE" class="form-control" data-pps-cron-mode>
                        <option value="1"{if $pps_cron_mode == '1'} selected{/if}>{l s='Every minute (recommended - exact times)' mod='productscheduler'}</option>
                        <option value="5"{if $pps_cron_mode == '5'} selected{/if}>{l s='Every 5 minutes' mod='productscheduler'}</option>
                        <option value="10"{if $pps_cron_mode == '10'} selected{/if}>{l s='Every 10 minutes' mod='productscheduler'}</option>
                        <option value="15"{if $pps_cron_mode == '15'} selected{/if}>{l s='Every 15 minutes' mod='productscheduler'}</option>
                        <option value="30"{if $pps_cron_mode == '30'} selected{/if}>{l s='Every 30 minutes' mod='productscheduler'}</option>
                        <option value="60"{if $pps_cron_mode == '60'} selected{/if}>{l s='Every hour' mod='productscheduler'}</option>
                        <option value="daily"{if $pps_cron_mode == 'daily'} selected{/if}>{l s='Once a day at a fixed time' mod='productscheduler'}</option>
                        <option value="custom"{if $pps_cron_mode == 'custom'} selected{/if}>{l s='Custom (cron expression)' mod='productscheduler'}</option>
                    </select>
                </div>
                <div class="pps-field" data-pps-cron-for="60">
                    <label for="pps-cron-minute">{l s='At minute' mod='productscheduler'}</label>
                    <input type="number" id="pps-cron-minute" name="PRODUCTSCHEDULER_CRON_MINUTE" class="form-control" min="0" max="59"
                           value="{$pps_cron_minute|intval}" data-pps-cron-minute>
                </div>
                <div class="pps-field" data-pps-cron-for="daily">
                    <label for="pps-cron-time">{l s='At' mod='productscheduler'}</label>
                    <input type="time" id="pps-cron-time" name="PRODUCTSCHEDULER_CRON_TIME" class="form-control"
                           value="{$pps_cron_time|escape:'html':'UTF-8'}" data-pps-cron-time>
                </div>
                <div class="pps-field" data-pps-cron-for="custom">
                    <label for="pps-cron-custom">{l s='Cron expression' mod='productscheduler'}</label>
                    <input type="text" id="pps-cron-custom" name="PRODUCTSCHEDULER_CRON_CUSTOM" class="form-control pps-mono"
                           value="{$pps_cron_custom|escape:'html':'UTF-8'}" placeholder="*/20 6-22 * * *" data-pps-cron-custom>
                    <span class="help-block">{l s='minute hour day month weekday' mod='productscheduler'}</span>
                </div>
                <div class="pps-field">
                    <label for="pps-cron-method">{l s='How it is called' mod='productscheduler'}</label>
                    <select id="pps-cron-method" name="PRODUCTSCHEDULER_CRON_METHOD" class="form-control" data-pps-cron-method>
                        <option value="curl"{if $pps_cron_method == 'curl'} selected{/if}>{l s='URL with curl (most servers)' mod='productscheduler'}</option>
                        <option value="wget"{if $pps_cron_method == 'wget'} selected{/if}>{l s='URL with wget' mod='productscheduler'}</option>
                        <option value="cli"{if $pps_cron_method == 'cli'} selected{/if}>{l s='Command line (PHP, no URL - run as the shop user)' mod='productscheduler'}</option>
                    </select>
                </div>
            </div>

            <div class="pps-cron-note" data-pps-cron-note></div>

            <label class="pps-label" for="pps-cron-line">
                {l s='Your crontab line' mod='productscheduler'}
                <code class="pps-cron-expr" data-pps-cron-expr></code>
            </label>
            <div class="input-group pps-copy-group">
                <input type="text" class="form-control pps-mono" id="pps-cron-line" value="{$pps_cron_line|escape:'html':'UTF-8'}" readonly data-pps-cron-line>
                <span class="input-group-btn"><button type="button" class="btn btn-default" data-pps-copy="#pps-cron-line">{l s='Copy' mod='productscheduler'}</button></span>
            </div>

            <div class="pps-form-foot">
                <button type="submit" name="submitProductSchedulerCron" value="1" class="btn btn-primary">{l s='Save my cron schedule' mod='productscheduler'}</button>
                <span class="help-block pps-inline-help">{l s='Saving remembers your choice for this page and for the status check. The crontab itself is on your server: change it there (or ask your hosting).' mod='productscheduler'}</span>
            </div>
        </form>

        <hr>
        <label class="pps-label" for="pps-cron-url">{l s='Cron URL (for hosting panels and online cron services)' mod='productscheduler'}</label>
        <div class="input-group pps-copy-group">
            <input type="text" class="form-control pps-mono" id="pps-cron-url" value="{$pps_cron_url|escape:'html':'UTF-8'}" readonly>
            <span class="input-group-btn"><button type="button" class="btn btn-default" data-pps-copy="#pps-cron-url">{l s='Copy' mod='productscheduler'}</button></span>
        </div>

        <form method="post" action="{$pps_settings_url|escape:'html':'UTF-8'}" class="pps-token-form">
            <input type="hidden" name="pps_form_key" value="{$pps_form_key|escape:'html':'UTF-8'}">
            <span class="help-block">{l s='Keep the URL secret. If it was shared by mistake, create a new one - the old one stops working at once.' mod='productscheduler'}</span>
            <button type="submit" name="submitProductSchedulerToken" value="1" class="btn btn-default"
                    data-pps-confirm="{l s='Create a new cron URL? You must update your cron job afterwards.' mod='productscheduler'}"
                    data-pps-confirm-danger="1">
                <i class="icon-refresh"></i> {l s='Create a new cron URL' mod='productscheduler'}
            </button>
        </form>
    </div>
</div>
<script src="{$pps_js_url|escape:'html':'UTF-8'}"></script>
