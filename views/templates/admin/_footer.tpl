{**
 * Module footer (settings page, list page, product tab).
 * @author Mohammad Babaei | https://adschi.com
 *}
{if isset($pps_compact) && $pps_compact}
<div class="pps pps-footer pps-footer-compact">
    <a class="pps-footer-mini" href="{$pps_footer_url|escape:'html':'UTF-8'}" target="_blank" rel="noopener noreferrer">
        <img src="{$pps_footer_logo|escape:'html':'UTF-8'}" alt="{$pps_footer_site|escape:'html':'UTF-8'}" width="44" height="25">
        <span>{$pps_footer_name|escape:'html':'UTF-8'} <span class="pps-footer-ver">v{$pps_footer_version|escape:'html':'UTF-8'}</span></span>
        <span class="pps-footer-sep">&middot;</span>
        <span>{$pps_footer_author|escape:'html':'UTF-8'} &mdash; {$pps_footer_site|escape:'html':'UTF-8'}</span>
    </a>
</div>
{else}
<div class="pps pps-footer">
    <div class="pps-footer-card">
        <a class="pps-footer-logo" href="{$pps_footer_url|escape:'html':'UTF-8'}" target="_blank" rel="noopener noreferrer">
            <img src="{$pps_footer_logo|escape:'html':'UTF-8'}" alt="{$pps_footer_site|escape:'html':'UTF-8'}">
        </a>
        <div class="pps-footer-title">{$pps_footer_name|escape:'html':'UTF-8'}</div>
        <div class="pps-footer-badges">
            <span class="pps-footer-ver">v{$pps_footer_version|escape:'html':'UTF-8'}</span>
            <span class="pps-footer-pill">PrestaShop 1.7 &ndash; 9</span>
            <span class="pps-footer-pill">{l s='Multistore ready' mod='productscheduler'}</span>
        </div>
        <div class="pps-footer-by">
            {l s='Developed by' mod='productscheduler'}
            <a href="{$pps_footer_url|escape:'html':'UTF-8'}" target="_blank" rel="noopener noreferrer">{$pps_footer_author|escape:'html':'UTF-8'}</a>
        </div>
        <a class="pps-footer-site" href="{$pps_footer_url|escape:'html':'UTF-8'}" target="_blank" rel="noopener noreferrer">
            {$pps_footer_site|escape:'html':'UTF-8'} <span aria-hidden="true">&#8599;</span>
        </a>
        <div class="pps-footer-copy">&copy; {$pps_footer_year|intval} {$pps_footer_site|escape:'html':'UTF-8'}</div>
    </div>
</div>
{/if}
