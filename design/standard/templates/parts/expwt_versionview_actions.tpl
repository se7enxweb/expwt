{* expwt: the actions of the site's version preview (content/versionview), in place of ezwt's
   website_toolbar_versionview.tpl: Edit and Publish of the version, Manage versions. A form of its own posting the
   kernel's actions (EditButton, PreviewPublishButton, VersionsButton) to content/versionview. Design neutral, as
   expwt_edit_actions.tpl. Variables of content/versionview: $object, $version, $language, $from_language, $is_creator. *}
{def $expwt_may = or( and( eq( $version.status, 0 ), $is_creator, $object.can_edit ), and( eq( $object.status, 2 ), $object.can_edit ) )}
{ezcss( array( 'expwt-actions.css' ) )}
<form class="expwt-actions expwt-actions-versionview" method="post" action={concat( 'content/versionview/', $object.id, '/', $version.version, '/', $language, '/', $from_language )|ezurl} aria-label="{'Manage versions'|i18n( 'extension/expwt' )|wash}">
    <div class="expwt-actions-group">
        <button class="expwt-act expwt-act-primary" type="submit" name="EditButton" value="1"{if $expwt_may|not} disabled="disabled"{/if}>{'Edit'|i18n( 'extension/expwt' )|wash}</button>
        <button class="expwt-act" type="submit" name="PreviewPublishButton" value="1"{if $expwt_may|not} disabled="disabled"{/if}>{'Publish'|i18n( 'extension/expwt' )|wash}</button>
        <button class="expwt-act expwt-act-quiet" type="submit" name="VersionsButton" value="1"{if $object.versions|count|le( 1 )} disabled="disabled"{/if}>{'Manage versions'|i18n( 'extension/expwt' )|wash}</button>
    </div>
</form>
{undef $expwt_may}
