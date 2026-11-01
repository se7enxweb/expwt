<?php
/**
 * expwt/panels/<siteaccess>/<role id>: Setup > Website sidebar panels (E9). Which panels of the sidebar the users of
 * a role see, and in which order, per siteaccess; written to settings/siteaccess/<siteaccess>/expwt.ini.append.php
 * by the kernel's settings editor (a backup before every write, the audit's system.setting.write), with the
 * earlier versions of the file to restore (expwtRolePanels).
 *
 * Needs expwt/admin (the view's function) and the kernel's settings module: settings/view to see it, settings/edit
 * to change it (the policies of the settings pages, which change the same files). POST only for changes; the form
 * token is ezformtoken's.
 *
 *   Order[]=<panel>, Shown[<panel>]=1       the panels of the role as the form has them
 *   MoveUp=<panel> | MoveDown=<panel>        one place up or down (not saved yet)
 *   SaveButton                               the role's panels saved
 *   RemoveButton                             the role's own panels removed (the sidebar's own order again)
 *   RoleUp=<role> | RoleDown=<role>          the role one place up or down in RoleList[] (saved)
 *   Restore=<Ymd-His[-N]>                    the role settings of that backup put back (saved)
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

/** @var eZModule $Module */
$Module = $Params['Module'];
$http = eZHTTPTool::instance();
$user = eZUser::currentUser();
$may = function ( $function ) use ( $user ) {
    $access = $user->hasAccessTo( 'settings', $function );
    return isset( $access['accessWord'] ) && $access['accessWord'] !== 'no';
};
if ( !$may( 'view' ) )
    return $Module->handleError( eZError::KERNEL_ACCESS_DENIED, 'kernel' );
$canEdit = $may( 'edit' );
$t = function ( $text, array $args = array() ) { return ezpI18n::tr( 'extension/expwt', $text, null, $args ); };

// the siteaccesses of the site (never an administration), each with whether the sidebar is on there (kept in the
// cache while no site.ini or expwt.ini changes: reading every siteaccess's settings takes seconds)
$siteAccesses = expwtRolePanels::siteAccesses();
ksort( $siteAccesses );

// the roles of the installation
$roleNames = array();
foreach ( (array)eZRole::fetchList() as $role )
    $roleNames[(int)$role->attribute( 'id' )] = (string)$role->attribute( 'name' );
asort( $roleNames, SORT_NATURAL | SORT_FLAG_CASE );

// a choice made in the form: to its address
if ( $http->hasPostVariable( 'ChooseButton' ) )
{
    $sa = $http->hasPostVariable( 'SiteAccess' ) ? (string)$http->postVariable( 'SiteAccess' ) : '';
    $role = $http->hasPostVariable( 'RoleID' ) ? (int)$http->postVariable( 'RoleID' ) : 0;
    return $Module->redirectTo( '/expwt/panels' . ( isset( $siteAccesses[$sa] ) ? '/' . $sa . ( isset( $roleNames[$role] ) ? '/' . $role : '' ) : '' ) );
}

$siteAccess = isset( $Params['SiteAccess'] ) && isset( $siteAccesses[(string)$Params['SiteAccess']] ) ? (string)$Params['SiteAccess'] : null;
if ( $siteAccess === null && $siteAccesses )
{
    // the first siteaccess with the sidebar on, else the first
    foreach ( $siteAccesses as $name => $s )
        if ( $s['on'] )
            return $Module->redirectTo( '/expwt/panels/' . $name );
    return $Module->redirectTo( '/expwt/panels/' . key( $siteAccesses ) );
}
$roleId = isset( $Params['RoleID'] ) && isset( $roleNames[(int)$Params['RoleID']] ) ? (int)$Params['RoleID'] : null;
$here = '/expwt/panels' . ( $siteAccess !== null ? '/' . $siteAccess . ( $roleId !== null ? '/' . $roleId : '' ) : '' );
// expwt.ini as the chosen siteaccess reads it (every settings file), only for that one
$siteIni = $siteAccess !== null ? expSettingsPage::chainFor( 'expwt.ini', $siteAccess )['ini'] : null;

// every panel there is, in the sidebar's own order (the sidebar's own, then the registered ones before the panel
// their registration names), with its label
$labels = array( 'page' => 'Page', 'create' => 'Create', 'structure' => 'Structure', 'seo' => 'SEO', 'sites' => 'Sites',
                 'forms' => 'Forms', 'newsletter' => 'Newsletter', 'urls' => 'URLs', 'calendar' => 'Calendar',
                 'insights' => 'Insights', 'inbox' => 'Inbox', 'migrations' => 'Migrations', 'me' => 'Me' );
$panelLabels = array();
foreach ( expwtState::PANELS as $id )
    $panelLabels[$id] = $t( isset( $labels[$id] ) ? $labels[$id] : $id );
$registry = $siteAccess !== null ? expwtRegistry::fromIni( $siteIni ) : expwtRegistry::EMPTY_REGISTRY;
foreach ( $registry['panels'] as $p )
{
    $ids = array_keys( $panelLabels );
    $at = array_search( $p['before'], $ids, true );
    $entry = array( $p['id'] => ezpI18n::tr( $p['context'], $p['label'] ) );
    $panelLabels = $at === false ? $panelLabels + $entry
                                 : array_slice( $panelLabels, 0, $at, true ) + $entry + array_slice( $panelLabels, $at, null, true );
}
$panelIds = array_keys( $panelLabels );

$flash = null;
$pending = null;
$roles = array();
$editor = null;
$history = array();
$decidedElsewhere = false;
if ( $siteAccess !== null )
{
    try
    {
        $editor = expwtRolePanels::editor( $siteAccess );
        $values = array();
        foreach ( $editor->blocks() as $block )
            $values[$block] = $editor->variables( $block );
        $roles = expwtRolePanels::fromValues( $values );
        // what the siteaccess reads in the end (every settings file): another file decides when it differs
        $effective = array();
        $ini = $siteIni;
        if ( $ini->hasGroup( expwtRolePanels::BLOCK ) )
            $effective[expwtRolePanels::BLOCK] = (array)$ini->group( expwtRolePanels::BLOCK );
        foreach ( isset( $effective[expwtRolePanels::BLOCK]['RoleList'] ) ? (array)$effective[expwtRolePanels::BLOCK]['RoleList'] : array() as $id )
            if ( is_scalar( $id ) && $ini->hasGroup( expwtRolePanels::GROUP . (int)$id ) )
                $effective[expwtRolePanels::GROUP . (int)$id] = (array)$ini->group( expwtRolePanels::GROUP . (int)$id );
        $decidedElsewhere = expwtRolePanels::fromValues( $effective ) !== $roles;
    }
    catch ( Throwable $e )
    {
        eZDebug::writeError( get_class( $e ) . ': ' . $e->getMessage(), __METHOD__ );
        $flash = array( 'kind' => 'bad', 'message' => $t( 'Not saved: %message', array( '%message' => $e->getMessage() ) ) );
    }
}

// the changes (POST, settings/edit)
$save = function ( array $newRoles, $message ) use ( $editor, $siteAccess, $t, $Module, $here, $http ) {
    try
    {
        expwtRolePanels::write( $editor, $newRoles );
        if ( !$editor->hasChanges() )
            $flash = array( 'kind' => 'info', 'message' => $t( 'Nothing changed.' ) );
        else
        {
            $result = $editor->save();
            expSettingsPage::clearIniCache();
            $flash = array( 'kind' => 'ok', 'message' => $message . ' ' . $t( 'Saved to %file.', array( '%file' => $result->relativePath() ) ) );
        }
    }
    catch ( Throwable $e )
    {
        eZDebug::writeError( get_class( $e ) . ': ' . $e->getMessage(), __METHOD__ );
        $flash = array( 'kind' => 'bad', 'message' => $t( 'Not saved: %message', array( '%message' => $e->getMessage() ) ) );
    }
    $http->setSessionVariable( 'expwtPanelsFlash', $flash );
    return $Module->redirectTo( $here );
};
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && $editor !== null && $flash === null )
{
    if ( !$canEdit )
        return $Module->handleError( eZError::KERNEL_ACCESS_DENIED, 'kernel' );
    if ( $http->hasPostVariable( 'Restore' ) )
    {
        $restored = expwtRolePanels::backupRoles( $editor, (string)$http->postVariable( 'Restore' ) );
        if ( $restored === null )
            return $Module->handleError( eZError::KERNEL_NOT_FOUND, 'kernel' );
        $stamp = (string)$http->postVariable( 'Restore' );
        $time = DateTime::createFromFormat( 'Ymd-His', substr( $stamp, 0, 15 ) );
        return $save( $restored, $t( 'Restored the panels of the version of %time.',
                                     array( '%time' => $time ? eZLocale::instance()->formatShortDateTime( $time->getTimestamp() ) : $stamp ) ) );
    }
    foreach ( array( 'RoleUp' => -1, 'RoleDown' => 1 ) as $button => $by )
    {
        if ( $http->hasPostVariable( $button ) )
        {
            $order = expwtRolePanels::move( array_keys( $roles ), (int)$http->postVariable( $button ), $by );
            $moved = array();
            foreach ( $order as $id )
                $moved[$id] = $roles[$id];
            return $save( $moved, '' );
        }
    }
    if ( $roleId !== null )
    {
        if ( $http->hasPostVariable( 'RemoveButton' ) )
        {
            unset( $roles[$roleId] );
            return $save( $roles, '' );
        }
        // the role's panels as the form has them
        $order = array();
        foreach ( $http->hasPostVariable( 'Order' ) ? (array)$http->postVariable( 'Order' ) : array() as $id )
            if ( in_array( $id, $panelIds, true ) && !in_array( $id, $order, true ) )
                $order[] = $id;
        $order = array_merge( $order, array_values( array_diff( $panelIds, $order ) ) );
        $shown = $http->hasPostVariable( 'Shown' ) ? array_keys( (array)$http->postVariable( 'Shown' ) ) : array();
        $rule = expwtRolePanels::role( $order, array_diff( $panelIds, $shown ) );
        foreach ( array( 'MoveUp' => -1, 'MoveDown' => 1 ) as $button => $by )
            if ( $http->hasPostVariable( $button ) )
                $rule['order'] = expwtRolePanels::move( $rule['order'], (string)$http->postVariable( $button ), $by );
        if ( $http->hasPostVariable( 'SaveButton' ) )
        {
            $roles[$roleId] = $rule;
            return $save( $roles, '' );
        }
        $pending = $rule;
    }
}

if ( $flash === null && $http->hasSessionVariable( 'expwtPanelsFlash' ) )
{
    $flash = $http->sessionVariable( 'expwtPanelsFlash' );
    $http->removeSessionVariable( 'expwtPanelsFlash' );
}
if ( $editor !== null )
{
    $locale = eZLocale::instance();
    foreach ( expwtRolePanels::history( $editor ) as $h )
    {
        $summary = array();
        foreach ( $h['roles'] as $id => $rule )
            $summary[] = array( 'name' => isset( $roleNames[$id] ) ? $roleNames[$id] : '#' . $id,
                                'hidden' => array_map( function ( $p ) use ( $panelLabels ) { return isset( $panelLabels[$p] ) ? $panelLabels[$p] : $p; }, $rule['hidden'] ) );
        $history[] = array( 'stamp' => $h['stamp'], 'time' => $locale->formatShortDateTime( $h['time'] ), 'roles' => $summary );
    }
}

$configured = array();
foreach ( $roles as $id => $rule )
    $configured[] = array( 'id' => $id, 'name' => isset( $roleNames[$id] ) ? $roleNames[$id] : '#' . $id,
                           'hidden' => count( $rule['hidden'] ) );
$rows = array();
if ( $roleId !== null )
{
    foreach ( expwtRolePanels::rows( $panelIds, $pending !== null ? $pending : ( isset( $roles[$roleId] ) ? $roles[$roleId] : null ) ) as $r )
        $rows[] = $r + array( 'label' => $panelLabels[$r['id']] );
}

$tpl = eZTemplate::factory();
$tpl->setVariable( 'siteaccesses', array_values( array_map( function ( $s ) { return array( 'name' => $s['name'], 'on' => $s['on'] ); }, $siteAccesses ) ) );
$tpl->setVariable( 'siteaccess', $siteAccess );
$tpl->setVariable( 'roles', array_map( function ( $id, $name ) { return array( 'id' => $id, 'name' => $name ); }, array_keys( $roleNames ), $roleNames ) );
$tpl->setVariable( 'role_id', $roleId );
$tpl->setVariable( 'role_name', $roleId !== null ? $roleNames[$roleId] : '' );
$tpl->setVariable( 'role_configured', $roleId !== null && isset( $roles[$roleId] ) );
$tpl->setVariable( 'configured', $configured );
$tpl->setVariable( 'rows', $rows );
$tpl->setVariable( 'pending', $pending !== null );
$tpl->setVariable( 'can_edit', $canEdit );
$tpl->setVariable( 'flash', $flash );
$tpl->setVariable( 'file', $editor !== null ? $editor->relativePath() : '' );
$tpl->setVariable( 'history', $history );
$tpl->setVariable( 'decided_elsewhere', $decidedElsewhere );
$tpl->setVariable( 'here', $here );

$Result = array(
    'content' => $tpl->fetch( 'design:expwt/panels.tpl' ),
    'path' => array( array( 'url' => 'setup/cache', 'text' => ezpI18n::tr( 'design/admin/parts/setup/menu', 'Setup' ) ),
                     array( 'url' => false, 'text' => $t( 'Website sidebar panels' ) ) ),
);
