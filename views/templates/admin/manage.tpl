{**
 * Catalog > Scheduled publishing
 * @author Mohammad Babaei | https://adschi.com
 *}
<link rel="stylesheet" href="{$pps_css_url|escape:'html':'UTF-8'}">
<div class="pps pps-manage" data-pps-config="{$pps_config_json|escape:'html':'UTF-8'}">

    {if $pps_flash}
        <div class="alert alert-{$pps_flash.t|escape:'html':'UTF-8'}">{$pps_flash.m|escape:'html':'UTF-8'}</div>
    {/if}

    <div class="pps-kpis">
        <a class="pps-kpi" href="{$pps_status_tabs.upcoming|escape:'html':'UTF-8'}">
            <span class="pps-kpi-value">{$pps_counts.upcoming|intval}</span>
            <span class="pps-kpi-label">{l s='Waiting' mod='productscheduler'}</span>
        </a>
        <div class="pps-kpi">
            <span class="pps-kpi-value pps-kpi-small">{if $pps_runner.next_due}{$pps_runner.next_due|escape:'html':'UTF-8'}{else}&mdash;{/if}</span>
            <span class="pps-kpi-label">{l s='Next change' mod='productscheduler'}{if $pps_runner.next_due_in !== null} &middot; <span data-pps-in="{$pps_runner.next_due_in|intval}"></span>{/if}</span>
        </div>
        <a class="pps-kpi" href="{$pps_status_tabs.done|escape:'html':'UTF-8'}">
            <span class="pps-kpi-value">{$pps_counts.done|intval}</span>
            <span class="pps-kpi-label">{l s='Done' mod='productscheduler'}</span>
        </a>
        <a class="pps-kpi{if $pps_counts.failed > 0} pps-kpi-bad{/if}" href="{$pps_status_tabs.failed|escape:'html':'UTF-8'}">
            <span class="pps-kpi-value">{$pps_counts.failed|intval}</span>
            <span class="pps-kpi-label">{l s='Failed' mod='productscheduler'}</span>
        </a>
    </div>

    <div class="alert alert-{$pps_runner.level|escape:'html':'UTF-8'} pps-runner-alert">
        <div>
            {$pps_runner.text|escape:'html':'UTF-8'}
            {if $pps_runner.last_run}<br><small>{l s='Last run' mod='productscheduler'}: {$pps_runner.last_run|escape:'html':'UTF-8'}{if $pps_runner.last_source} ({$pps_runner.last_source|escape:'html':'UTF-8'}){/if}</small>{/if}
        </div>
        <div class="pps-runner-actions">
            {if $pps_can_edit}
                <form method="post" action="{$pps_form_url|escape:'html':'UTF-8'}">
                    <input type="hidden" name="pps_token" value="{$pps_token|escape:'html':'UTF-8'}">
                    <button type="submit" name="submitPpsRunDue" value="1" class="btn btn-default btn-sm">{l s='Run due schedules now' mod='productscheduler'}</button>
                </form>
            {/if}
            <a class="btn btn-default btn-sm" href="{$pps_settings_url|escape:'html':'UTF-8'}">{l s='Settings and cron job' mod='productscheduler'}</a>
        </div>
    </div>

    {if $pps_can_edit}
    <div class="panel pps-panel">
        <div class="panel-heading"><i class="icon-plus-circle"></i> {l s='Add schedule' mod='productscheduler'}</div>
        <form method="post" action="{$pps_form_url|escape:'html':'UTF-8'}" class="pps-form" autocomplete="off" data-pps-add-form>
            <input type="hidden" name="pps_token" value="{$pps_token|escape:'html':'UTF-8'}">
            <input type="hidden" name="pps_product_ids" value="" data-pps-picked>
            <div class="pps-grid">
                <div class="pps-field pps-span-2">
                    <label for="pps-picker-q">{l s='Products' mod='productscheduler'}</label>
                    <div class="pps-picker">
                        <div class="pps-chips" data-pps-chips></div>
                        <input type="search" id="pps-picker-q" class="form-control" data-pps-picker
                               placeholder="{l s='Search by name, reference or ID' mod='productscheduler'}">
                        <div class="pps-picker-results" data-pps-results hidden></div>
                    </div>
                    <input type="text" name="pps_product_ids_extra" class="form-control pps-mt"
                           placeholder="{l s='... or paste product IDs, e.g. 12, 15, 33' mod='productscheduler'}">
                </div>
                <div class="pps-field">
                    <label for="pps-l-action">{l s='Action' mod='productscheduler'}</label>
                    <select id="pps-l-action" name="pps_action" class="form-control" data-pps-action>
                        <option value="publish">{l s='Publish (set online)' mod='productscheduler'}</option>
                        <option value="unpublish">{l s='Unpublish (set offline)' mod='productscheduler'}</option>
                    </select>
                </div>
                <div class="pps-field">
                    <label for="pps-l-at">{l s='Date and time' mod='productscheduler'}</label>
                    <input type="datetime-local" id="pps-l-at" name="pps_at" class="form-control" step="60" min="{$pps_min_input|escape:'html':'UTF-8'}" required data-pps-at>
                    <div class="pps-quick">
                        <button type="button" class="pps-chip" data-pps-quick="1h">+1 h</button>
                        <button type="button" class="pps-chip" data-pps-quick="tomorrow">{l s='Tomorrow 08:00' mod='productscheduler'}</button>
                        <button type="button" class="pps-chip" data-pps-quick="monday">{l s='Monday 08:00' mod='productscheduler'}</button>
                    </div>
                </div>
                <div class="pps-field">
                    <label class="pps-check">
                        <input type="checkbox" name="pps_end_on" value="1" data-pps-end-on>
                        <span data-pps-end-label
                              data-publish="{l s='Unpublish again at' mod='productscheduler'}"
                              data-unpublish="{l s='Publish again at' mod='productscheduler'}">{l s='Unpublish again at' mod='productscheduler'}</span>
                        <span class="pps-optional">({l s='optional' mod='productscheduler'})</span>
                    </label>
                    <input type="datetime-local" name="pps_end_at" class="form-control" step="60" min="{$pps_min_input|escape:'html':'UTF-8'}" disabled data-pps-end>
                </div>
                {if $pps_multistore && $pps_shops|@count > 0}
                    <div class="pps-field pps-shops">
                        <label>{l s='Shops' mod='productscheduler'}</label>
                        <label class="pps-check pps-check-strong">
                            <input type="checkbox" name="pps_shop_all" value="1" checked data-pps-shop-all>
                            {l s='All shops of each product' mod='productscheduler'}
                        </label>
                        {foreach from=$pps_shops item=shop}
                            <label class="pps-check">
                                <input type="checkbox" name="pps_shops[]" value="{$shop.id|intval}" checked disabled data-pps-shop>
                                {$shop.name|escape:'html':'UTF-8'}
                            </label>
                        {/foreach}
                    </div>
                {/if}
            </div>
            <label class="pps-check" data-pps-new-wrap>
                <input type="checkbox" name="pps_mark_new" value="1">
                {l s='Show as a new product from the publish time' mod='productscheduler'}
            </label>
            <div class="pps-form-foot">
                <button type="submit" name="submitPpsAdd" value="1" class="btn btn-primary">{l s='Add schedule' mod='productscheduler'}</button>
            </div>
        </form>
    </div>
    {/if}

    {if $pps_blog.available && $pps_can_edit}
    <div class="panel pps-panel pps-blog-panel" id="pps-blog">
        <div class="panel-heading">
            <i class="icon-book"></i> {l s='Plan blog articles' mod='productscheduler'}
            {if $pps_blog.unplanned}<span class="badge pps-badge-warn">{$pps_blog.unplanned|intval} {l s='offline and not planned' mod='productscheduler'}</span>{/if}
        </div>
        <p class="pps-sub pps-blog-intro">{l s='Publish written articles automatically - one at a time or as a series, e.g. one article every day at 10:00. Articles that are offline and not planned yet are ticked already.' mod='productscheduler'}</p>
        {if $pps_blog.articles}
        <form method="post" action="{$pps_form_url|escape:'html':'UTF-8'}" class="pps-form" autocomplete="off" data-pps-blog-form>
            <input type="hidden" name="pps_token" value="{$pps_token|escape:'html':'UTF-8'}">
            <div class="table-responsive">
                <table class="table pps-table pps-blog-table">
                    <thead>
                        <tr>
                            <th class="pps-col-check"><input type="checkbox" data-pps-blog-all aria-label="{l s='Select all' mod='productscheduler'}"></th>
                            <th class="pps-col-order">{l s='Order' mod='productscheduler'}</th>
                            <th>{l s='Article' mod='productscheduler'}</th>
                            <th>{l s='Status' mod='productscheduler'}</th>
                            <th>{l s='Article date' mod='productscheduler'}</th>
                            <th>{l s='Next change' mod='productscheduler'}</th>
                        </tr>
                    </thead>
                    <tbody>
                    {foreach from=$pps_blog.articles item=a name=blog}
                        <tr>
                            <td class="pps-col-check"><input type="checkbox" name="pps_blog_ids[]" value="{$a.id|intval}" data-pps-blog-check{if !$a.active && !$a.next} checked{/if}></td>
                            <td class="pps-col-order"><input type="number" min="0" max="999" name="pps_blog_order[{$a.id|intval}]" value="{$smarty.foreach.blog.iteration|intval}" class="form-control input-sm" data-pps-blog-order></td>
                            <td>
                                {if $a.edit_url}<a class="pps-product" href="{$a.edit_url|escape:'html':'UTF-8'}">{$a.name|escape:'html':'UTF-8'}</a>{else}{$a.name|escape:'html':'UTF-8'}{/if}
                                <div class="pps-sub">ID {$a.id|intval}{if $a.category} &middot; {$a.category|escape:'html':'UTF-8'}{/if}</div>
                            </td>
                            <td><span class="pps-status {if $a.active}pps-status-done{else}pps-status-cancelled{/if}">{if $a.active}{l s='Online' mod='productscheduler'}{else}{l s='Offline' mod='productscheduler'}{/if}</span></td>
                            <td>{$a.date|escape:'html':'UTF-8'}</td>
                            <td>
                                {if $a.next}
                                    <span class="pps-badge pps-badge-{$a.next.action|escape:'html':'UTF-8'}">{if $a.next.action == 'publish'}{l s='Publish' mod='productscheduler'}{else}{l s='Unpublish' mod='productscheduler'}{/if}</span>
                                    {$a.next.when|escape:'html':'UTF-8'}
                                    <button type="submit" form="pps-row-form" name="pps_row" value="cancel:{$a.next.id|intval}" class="btn btn-link btn-sm pps-btn-danger"
                                            data-pps-confirm="{l s='Cancel this schedule?' mod='productscheduler'}" data-pps-confirm-danger="1">{l s='Cancel' mod='productscheduler'}</button>
                                {else}<span class="pps-sub">&mdash;</span>{/if}
                            </td>
                        </tr>
                    {/foreach}
                    </tbody>
                </table>
            </div>
            <div class="pps-grid pps-blog-grid">
                <div class="pps-field">
                    <label for="pps-b-action">{l s='Action' mod='productscheduler'}</label>
                    <select id="pps-b-action" name="pps_blog_action" class="form-control" data-pps-blog-action>
                        <option value="publish">{l s='Publish (set online)' mod='productscheduler'}</option>
                        <option value="unpublish">{l s='Unpublish (set offline)' mod='productscheduler'}</option>
                    </select>
                </div>
                <div class="pps-field">
                    <label>{l s='When' mod='productscheduler'}</label>
                    <label class="pps-check"><input type="radio" name="pps_blog_mode" value="series" checked data-pps-blog-mode> {l s='As a series, one after the other' mod='productscheduler'}</label>
                    <label class="pps-check"><input type="radio" name="pps_blog_mode" value="at" data-pps-blog-mode> {l s='All at the same time' mod='productscheduler'}</label>
                </div>
                <div class="pps-field" data-pps-blog-series>
                    <label>{l s='Start and rhythm' mod='productscheduler'}</label>
                    <div class="pps-inline">
                        <input type="date" name="pps_blog_start" class="form-control" value="{$pps_blog.start|escape:'html':'UTF-8'}" min="{$smarty.now|date_format:'%Y-%m-%d'}" data-pps-blog-start>
                        <input type="time" name="pps_blog_time" class="form-control" value="{$pps_blog.time|escape:'html':'UTF-8'}" data-pps-blog-time>
                    </div>
                    <select name="pps_blog_every" class="form-control pps-mt" data-pps-blog-every>
                        <option value="1">{l s='one article every day' mod='productscheduler'}</option>
                        <option value="2">{l s='one article every 2 days' mod='productscheduler'}</option>
                        <option value="3">{l s='one article every 3 days' mod='productscheduler'}</option>
                        <option value="7">{l s='one article every week' mod='productscheduler'}</option>
                    </select>
                </div>
                <div class="pps-field" data-pps-blog-at hidden>
                    <label for="pps-b-at">{l s='Date and time' mod='productscheduler'}</label>
                    <input type="datetime-local" id="pps-b-at" name="pps_blog_at" class="form-control" step="60" min="{$pps_min_input|escape:'html':'UTF-8'}">
                </div>
            </div>
            <label class="pps-check" data-pps-blog-new-wrap>
                <input type="checkbox" name="pps_blog_new" value="1" checked>
                {l s='Show as the newest article: its date becomes the publish day' mod='productscheduler'}
            </label>
            <div class="pps-blog-preview" data-pps-blog-preview data-none="{l s='Tick at least one article.' mod='productscheduler'}" data-one="{l s='1 article on %s' mod='productscheduler'}" data-many="{l s='%1$d articles: first on %2$s, last on %3$s' mod='productscheduler'}"></div>
            <div class="pps-form-foot">
                <button type="submit" name="submitPpsBlog" value="1" class="btn btn-primary" data-pps-confirm="{l s='Plan the ticked articles?' mod='productscheduler'}">{l s='Plan articles' mod='productscheduler'}</button>
            </div>
        </form>
        {else}
            <div class="pps-empty-state"><p>{l s='The blog has no articles yet.' mod='productscheduler'}</p></div>
        {/if}
    </div>
    {/if}

    <div class="panel pps-panel">
        <div class="panel-heading">
            <i class="icon-calendar"></i> {l s='Schedules' mod='productscheduler'}
            <span class="badge">{$pps_total|intval}</span>
        </div>

        <ul class="pps-tabs">
            <li{if $pps_filters.f_status == 'upcoming'} class="active"{/if}><a href="{$pps_status_tabs.upcoming|escape:'html':'UTF-8'}">{l s='Waiting' mod='productscheduler'} <span class="pps-count">{$pps_counts.upcoming|intval}</span></a></li>
            <li{if $pps_filters.f_status == 'done'} class="active"{/if}><a href="{$pps_status_tabs.done|escape:'html':'UTF-8'}">{l s='Done' mod='productscheduler'} <span class="pps-count">{$pps_counts.done|intval}</span></a></li>
            <li{if $pps_filters.f_status == 'failed'} class="active"{/if}><a href="{$pps_status_tabs.failed|escape:'html':'UTF-8'}">{l s='Failed' mod='productscheduler'} <span class="pps-count">{$pps_counts.failed|intval}</span></a></li>
            <li{if $pps_filters.f_status == 'cancelled'} class="active"{/if}><a href="{$pps_status_tabs.cancelled|escape:'html':'UTF-8'}">{l s='Cancelled' mod='productscheduler'} <span class="pps-count">{$pps_counts.cancelled|intval}</span></a></li>
            <li{if $pps_filters.f_status == 'all'} class="active"{/if}><a href="{$pps_status_tabs.all|escape:'html':'UTF-8'}">{l s='All' mod='productscheduler'}</a></li>
        </ul>

        <form method="get" action="{$pps_filter_action|escape:'html':'UTF-8'}" class="pps-filters">
            {foreach from=$pps_filter_hidden key=name item=value}
                <input type="hidden" name="{$name|escape:'html':'UTF-8'}" value="{$value|escape:'html':'UTF-8'}">
            {/foreach}
            <input type="hidden" name="f_status" value="{$pps_filters.f_status|escape:'html':'UTF-8'}">
            <input type="search" name="f_q" class="form-control" value="{$pps_filters.f_q|escape:'html':'UTF-8'}"
                   placeholder="{l s='Product name, reference or ID' mod='productscheduler'}">
            {if $pps_blog.available}
            <select name="f_type" class="form-control">
                <option value="">{l s='Products and blog' mod='productscheduler'}</option>
                <option value="product"{if $pps_filters.f_type == 'product'} selected{/if}>{l s='Products' mod='productscheduler'}</option>
                <option value="blog"{if $pps_filters.f_type == 'blog'} selected{/if}>{l s='Blog articles' mod='productscheduler'}</option>
            </select>
            {/if}
            <select name="f_action" class="form-control">
                <option value="">{l s='All actions' mod='productscheduler'}</option>
                <option value="publish"{if $pps_filters.f_action == 'publish'} selected{/if}>{l s='Publish' mod='productscheduler'}</option>
                <option value="unpublish"{if $pps_filters.f_action == 'unpublish'} selected{/if}>{l s='Unpublish' mod='productscheduler'}</option>
            </select>
            <select name="n" class="form-control" data-pps-autosubmit>
                {foreach from=$pps_per_page_options item=n}
                    <option value="{$n|intval}"{if $pps_filters.n == $n} selected{/if}>{$n|intval} {l s='per page' mod='productscheduler'}</option>
                {/foreach}
            </select>
            <button type="submit" class="btn btn-default"><i class="icon-search"></i> {l s='Filter' mod='productscheduler'}</button>
            {if $pps_filters.f_q || $pps_filters.f_action || $pps_filters.f_type}
                <a class="btn btn-link" href="{$pps_status_tabs[$pps_filters.f_status]|escape:'html':'UTF-8'}&amp;f_q=&amp;f_action=&amp;f_type=">{l s='Reset' mod='productscheduler'}</a>
            {/if}
        </form>

        {if $pps_can_edit}
            <form id="pps-row-form" method="post" action="{$pps_form_url|escape:'html':'UTF-8'}">
                <input type="hidden" name="pps_token" value="{$pps_token|escape:'html':'UTF-8'}">
                <input type="hidden" name="submitPpsRow" value="1">
            </form>
            <form id="pps-bulk-form" method="post" action="{$pps_form_url|escape:'html':'UTF-8'}">
                <input type="hidden" name="pps_token" value="{$pps_token|escape:'html':'UTF-8'}">
                <input type="hidden" name="submitPpsBulk" value="1">
            </form>
        {/if}

        {if $pps_items}
            <div class="table-responsive">
                <table class="table pps-table">
                    <thead>
                        <tr>
                            {if $pps_can_edit}<th class="pps-col-check"><input type="checkbox" data-pps-check-all aria-label="{l s='Select all' mod='productscheduler'}"></th>{/if}
                            <th>{l s='Product' mod='productscheduler'}</th>
                            {if $pps_multistore}<th>{l s='Shop' mod='productscheduler'}</th>{/if}
                            <th>{l s='Action' mod='productscheduler'}</th>
                            <th>{l s='Scheduled for' mod='productscheduler'}</th>
                            <th>{l s='Status' mod='productscheduler'}</th>
                            <th>{l s='Result' mod='productscheduler'}</th>
                            <th class="text-right"></th>
                        </tr>
                    </thead>
                    <tbody>
                    {foreach from=$pps_items item=row}
                        <tr class="pps-row pps-row-{$row.status|escape:'html':'UTF-8'}">
                            {if $pps_can_edit}
                                <td class="pps-col-check">{if $row.can_manage}<input type="checkbox" name="pps_ids[]" value="{$row.id|intval}" form="pps-bulk-form" data-pps-check>{/if}</td>
                            {/if}
                            <td>
                                {if $row.type == 'blog'}<span class="pps-badge pps-badge-blog">{l s='Blog' mod='productscheduler'}</span> {/if}<a class="pps-product" href="{$row.edit_url|escape:'html':'UTF-8'}">{if $row.product_name}{$row.product_name|escape:'html':'UTF-8'}{else}#{$row.id_product|intval}{/if}</a>
                                <div class="pps-sub">ID {$row.id_product|intval}{if $row.reference} &middot; {$row.reference|escape:'html':'UTF-8'}{/if}{if $row.employee} &middot; {l s='by' mod='productscheduler'} {$row.employee|escape:'html':'UTF-8'}{/if}</div>
                            </td>
                            {if $pps_multistore}<td>{$row.shop_name|escape:'html':'UTF-8'}</td>{/if}
                            <td>
                                <span class="pps-badge pps-badge-{$row.action|escape:'html':'UTF-8'}">{$row.action_label|escape:'html':'UTF-8'}</span>
                                {if $row.mark_new}<span class="pps-sub">{l s='shown as new' mod='productscheduler'}</span>{/if}
                            </td>
                            <td>
                                <div class="pps-when">{$row.when|escape:'html':'UTF-8'}</div>
                                {if $row.status == 'pending'}<div class="pps-sub" data-pps-in="{$row.in_seconds|intval}" data-pps-pending="1"></div>{/if}
                                {if $row.status == 'pending' && $row.can_manage && $pps_can_edit}
                                    <div class="pps-reschedule" id="pps-rs-{$row.id|intval}" hidden>
                                        <input type="datetime-local" name="pps_at_{$row.id|intval}" form="pps-row-form" class="form-control input-sm" step="60"
                                               value="{$row.input_value|escape:'html':'UTF-8'}">
                                        <button type="submit" form="pps-row-form" name="pps_row" value="reschedule:{$row.id|intval}" class="btn btn-primary btn-sm">{l s='Save' mod='productscheduler'}</button>
                                    </div>
                                {/if}
                            </td>
                            <td><span class="pps-status pps-status-{$row.status|escape:'html':'UTF-8'}">{$row.status_label|escape:'html':'UTF-8'}</span></td>
                            <td class="pps-col-result">
                                {if $row.executed}<div class="pps-sub">{$row.executed|escape:'html':'UTF-8'}</div>{/if}
                                {if $row.message}<div>{$row.message|escape:'html':'UTF-8'}</div>{/if}
                            </td>
                            <td class="text-right pps-actions">
                                {if $pps_can_edit && $row.can_manage}
                                    {if $row.status == 'pending'}
                                        <button type="button" class="btn btn-default btn-sm" data-pps-toggle="#pps-rs-{$row.id|intval}">{l s='Change time' mod='productscheduler'}</button>
                                        <button type="submit" form="pps-row-form" name="pps_row" value="run:{$row.id|intval}" class="btn btn-default btn-sm"
                                                data-pps-confirm="{l s='Run this schedule now? The product changes immediately.' mod='productscheduler'}">{l s='Run now' mod='productscheduler'}</button>
                                        <button type="submit" form="pps-row-form" name="pps_row" value="cancel:{$row.id|intval}" class="btn btn-default btn-sm pps-btn-danger" data-pps-confirm-danger="1"
                                                data-pps-confirm="{l s='Cancel this schedule?' mod='productscheduler'}">{l s='Cancel' mod='productscheduler'}</button>
                                    {elseif $row.status != 'running'}
                                        <button type="submit" form="pps-row-form" name="pps_row" value="delete:{$row.id|intval}" class="btn btn-default btn-sm" data-pps-confirm-danger="1"
                                                data-pps-confirm="{l s='Delete this entry from the history?' mod='productscheduler'}">{l s='Delete' mod='productscheduler'}</button>
                                    {/if}
                                {/if}
                            </td>
                        </tr>
                    {/foreach}
                    </tbody>
                </table>
            </div>

            <div class="pps-list-foot">
                {if $pps_can_edit}
                    <div class="pps-bulk">
                        <select name="pps_bulk_action" form="pps-bulk-form" class="form-control">
                            <option value="">{l s='Bulk action' mod='productscheduler'}</option>
                            <option value="cancel">{l s='Cancel selected (waiting)' mod='productscheduler'}</option>
                            <option value="delete">{l s='Delete selected (finished)' mod='productscheduler'}</option>
                        </select>
                        <button type="submit" form="pps-bulk-form" class="btn btn-default"
                                data-pps-confirm="{l s='Apply the bulk action to the selected entries?' mod='productscheduler'}">{l s='Apply' mod='productscheduler'}</button>
                    </div>
                {/if}
                {if $pps_pages > 1}
                    <ul class="pagination pps-pagination">
                        <li{if $pps_page == 1} class="disabled"{/if}><a href="{$pps_first_url|escape:'html':'UTF-8'}">&laquo;</a></li>
                        {foreach from=$pps_page_urls key=p item=url}
                            <li{if $p == $pps_page} class="active"{/if}><a href="{$url|escape:'html':'UTF-8'}">{$p|intval}</a></li>
                        {/foreach}
                        <li{if $pps_page == $pps_pages} class="disabled"{/if}><a href="{$pps_last_url|escape:'html':'UTF-8'}">&raquo;</a></li>
                    </ul>
                {/if}
            </div>
        {else}
            <div class="pps-empty-state">
                <div class="pps-empty-icon" aria-hidden="true">&#9201;</div>
                <p>{l s='No schedules here yet.' mod='productscheduler'}</p>
                <p class="pps-sub">{l s='Add one above, or open a product and use the Modules tab.' mod='productscheduler'}</p>
            </div>
        {/if}
    </div>

    {include file="./_footer.tpl"}
</div>
<script src="{$pps_js_url|escape:'html':'UTF-8'}"></script>
