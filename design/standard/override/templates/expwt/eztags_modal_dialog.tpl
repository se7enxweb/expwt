{* ezjsctemplate/modal_dialog.tpl in the sidebar's frame (override.ini [expwt_frame_eztags_modal_dialog]): the dialog
   "Add new" of the tags field opens to ask where the new tag goes, on the sidebar's model: a <dialog> (eztags.js,
   EzTags.Dialog, as in admin4) with a title, a close button, eztags' tag trees (ezjsctemplate/tree_menu.tpl) and Cancel.
   A tag chosen in a tree is the new tag's parent; Escape, the close button and Cancel (class jqmClose) close it
   without one. The id is eztags' own, so the field finds it. *}
{if is_unset( $attribute_id )}{def $attribute_id = '0'}{/if}
{include uri='design:ezjsctemplate/tree_menu_script.tpl' menu_persistence=false()}
{ezscript_require( array( 'eztags.js', 'tagsstructuremenu.js' ) )}
<dialog class="eztags-dialog expwt-dialog parent-selector-tree" id="parent-selector-tree-{$attribute_id}" aria-labelledby="parent-selector-tree-{$attribute_id}-title">
    <div class="expwt-dialog-head">
        <h2 class="expwt-dialog-title" id="parent-selector-tree-{$attribute_id}-title">{'Select parent'|i18n( 'extension/eztags/tags/edit' )}</h2>
        <button type="button" class="a4-btn is-icon jqmClose" aria-label="{'Close'|i18n( 'extension/eztags/tags/view' )|wash}" title="{'Close'|i18n( 'extension/eztags/tags/view' )|wash}">&times;</button>
    </div>
    <div class="eztags-dialog-body expwt-dialog-body">
        <div id="content-tree-{$attribute_id}">
            <div class="contentstructure">
            {if and( is_set( $root_tag ), $root_tag|is_array )}
                {foreach $root_tag as $key => $value}
                    {include uri='design:ezjsctemplate/tree_menu.tpl' attribute_id=concat( $attribute_id, '_', $key ) root_tag=$value}
                {/foreach}
            {else}
                {include uri='design:ezjsctemplate/tree_menu.tpl' attribute_id=$attribute_id root_tag=cond( is_set( $root_tag ), $root_tag, false() )}
            {/if}
            </div>
        </div>
    </div>
    <div class="expwt-dialog-foot">
        <button type="button" class="a4-btn jqmClose">{'Cancel'|i18n( 'extension/eztags/datatypes' )}</button>
    </div>
</dialog>
