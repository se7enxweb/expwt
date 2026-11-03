{* expwt: the actions of the site's edit form (content/edit), in place of ezwt's website_toolbar_edit.tpl.
   Included inside the design's <form> of content/edit: the buttons post the kernel's own actions (PublishButton,
   StoreButton, StoreExitButton, DiscardButton, VersionsButton, PreviewButton, FromLanguageButton), so nothing else
   is needed on the server. Design neutral: plain buttons, the classes expwt-actions*, drawn by expwt-actions.css
   from its --expwt-act-* tokens (a design may set them). The discard question is asked by expwt-actions.js
   (data-expwt-confirm), no inline script, no inline style.
   Variables of content/edit: $object, $from_language. Optional: $expwt_versions (default true) shows Manage versions,
   Preview and Translate. *}
{def $expwt_more = first_set( $expwt_versions, true() )}
{ezcss( array( 'expwt-actions.css' ) )}
<div class="expwt-actions expwt-actions-edit" role="group" aria-label="{'Edit'|i18n( 'extension/expwt' )|wash}">
    <div class="expwt-actions-group">
        <button class="expwt-act expwt-act-primary" type="submit" name="PublishButton" value="1" title="{'Publish the contents of the draft that is being edited. The draft will become the published version of the object.'|i18n( 'extension/expwt' )|wash}">{'Send for publishing'|i18n( 'extension/expwt' )|wash}</button>
        <button class="expwt-act" type="submit" name="StoreButton" value="1" title="{'Store the contents of the draft that is being edited and continue editing. Use this button to periodically save your work while editing.'|i18n( 'extension/expwt' )|wash}">{'Store draft'|i18n( 'extension/expwt' )|wash}</button>
        <button class="expwt-act" type="submit" name="StoreExitButton" value="1" title="{'Store the draft that is being edited and exit from edit mode. Use when you need to exit your work and return later to continue.'|i18n( 'extension/expwt' )|wash}">{'Store draft and exit'|i18n( 'extension/expwt' )|wash}</button>
        <button class="expwt-act expwt-act-danger" type="submit" name="DiscardButton" value="1" formnovalidate="formnovalidate" data-expwt-confirm="{'Are you sure you want to discard the draft?'|i18n( 'extension/expwt' )|wash}" title="{'Discard the draft that is being edited. This will also remove the translations that belong to the draft (if any).'|i18n( 'extension/expwt' )|wash}">{'Discard draft'|i18n( 'extension/expwt' )|wash}</button>
    </div>
{if $expwt_more}
    <div class="expwt-actions-group">
        <button class="expwt-act expwt-act-quiet" type="submit" name="VersionsButton" value="1">{'Manage versions'|i18n( 'extension/expwt' )|wash}</button>
        <button class="expwt-act expwt-act-quiet" type="submit" name="PreviewButton" value="1">{'Preview'|i18n( 'extension/expwt' )|wash}</button>
{if and( is_set( $object ), $object.status|ne( 0 ), $object.languages|count|gt( 1 ) )}
        <label class="expwt-actions-label" for="expwt-from-language">{'Translate from'|i18n( 'extension/expwt' )|wash}</label>
        <select class="expwt-act-select" id="expwt-from-language" name="FromLanguage">
            <option value=""{if first_set( $from_language, false() )|not} selected="selected"{/if}>-</option>
{foreach $object.languages as $expwt_lang}
            <option value="{$expwt_lang.locale|wash}"{if $expwt_lang.locale|eq( first_set( $from_language, '' ) )} selected="selected"{/if}>{$expwt_lang.name|wash}</option>
{/foreach}
        </select>
        <button class="expwt-act expwt-act-quiet" type="submit" name="FromLanguageButton" value="1">{'Translate'|i18n( 'extension/expwt' )|wash}</button>
{/if}
    </div>
{/if}
</div>
{ezscript( array( 'expwt-actions.js' ) )}
{undef $expwt_more}
