{if $settings.Security.secure_storefront === "YesNo::YES"|enum}
    {$storefront_url = fn_url('', 'SiteArea::STOREFRONT'|enum, 'https')|replace:$config.customer_index:""|rtrim:"/"}
{else}
    {$storefront_url = fn_url('', 'SiteArea::STOREFRONT'|enum, 'http')|replace:$config.customer_index:""|rtrim:"/"}
{/if}

{$result_url = "{$storefront_url}/payment_notification/result/csc_robokassa"}
{$success_url = "{$storefront_url}/payment_notification/success/csc_robokassa"}
{$fail_url = "{$storefront_url}/payment_notification/fail/csc_robokassa"}

<div class="control-group">
    <label class="control-label">Result Url</label>
    <div class="controls">
        {include file = "common/widget_copy.tpl" widget_copy_code_text=$result_url widget_copy_class="widget-copy--compact"}
    </div>
</div>
<div class="control-group">
    <label class="control-label">{__("csc_method")}</label>
    <div class="controls">
        <p class="switch">POST</p>
    </div>
</div>

<div class="control-group">
    <label class="control-label">Success Url</label>
    <div class="controls">
        {include file = "common/widget_copy.tpl" widget_copy_code_text=$success_url widget_copy_class="widget-copy--compact"}
    </div>
</div>
<div class="control-group">
    <label class="control-label">{__("csc_method")}</label>
    <div class="controls">
        <p class="switch">GET</p>
    </div>
</div>

<div class="control-group">
    <label class="control-label">Fail Url</label>
    <div class="controls">
        {include file = "common/widget_copy.tpl" widget_copy_code_text=$fail_url widget_copy_class="widget-copy--compact"}
    </div>
</div>
<div class="control-group">
    <label class="control-label">{__("csc_method")}</label>
    <div class="controls">
        <p class="switch">GET</p>
    </div>
</div>
<hr>

<div class="control-group">
    <label class="control-label" for="country">{__("country")}:</label>
    <div class="controls">
        <select name="payment_data[processor_params][country]" id="country">
            <option value="RU"{if $processor_params.country == 'RU'} selected="selected"{/if}>{__("russia")}</option>
            <option value="KZ"{if $processor_params.country == 'KZ'} selected="selected"{/if}>{__("kazakhstan")}</option>
        </select>
    </div>
</div>

<div class="control-group">
    <label class="control-label" for="split">{__("csc_split")}:</label>
    <div class="controls">
        <input type="hidden" name="payment_data[processor_params][split]" value="N">
        <input type="checkbox" name="payment_data[processor_params][split]" id="split" value="Y"{if $processor_params.split == "Y"} checked{/if}>
    </div>
</div>

<div class="control-group">
    <label class="control-label" for="merchantid">{__("merchantid")}:</label>
    <div class="controls">
        <input type="text" name="payment_data[processor_params][merchantid]" id="merchantid" value="{$processor_params.merchantid}">
    </div>
</div>

<div class="control-group">
    <label class="control-label" for="password1">{__("password1")}:</label>
    <div class="controls">
        <input type="text" name="payment_data[processor_params][password1]" id="password1" value="{$processor_params.password1}">
    </div>
</div>

<div class="control-group">
    <label class="control-label" for="password2">{__("password2")}:</label>
    <div class="controls">
        <input type="text" name="payment_data[processor_params][password2]" id="password2" value="{$processor_params.password2}">
    </div>
</div>

<div class="control-group">
    <label class="control-label" for="details">{__("description")}:</label>
    <div class="controls">
        <input type="text" name="payment_data[processor_params][details]" id="details" value="{$processor_params.details}">
    </div>
</div>

<div class="control-group">
    <label class="control-label" for="mode">{__("mode")}:</label>
    <div class="controls">
        <select name="payment_data[processor_params][mode]" id="mode">
            <option value="test"{if $processor_params.mode == 'test'} selected="selected"{/if}>{__("test")}</option>
            <option value="live"{if $processor_params.mode == 'live'} selected="selected"{/if}>{__("live")}</option>
        </select>
    </div>
</div>

<div class="control-group">
    <label class="control-label" for="encoding">{__("robokassa_encoding")}</label>
    <div class="controls">
        <p class="switch">MD5</p>
    </div>
</div>

{assign var="statuses" value=$smarty.const.STATUSES_ORDER|fn_get_simple_statuses}

<div class="control-group">
    <label class="control-label" for="status_paid">{__("status_paid")}:</label>
    <div class="controls">
        <select name="payment_data[processor_params][status_paid]" id="status_paid">
            {foreach $statuses as $status => $description}
                <option value="{$status}" {if $processor_params.status_paid == $status}selected="selected"{/if}>{$description}</option>
            {/foreach}
        </select>
    </div>
</div>

{include file="common/subheader.tpl" title=__("ru_settings") target="#ru_settings"}

<div id="ru_settings" class="in collapse">
    <div class="control-group">
        <label class="control-label" for="payment_method">{__("csc_payment_method")}:</label>
        <div class="controls">
            <select name="payment_data[processor_params][payment_method]" id="payment_method">
                <option value="full_prepayment"{if $processor_params.payment_method == "full_prepayment"} selected{/if}>{__("full_prepayment")}</option>
                <option value="full_payment"{if $processor_params.payment_method == "full_payment"} selected{/if}>{__("full_payment")}</option>
            </select>
        </div>
    </div>
</div>