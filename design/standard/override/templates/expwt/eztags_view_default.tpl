{* content/datatype/edit/view/default.tpl of eztags in the sidebar's frame (override.ini
   [expwt_frame_eztags_view_default]): what eztags' Default builder shows (the dialog to add a new tag, where the user
   may) and the tag tree to choose from (eztags' own tree, its subtree limit through ezjsctags::treeConfig), behind a
   disclosure. expwt-field.js builds the field with eztags' Tree builder, so a tag chosen in the tree is added. *}
{if $permission_array.can_add}
    {include uri='design:ezjsctemplate/modal_dialog.tpl' attribute_id=$attribute.id root_tag=$permission_array.allowed_locations_tags}
{/if}
<details class="expwt-tags-tree">
    <summary class="a4-btn">{'Choose a tag'|i18n( 'extension/eztags/datatypes' )}</summary>
    <div class="ez-tags-tree-selector"
        data-config-url="{concat( '/ezjscore/call/ezjsctags::treeConfig::', $attribute.id, '::', $attribute.version )|ezurl( no )}"
        data-base-url="{'/ezjscore/call/ezjsctags::tree::'|ezurl( no )}">
    </div>
</details>
