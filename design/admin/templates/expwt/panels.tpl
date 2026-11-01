{* Setup > Website sidebar panels (E9): the panels of the website sidebar per role and siteaccess, their order and
   which are shown; the roles with panels of their own (the first decides the order); the earlier versions of the
   file to restore. The look is the shared components only (components.css): no style of its own. *}
<div class="context-block a4-page">

<div class="box-header"><div class="box-ml">
<h1 class="context-title">{'Website sidebar panels'|i18n( 'extension/expwt' )}</h1>
</div></div>

<div class="box-bc"><div class="box-ml"><div class="box-content a4-stack">

<p class="a4-intro">{'Which panels of the website sidebar the users of a role see, and in which order, per siteaccess. What users may do stays with the policies of their roles: a hidden panel is only not shown.'|i18n( 'extension/expwt' )}</p>

{if $flash}
<p class="a4-notice is-{$flash.kind|wash}" role="status">{$flash.message|wash}</p>
{/if}

<form method="post" action={'/expwt/panels'|ezurl} class="a4-filterbar">
<label class="a4-field"><span class="a4-label">{'Siteaccess'|i18n( 'extension/expwt' )}</span>
<select name="SiteAccess" class="a4-input is-auto">
{foreach $siteaccesses as $s}
<option value="{$s.name|wash}"{if eq( $s.name, $siteaccess )} selected="selected"{/if}>{$s.name|wash} ({if $s.on}{'Sidebar on'|i18n( 'extension/expwt' )}{else}{'Sidebar off'|i18n( 'extension/expwt' )}{/if})</option>
{/foreach}
</select></label>
<label class="a4-field"><span class="a4-label">{'Role'|i18n( 'extension/expwt' )}</span>
<select name="RoleID" class="a4-input is-auto">
<option value="0">–</option>
{foreach $roles as $r}
<option value="{$r.id}"{if eq( $r.id, $role_id )} selected="selected"{/if}>{$r.name|wash}</option>
{/foreach}
</select></label>
<button type="submit" name="ChooseButton" class="a4-btn">{'Show'|i18n( 'extension/expwt' )}</button>
</form>

{if $decided_elsewhere}
<p class="a4-notice is-warn">{'Another settings file decides these settings for %siteaccess: what is saved here only takes effect without it.'|i18n( 'extension/expwt',, hash( '%siteaccess', $siteaccess ) )|wash}</p>
{/if}

{if $role_id}
<section class="a4-card" aria-labelledby="expwt-panels-role">
<div class="a4-card-head"><h2 class="a4-h2" id="expwt-panels-role">{$role_name|wash} <span class="a4-meta">{$siteaccess|wash}</span></h2></div>
<form method="post" action={$here|ezurl}>
{if $pending}<p class="a4-notice is-info" role="status">{'Unsaved changes: save them or leave the page.'|i18n( 'extension/expwt' )}</p>{/if}
<div class="a4-table-wrap">
<table class="a4-table is-compact">
<thead><tr>
<th>{'Panel'|i18n( 'extension/expwt' )}</th>
<th>{'Shown'|i18n( 'extension/expwt' )}</th>
{if $can_edit}<th><span class="a4-sr-only">{'Order'|i18n( 'extension/expwt' )}</span></th>{/if}
</tr></thead>
<tbody>
{foreach $rows as $i => $p}
<tr>
<td>{$p.label|wash}<input type="hidden" name="Order[]" value="{$p.id|wash}" /></td>
<td>{if $p.fixed}<input type="hidden" name="Shown[{$p.id|wash}]" value="1" /><span class="a4-tag">{'Always shown'|i18n( 'extension/expwt' )}</span>{else}<label class="a4-check"><input type="checkbox" name="Shown[{$p.id|wash}]" value="1"{if $p.shown} checked="checked"{/if}{if $can_edit|not} disabled="disabled"{/if} /> <span class="a4-sr-only">{'Shown'|i18n( 'extension/expwt' )}: {$p.label|wash}</span></label>{/if}</td>
{if $can_edit}<td class="a4-nowrap">
<button type="submit" name="MoveUp" value="{$p.id|wash}" class="a4-btn a4-btn-small a4-btn-ghost a4-btn-icon"{if eq( $i, 0 )} disabled="disabled"{/if} title="{'Move up'|i18n( 'extension/expwt' )|wash}"><span class="a4-icon a4-bi-arrow-up" aria-hidden="true"></span><span class="a4-sr-only">{'Move up'|i18n( 'extension/expwt' )}: {$p.label|wash}</span></button>
<button type="submit" name="MoveDown" value="{$p.id|wash}" class="a4-btn a4-btn-small a4-btn-ghost a4-btn-icon"{if eq( $i, sub( $rows|count, 1 ) )} disabled="disabled"{/if} title="{'Move down'|i18n( 'extension/expwt' )|wash}"><span class="a4-icon a4-bi-arrow-down" aria-hidden="true"></span><span class="a4-sr-only">{'Move down'|i18n( 'extension/expwt' )}: {$p.label|wash}</span></button>
</td>{/if}
</tr>
{/foreach}
</tbody>
</table>
</div>
{if $can_edit}
<div class="a4-toolbar">
<button type="submit" name="SaveButton" class="a4-btn a4-btn-primary">{'Save'|i18n( 'extension/expwt' )}</button>
{if $role_configured}<button type="submit" name="RemoveButton" class="a4-btn a4-btn-outline-danger">{'Remove the panels of this role'|i18n( 'extension/expwt' )}</button>{/if}
</div>
{/if}
</form>
</section>
{/if}

<section class="a4-card" aria-labelledby="expwt-panels-roles">
<div class="a4-card-head"><h2 class="a4-h2" id="expwt-panels-roles">{'Roles with panels of their own'|i18n( 'extension/expwt' )} <span class="a4-meta">{$siteaccess|wash}</span></h2></div>
{if $configured}
<p class="a4-help">{'A user with several of these roles gets the order of the first one; a panel stays shown when one of the user\'s roles shows it.'|i18n( 'extension/expwt' )}</p>
<form method="post" action={$here|ezurl}>
<div class="a4-table-wrap">
<table class="a4-table is-compact">
<thead><tr><th>{'Role'|i18n( 'extension/expwt' )}</th><th class="a4-num">{'Hidden'|i18n( 'extension/expwt' )}</th>{if $can_edit}<th><span class="a4-sr-only">{'Order'|i18n( 'extension/expwt' )}</span></th>{/if}</tr></thead>
<tbody>
{foreach $configured as $i => $c}
<tr>
<td><a href={concat( '/expwt/panels/', $siteaccess, '/', $c.id )|ezurl}>{$c.name|wash}</a></td>
<td class="a4-num">{$c.hidden}</td>
{if $can_edit}<td class="a4-nowrap">
<button type="submit" name="RoleUp" value="{$c.id}" class="a4-btn a4-btn-small a4-btn-ghost a4-btn-icon"{if eq( $i, 0 )} disabled="disabled"{/if} title="{'Move up'|i18n( 'extension/expwt' )|wash}"><span class="a4-icon a4-bi-arrow-up" aria-hidden="true"></span><span class="a4-sr-only">{'Move up'|i18n( 'extension/expwt' )}: {$c.name|wash}</span></button>
<button type="submit" name="RoleDown" value="{$c.id}" class="a4-btn a4-btn-small a4-btn-ghost a4-btn-icon"{if eq( $i, sub( $configured|count, 1 ) )} disabled="disabled"{/if} title="{'Move down'|i18n( 'extension/expwt' )|wash}"><span class="a4-icon a4-bi-arrow-down" aria-hidden="true"></span><span class="a4-sr-only">{'Move down'|i18n( 'extension/expwt' )}: {$c.name|wash}</span></button>
</td>{/if}
</tr>
{/foreach}
</tbody>
</table>
</div>
</form>
{else}
<p class="a4-empty">{'No role has panels of its own here: every user sees the panels in the sidebar\'s own order.'|i18n( 'extension/expwt' )}</p>
{/if}
</section>

{if $file}
<section class="a4-card" aria-labelledby="expwt-panels-history">
<div class="a4-card-head"><h2 class="a4-h2" id="expwt-panels-history">{'History'|i18n( 'extension/expwt' )}</h2></div>
<p class="a4-help">{'Earlier versions of %file, kept before every change.'|i18n( 'extension/expwt',, hash( '%file', $file ) )|wash}</p>
{if $history}
<form method="post" action={$here|ezurl}>
<div class="a4-table-wrap">
<table class="a4-table is-compact">
<thead><tr><th>{'In place until'|i18n( 'extension/expwt' )}</th><th>{'Roles with panels of their own'|i18n( 'extension/expwt' )}</th>{if $can_edit}<th><span class="a4-sr-only">{'Restore'|i18n( 'extension/expwt' )}</span></th>{/if}</tr></thead>
<tbody>
{foreach $history as $h}
<tr>
<td class="a4-nowrap">{$h.time|wash}</td>
<td>{if $h.roles}{foreach $h.roles as $r}<span class="a4-tag" title="{$r.hidden|implode( ', ' )|wash}">{$r.name|wash}{if $r.hidden} · {'Hidden'|i18n( 'extension/expwt' )}: {$r.hidden|count}{/if}</span> {/foreach}{else}<span class="a4-muted">–</span>{/if}</td>
{if $can_edit}<td><button type="submit" name="Restore" value="{$h.stamp|wash}" class="a4-btn a4-btn-small">{'Restore'|i18n( 'extension/expwt' )}</button></td>{/if}
</tr>
{/foreach}
</tbody>
</table>
</div>
</form>
{else}
<p class="a4-empty">{'No earlier versions yet.'|i18n( 'extension/expwt' )}</p>
{/if}
</section>
{/if}

</div></div></div>
</div>
