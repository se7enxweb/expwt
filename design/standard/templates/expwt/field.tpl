{* expwt/field: one field of the user's draft in the editor the installation has for its datatype ({attribute_edit_gui},
   as content/edit shows it), in the sidebar's frame. expwt-field.js tells the sidebar which draft it is and, after a
   save, the field's new value ($message, JSON). *}
<form class="expwt-field-form" method="post" action={$form_action|ezurl} enctype="multipart/form-data">
    {if and( $validation.processed, $validation.attributes|count )}
    <div class="expwt-field-error" role="alert">
        {foreach $validation.attributes as $item}
        <p>{$item.name|wash}: {$item.description|wash}</p>
        {/foreach}
    </div>
    {/if}
    {if $saved}
    <p class="expwt-field-saved" role="status">{'The draft is saved.'|i18n( 'extension/expwt' )}</p>
    {/if}
    <div class="expwt-field-input">
        {attribute_edit_gui attribute=$attribute}
    </div>
    <div class="expwt-field-tools"></div>
    <div class="expwt-field-actions">
        <button type="submit" name="StoreButton" class="button defaultbutton">{'Save draft'|i18n( 'extension/expwt' )}</button>
    </div>
    <input type="hidden" name="HasObjectInput" value="1" />
</form>
<script type="application/json" id="expwt-field-message">{$message}</script>
{ezscript( array( 'expwt-field.js' ) )}
