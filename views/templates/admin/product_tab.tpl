{**
 * Product editor > Modules tab: schedule publish / unpublish for this product (PS 1.7 / 8 / 9).
 * The product page is one big form: the fields here have no "name", so they are never sent with
 * the product; everything is saved through the module's own JSON endpoint.
 * @author Mohammad Babaei | https://adschi.com
 *}
<link rel="stylesheet" href="{$pps_css_url|escape:'html':'UTF-8'}">
<div class="pps pps-tab" data-pps-config="{$pps_config_json|escape:'html':'UTF-8'}">
    <div class="pps-card">
        <div class="pps-card-head">
            <div class="pps-title">
                <span class="pps-title-icon" aria-hidden="true">&#9201;</span>
                {l s='Scheduled publishing' mod='productscheduler'}
            </div>
            <div class="pps-state" data-pps-state></div>
        </div>

        {if !$pps_id_product}
            <div class="alert alert-info">{l s='Save the product first, then you can schedule it.' mod='productscheduler'}</div>
        {elseif !$pps_can_edit}
            <div class="alert alert-warning">{l s='You do not have permission to change schedules.' mod='productscheduler'}</div>
        {else}
            <div class="pps-form">
                <div class="pps-grid">
                    <div class="pps-field">
                        <label for="pps-action">{l s='Action' mod='productscheduler'}</label>
                        <select id="pps-action" class="form-control">
                            <option value="publish">{l s='Publish (set online)' mod='productscheduler'}</option>
                            <option value="unpublish">{l s='Unpublish (set offline)' mod='productscheduler'}</option>
                        </select>
                    </div>
                    <div class="pps-field">
                        <label for="pps-at">{l s='Date and time' mod='productscheduler'}</label>
                        <input type="datetime-local" id="pps-at" class="form-control" step="60">
                        <div class="pps-quick">
                            <button type="button" class="pps-chip" data-pps-quick="1h">+1 h</button>
                            <button type="button" class="pps-chip" data-pps-quick="tomorrow">{l s='Tomorrow 08:00' mod='productscheduler'}</button>
                            <button type="button" class="pps-chip" data-pps-quick="monday">{l s='Monday 08:00' mod='productscheduler'}</button>
                        </div>
                    </div>
                    <div class="pps-field">
                        <label class="pps-check">
                            <input type="checkbox" id="pps-end-on">
                            <span data-pps-end-label
                                  data-publish="{l s='Unpublish again at' mod='productscheduler'}"
                                  data-unpublish="{l s='Publish again at' mod='productscheduler'}">{l s='Unpublish again at' mod='productscheduler'}</span>
                            <span class="pps-optional">({l s='optional' mod='productscheduler'})</span>
                        </label>
                        <input type="datetime-local" id="pps-end" class="form-control" step="60" disabled>
                    </div>
                    {if $pps_multistore && $pps_shops|@count > 0}
                        <div class="pps-field pps-shops">
                            <label>{l s='Shops' mod='productscheduler'}</label>
                            <label class="pps-check pps-check-strong">
                                <input type="checkbox" id="pps-shop-all" checked>
                                {l s='All shops of this product' mod='productscheduler'}
                            </label>
                            {foreach from=$pps_shops item=shop}
                                <label class="pps-check">
                                    <input type="checkbox" class="pps-shop" value="{$shop.id|intval}" checked disabled>
                                    {$shop.name|escape:'html':'UTF-8'}
                                </label>
                            {/foreach}
                        </div>
                    {/if}
                </div>
                <label class="pps-check" data-pps-new-wrap>
                    <input type="checkbox" id="pps-mark-new">
                    {l s='Show as a new product from the publish time' mod='productscheduler'}
                </label>
                <div class="pps-form-foot">
                    <button type="button" class="btn btn-primary" data-pps-add>{l s='Add schedule' mod='productscheduler'}</button>
                    <span class="pps-msg" data-pps-msg role="status" aria-live="polite"></span>
                </div>
            </div>

            <div class="pps-section">
                <div class="pps-section-title">{l s='Upcoming' mod='productscheduler'}</div>
                <div data-pps-upcoming></div>
            </div>
            <div class="pps-section">
                <div class="pps-section-title">{l s='History' mod='productscheduler'}</div>
                <div data-pps-history></div>
            </div>
            <div class="pps-runner" data-pps-runner></div>
            <div class="pps-tab-links">
                <a href="{$pps_manage_url|escape:'html':'UTF-8'}" target="_blank" rel="noopener">{l s='All schedules' mod='productscheduler'} &rarr;</a>
            </div>
        {/if}
    </div>
    {include file="./_footer.tpl" pps_compact=true}
</div>
<script src="{$pps_js_url|escape:'html':'UTF-8'}"></script>
