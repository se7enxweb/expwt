{* content/datatype/edit/eztags.tpl in the sidebar's frame (override.ini [expwt_frame_eztags_edit]): the tags field on
   the sidebar's model. The selected tags are chips (the name and a remove button), below them the labeled search with
   its suggestions and "Add new" (when the user may add tags), the tags eztags suggests as chips to add, and "Browse
   tags" with eztags' own tag tree in the frame; a tag chosen in the tree is added. "Add new" asks for the new tag's
   parent in a dialog (ezjsctemplate/modal_dialog.tpl, expwt/eztags_modal_dialog.tpl here).
   eztags' plain JavaScript editor (eztags.js) builds it: the field's own inputs as eztags has them, and its templates
   (skeleton, selectedItem, suggestedItem, autocompleteItem) from the page, in script blocks of type text/html that
   eztags.js reads (Base.render_template) and the browser never runs. expwt-field.css draws it with the sidebar's
   tokens. A field of eztags' Select builder keeps its dropdowns. *}
{include uri='design:eztags/edit_scripts.tpl'}
{def $permission_array = $attribute.content.permission_array
     $builder = 'Default'}
{if $attribute.contentclass_attribute.data_text1}
    {set $builder = $attribute.contentclass_attribute.data_text1}
{/if}
{def $tree = ne( $builder, 'Select' )
     $base = concat( 'expwt-tags-', $attribute.id )}

{default attribute_base=ContentObjectAttribute}
<div class="eztags-wrapper expwt-tags">
    <div id="eztags{$attribute.id}" class="tagssuggest"
        data-eztags
        data-builder="{if $tree}Tree{else}{$builder|wash}{/if}"
        data-max-results="{ezini( 'GeneralSettings', 'MaxResults', 'eztags.ini' )}"
        data-has-add-access="{cond( $permission_array.can_add, 'true', true(), 'false' )}"
        data-subtree-limit="{$attribute.contentclass_attribute.data_int1}"
        data-hide-root-tag="{$attribute.contentclass_attribute.data_int3}"
        data-max-tags="{if $attribute.contentclass_attribute.data_int4|gt( 0 )}{$attribute.contentclass_attribute.data_int4}{else}0{/if}"
        data-locale="{$attribute.language_code}"
        data-translations="{json_encode( hash(
            'selectedTags', 'Selected tags'|i18n( 'extension/eztags/datatypes' ),
            'loading', concat( 'Loading'|i18n( 'extension/eztags/datatypes' ), '...' ),
            'noSelectedTags', 'There are no selected tags'|i18n( 'extension/eztags/datatypes' ),
            'suggestedTags', 'Suggested tags'|i18n( 'extension/eztags/datatypes' ),
            'noSuggestedTags', 'There are no tags to suggest'|i18n( 'extension/eztags/datatypes' ),
            'addNew', 'Add new'|i18n( 'extension/eztags/datatypes' ),
            'clickAddThisTag', 'Click to add this tag'|i18n( 'extension/eztags/datatypes' ),
            'removeTag', 'Remove tag'|i18n( 'extension/eztags/datatypes' ),
            'translateTag', 'Translate tag'|i18n( 'extension/eztags/datatypes' ),
            'existingTranslations', 'Existing translations'|i18n( 'extension/eztags/datatypes' ),
            'noExistingTranslations', 'No existing translations'|i18n( 'extension/eztags/datatypes' ),
            'addTranslation', 'Add translation'|i18n( 'extension/eztags/datatypes' ),
            'cancel', 'Cancel'|i18n( 'extension/eztags/datatypes' ),
            'ok', 'OK'|i18n( 'extension/eztags/datatypes' ),
            'findTag', 'Find a tag to add'|i18n( 'extension/eztags/datatypes' ),
            'chooseTag', 'Choose a tag'|i18n( 'extension/eztags/datatypes' ) ) )|wash}"
        >
        <input id="ezcoa-{if ne( $attribute_base, 'ContentObjectAttribute' )}{$attribute_base}-{/if}{$attribute.contentclassattribute_id}_{$attribute.contentclass_attribute_identifier}" class="tagnames" type="hidden" name="{$attribute_base}_eztags_data_text_{$attribute.id}" value="{$attribute.content.keyword_string|wash}" />
        <input id="ezcoa2-{if ne( $attribute_base, 'ContentObjectAttribute' )}{$attribute_base}-{/if}{$attribute.contentclassattribute_id}_{$attribute.contentclass_attribute_identifier}" class="tagpids" type="hidden" name="{$attribute_base}_eztags_data_text2_{$attribute.id}" value="{$attribute.content.parent_string|wash}" />
        <input id="ezcoa3-{if ne( $attribute_base, 'ContentObjectAttribute' )}{$attribute_base}-{/if}{$attribute.contentclassattribute_id}_{$attribute.contentclass_attribute_identifier}" class="tagids" type="hidden" name="{$attribute_base}_eztags_data_text3_{$attribute.id}" value="{$attribute.content.id_string|wash}" />
        <input id="ezcoa4-{if ne( $attribute_base, 'ContentObjectAttribute' )}{$attribute_base}-{/if}{$attribute.contentclassattribute_id}_{$attribute.contentclass_attribute_identifier}" class="taglocales" type="hidden" name="{$attribute_base}_eztags_data_text4_{$attribute.id}" value="{$attribute.content.locale_string|wash}" />
{if $tree}
        <script type="text/html" class="skeleton">
            <div class="tagssuggest-ui">
                <div class="tags-output">
                    <span class="expwt-tags-label" id="{$base}-selected">{'Selected tags'|i18n( 'extension/eztags/datatypes' )|wash}</span>
                    <div class="tags-list tags-listed no-results">
                        <p class="loading">{'Loading'|i18n( 'extension/eztags/datatypes' )|wash}...</p>
                        <p class="no-results">{'There are no selected tags'|i18n( 'extension/eztags/datatypes' )|wash}.</p>
                        <ul class="js-tags-selected expwt-chips" aria-labelledby="{$base}-selected"></ul>
                    </div>
                </div>
                <div class="tags-input">
                    <label class="expwt-tags-label" for="{$base}-find">{'Find a tag to add'|i18n( 'extension/eztags/datatypes' )|wash}</label>
                    <div class="expwt-tags-find">
                        <div class="tagssuggestfieldwrap">
                            <input id="{$base}-find" class="tagssuggestfield tags-input-field a4-field" type="text" value="" autocomplete="off" />
                            <div class="tagssuggestresults jsonSuggestResults"><div class="results-wrap"></div></div>
                        </div>
                        <button type="button" class="button-add-tag a4-btn" disabled="disabled">{'Add new'|i18n( 'extension/eztags/datatypes' )|wash}</button>
                    </div>
                    <div class="tags-list tags-suggested no-results">
                        <span class="expwt-tags-label" id="{$base}-suggested">{'Suggested tags'|i18n( 'extension/eztags/datatypes' )|wash}</span>
                        <p class="loading">{'Loading'|i18n( 'extension/eztags/datatypes' )|wash}...</p>
                        <ul class="js-tags-suggested expwt-chips" aria-labelledby="{$base}-suggested"></ul>
                    </div>
                </div>
            </div>
        </script>
        <script type="text/html" class="selectedItem">
            <li class="expwt-chip" data-cid="<%= tag.cid %>"><span class="expwt-chip-name"><%= tag.name %></span><button type="button" class="js-tags-remove a4-chip-remove" title="<%= tr.removeTag %>" aria-label="<%= tr.removeTag %>: <%= tag.name %>">&times;</button></li>
        </script>
        <script type="text/html" class="suggestedItem">
            <li class="expwt-chip is-suggested js-suggested-item" data-cid="<%= tag.cid %>"><button type="button" class="expwt-chip-add" title="<%= tr.clickAddThisTag %>"><%= tag.name %></button></li>
        </script>
        <script type="text/html" class="autocompleteItem">
            <div data-cid="<%= tag.cid %>" class="js-autocomplete-item resultItem<%= tag.main_tag_id == "0" ? "" : " itemSynonym" %>"><a href="#"><span class="expwt-tags-result-name"><%= tag.name %></span><span class="expwt-tags-result-path"><%= tag.parent_name %></span></a></div>
        </script>
{/if}
    </div>
{if $tree}
    <details class="expwt-tags-tree">
        <summary class="a4-btn">{'Browse tags'|i18n( 'extension/expwt' )}</summary>
        <div class="ez-tags-tree-selector"
            data-config-url="{concat( '/ezjscore/call/ezjsctags::treeConfig::', $attribute.id, '::', $attribute.version )|ezurl( no )}"
            data-base-url="{'/ezjscore/call/ezjsctags::tree::'|ezurl( no )}">
        </div>
    </details>
    {if $permission_array.can_add}
        {include uri='design:ezjsctemplate/modal_dialog.tpl' attribute_id=$attribute.id root_tag=$permission_array.allowed_locations_tags}
    {/if}
{/if}
</div>
{/default}
{undef $permission_array $builder $tree $base}
