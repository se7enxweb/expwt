{* expwt: the page toolbar of a design, in place of ezwt's website_toolbar.tpl. The page actions (edit, create, move,
   remove, sort, versions, translations, states) are in the website sidebar, which expwt adds to every page through
   the kernel's pagelayout hook (site.ini [PageLayoutHooks] BodyEnd[]=expwt/hook.tpl), so this include draws nothing
   where the sidebar is on. Where it is off (expwt.ini [ExpwtSettings] Enabled), it draws a minimal toolbar: Edit for
   an editor of the page. Variables: $current_node (optional; the page's node).
   expwt.ini ships Enabled commented out (its default, disabled, is in the code), so the setting is read only where
   a siteaccess or an override sets it; reading it unconditionally logged an error on every page. *}
{def $expwt_sidebar_on = false()}
{if ezini_hasvariable( 'ExpwtSettings', 'Enabled', 'expwt.ini' )}{set $expwt_sidebar_on = ezini( 'ExpwtSettings', 'Enabled', 'expwt.ini' )|eq( 'enabled' )}{/if}
{if and( $expwt_sidebar_on|not, is_set( $current_node ), $current_node.can_edit )}
{ezcss( array( 'expwt-actions.css' ) )}
<nav class="expwt-actions expwt-actions-page" aria-label="{'Edit'|i18n( 'extension/expwt' )|wash}">
    <div class="expwt-actions-group">
        <a class="expwt-act expwt-act-primary" href={concat( 'content/edit/', $current_node.contentobject_id )|ezurl}>{'Edit'|i18n( 'extension/expwt' )|wash}</a>
    </div>
</nav>
{/if}
{undef $expwt_sidebar_on}
