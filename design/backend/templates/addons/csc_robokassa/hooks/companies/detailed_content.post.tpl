{include file="common/subheader.tpl" title=__("robokassa") target="#robokassa"}

{if $runtime.company_id == 0}
    <div class="control-group">
        <label class="control-label" for="csc_robokassa_split">{__("split")}:</label>
        <div class="controls">
            <input type="hidden" name="company_data[csc_robokassa_split]" value="N">
            <input type="checkbox" name="company_data[csc_robokassa_split]" id="csc_robokassa_split" value="Y"{if $company_data.csc_robokassa_split == "Y"} checked{/if}>
        </div>
    </div>
{else}
    <div class="control-group">
        <label class="control-label" for="csc_robokassa_split">{__("split")}:</label>
        <div class="controls">
            <p>{if $company_data.csc_robokassa_split == "Y"}{__("active")}{else}{__("disabled")}{/if}</p>
        </div>
    </div>
{/if}

<div id="robokassa" class="in collapse">
    <div class="control-group">
        <label class="control-label" for="csc_robokassa_merchant_id">{__("merchantid")}:</label>
        <div class="controls">
            <input type="text" name="company_data[csc_robokassa_merchant_id]" id="csc_robokassa_merchant_id" value="{$company_data.csc_robokassa_merchant_id}">
        </div>
    </div>
</div>