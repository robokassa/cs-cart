{if $addons.rus_taxes.status != "A"}
	<div class="control-group">
		<label class="control-label" for="elm_tax_type_{$id}">{__("csc_tax_type")}:</label>
		<div class="controls">
			<select name="tax_data[tax_type]" id="elm_tax_type_{$id}">
				{foreach from=fn_get_schema('csc_robokassa', 'tax_types') key="tax_type" item="descr"}
					<option value="{$tax_type}" {if $tax.tax_type == $tax_type}selected="selected"{/if}>{$descr}</option>
				{/foreach}
			</select>
		</div>
	</div>
{/if}
