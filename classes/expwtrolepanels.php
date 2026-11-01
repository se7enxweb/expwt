<?php
/**
 * The order and visibility of the sidebar's panels per role and siteaccess (E9), set by administrators in Setup >
 * Website sidebar panels and kept in the siteaccess's expwt.ini.append.php (settings/siteaccess/<siteaccess>/),
 * written through the kernel's settings editor (expIniEditor: line by line, a backup of the file before every
 * write, the audit's system.setting.write):
 *
 *   [RolePanelSettings]
 *   RoleList[]                   the roles with panels of their own, first the one that decides the order
 *   RoleList[]=3
 *   RoleList[]=1
 *
 *   [RolePanels_3]               one group per role of RoleList[] (the role's id)
 *   PanelOrder[]                 the panels in this order; panels not named follow in the sidebar's own order
 *   PanelOrder[]=page
 *   PanelOrder[]=calendar
 *   HiddenPanelList[]            panels the role does not see
 *   HiddenPanelList[]=forms
 *
 * For a user: the order of the first role of RoleList[] the user has; a panel is hidden only when every role of
 * RoleList[] the user has hides it (a role that shows it wins: these settings never take away what another role of
 * the same user shows). Page and Me are never hidden. Hiding is the sidebar's look only: what a user may do stays
 * with the policies (a hidden panel's actions still answer to those allowed).
 *
 * Earlier versions of the file are the editor's backups (var/backup/ini/<time>/<file>); history() lists them and
 * restore() puts the role settings of one back (the rest of the file untouched, with a backup of its own first).
 *
 * The rules are pure (plain arrays, tested without a database); the reading and writing of files are the static
 * methods at the end.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

class expwtRolePanels
{
    const BLOCK = 'RolePanelSettings';
    const GROUP = 'RolePanels_';
    /** Never hidden: Page is where editing on the page lands, Me holds the preferences and signing out */
    const ALWAYS_SHOWN = array( 'page', 'me' );
    /** At most this many backups are listed in the history */
    const HISTORY = 20;

    // ------------------------------------------------------------------ the rules (pure)

    /** @return bool a panel id: the sidebar's own or a registered one */
    public static function isPanelId( $id )
    {
        return is_string( $id ) && (bool)preg_match( '/^[a-z][a-z0-9_]{0,39}$/', $id );
    }

    /**
     * The settings of one role, cleaned: panel ids only, each once; Page and Me never hidden.
     *
     * @param mixed $order PanelOrder[]
     * @param mixed $hidden HiddenPanelList[]
     * @return array order, hidden
     */
    public static function role( $order, $hidden )
    {
        $clean = function ( $list ) {
            $out = array();
            foreach ( is_array( $list ) ? $list : array() as $id )
            {
                $id = is_scalar( $id ) ? trim( (string)$id ) : '';
                if ( self::isPanelId( $id ) && !in_array( $id, $out, true ) )
                    $out[] = $id;
            }
            return $out;
        };
        return array( 'order' => $clean( $order ), 'hidden' => array_values( array_diff( $clean( $hidden ), self::ALWAYS_SHOWN ) ) );
    }

    /**
     * The panel rule of a user.
     *
     * @param array $roles role id => role() of RoleList[], in its order
     * @param int[] $userRoleIds the roles the user has (directly or through a group)
     * @return array|null order, hidden; null when no role of RoleList[] is the user's (the sidebar's own order)
     */
    public static function forUser( array $roles, array $userRoleIds )
    {
        $userRoleIds = array_map( 'intval', $userRoleIds );
        $order = null;
        $hidden = null;
        foreach ( $roles as $roleId => $rule )
        {
            if ( !in_array( (int)$roleId, $userRoleIds, true ) )
                continue;
            if ( $order === null )
                $order = $rule['order'];
            $hidden = $hidden === null ? $rule['hidden'] : array_values( array_intersect( $hidden, $rule['hidden'] ) );
        }
        return $order === null ? null : array( 'order' => $order, 'hidden' => $hidden );
    }

    /**
     * The panels in a rule's order, without the hidden ones: the panels the rule orders first, in its order, then the
     * others in the order given. Never empty when the list was not.
     *
     * @param string[] $ids the panels the user gets, in the sidebar's own order
     * @param array|null $rule forUser()
     * @return string[]
     */
    public static function apply( array $ids, $rule )
    {
        if ( !is_array( $rule ) )
            return array_values( $ids );
        $shown = array();
        foreach ( $ids as $id )
            if ( !in_array( $id, $rule['hidden'], true ) || in_array( $id, self::ALWAYS_SHOWN, true ) )
                $shown[] = $id;
        if ( !$shown )
            $shown = array_values( $ids );
        $first = array_values( array_intersect( $rule['order'], $shown ) );
        return array_merge( $first, array_values( array_diff( $shown, $first ) ) );
    }

    /**
     * What the Setup screen shows for a role: every panel in the role's order with whether it is shown.
     *
     * @param string[] $ids every panel there is, in the sidebar's own order
     * @param array|null $rule role()
     * @return array[] id, shown, fixed (never hidden)
     */
    public static function rows( array $ids, $rule )
    {
        $rule = is_array( $rule ) ? $rule : array( 'order' => array(), 'hidden' => array() );
        $first = array_values( array_intersect( $rule['order'], $ids ) );
        $rows = array();
        foreach ( array_merge( $first, array_values( array_diff( $ids, $first ) ) ) as $id )
        {
            $fixed = in_array( $id, self::ALWAYS_SHOWN, true );
            $rows[] = array( 'id' => $id, 'shown' => $fixed || !in_array( $id, $rule['hidden'], true ), 'fixed' => $fixed );
        }
        return $rows;
    }

    /**
     * A list with one item moved one place up (-1) or down (+1); unchanged at the ends or for an item not in it.
     *
     * @param array $list
     * @param mixed $item
     * @param int $by -1 or 1
     * @return array
     */
    public static function move( array $list, $item, $by )
    {
        $list = array_values( $list );
        $at = array_search( $item, $list, true );
        $to = $at === false ? false : $at + ( $by < 0 ? -1 : 1 );
        if ( $to === false || $to < 0 || $to >= count( $list ) )
            return $list;
        $list[$at] = $list[$to];
        $list[$to] = $item;
        return $list;
    }

    /**
     * The role settings of an INI file's values (as expIniWriter::values() reads one file, or eZINI's groups).
     *
     * @param array $values block => variable => value
     * @return array role id => role(), in the order of RoleList[] (a role without its group: shown as the sidebar has it)
     */
    public static function fromValues( array $values )
    {
        $list = isset( $values[self::BLOCK]['RoleList'] ) && is_array( $values[self::BLOCK]['RoleList'] ) ? $values[self::BLOCK]['RoleList'] : array();
        $roles = array();
        foreach ( $list as $roleId )
        {
            if ( !is_scalar( $roleId ) || !ctype_digit( trim( (string)$roleId ) ) || (int)$roleId <= 0 || isset( $roles[(int)$roleId] ) )
                continue;
            $group = isset( $values[self::GROUP . (int)$roleId] ) && is_array( $values[self::GROUP . (int)$roleId] ) ? $values[self::GROUP . (int)$roleId] : array();
            $roles[(int)$roleId] = self::role( isset( $group['PanelOrder'] ) ? $group['PanelOrder'] : array(),
                                               isset( $group['HiddenPanelList'] ) ? $group['HiddenPanelList'] : array() );
        }
        return $roles;
    }

    /**
     * The backup directories of a file, newest first: <backupRoot>/<Ymd-His>[-N]/<relative path>.
     *
     * @param string[] $paths the backups found (absolute)
     * @param string $backupRoot
     * @param string $relativePath
     * @return array[] path, stamp (Ymd-His[-N]), time (Unix time of the stamp)
     */
    public static function sortBackups( array $paths, $backupRoot, $relativePath )
    {
        $backupRoot = rtrim( $backupRoot, '/' ) . '/';
        $out = array();
        foreach ( $paths as $path )
        {
            if ( strpos( $path, $backupRoot ) !== 0 || substr( $path, -strlen( '/' . $relativePath ) ) !== '/' . $relativePath )
                continue;
            $stamp = substr( $path, strlen( $backupRoot ), -strlen( '/' . $relativePath ) );
            if ( !preg_match( '/^(\d{8}-\d{6})(?:-(\d+))?$/', $stamp, $m ) )
                continue;
            $time = DateTime::createFromFormat( 'Ymd-His', $m[1] );
            $out[] = array( 'path' => $path, 'stamp' => $stamp, 'time' => $time ? $time->getTimestamp() : 0,
                            'n' => isset( $m[2] ) ? (int)$m[2] : 1 );
        }
        usort( $out, function ( $a, $b ) {
            return $a['time'] !== $b['time'] ? $b['time'] - $a['time'] : $b['n'] - $a['n'];
        } );
        return $out;
    }

    /**
     * Writes the role settings into an editor: RoleList[] and one group per role, the groups of roles no longer in
     * the list removed. Each array starts with its reset line (Var[]), so the files read before this one do not add
     * to it.
     *
     * @param expIniEditor $editor
     * @param array $roles role id => role(), in the order of RoleList[]
     * @return expIniEditor
     */
    public static function write( expIniEditor $editor, array $roles )
    {
        foreach ( $editor->blocks() as $block )
            if ( strpos( $block, self::GROUP ) === 0 && !isset( $roles[(int)substr( $block, strlen( self::GROUP ) )] ) )
                $editor->removeBlock( $block );
        if ( !$roles )
        {
            if ( in_array( self::BLOCK, $editor->blocks(), true ) )
                $editor->removeBlock( self::BLOCK );
            return $editor;
        }
        $editor->clearArray( self::BLOCK, 'RoleList' );
        foreach ( array_keys( $roles ) as $roleId )
            $editor->add( self::BLOCK, 'RoleList', (string)(int)$roleId );
        foreach ( $roles as $roleId => $rule )
        {
            $group = self::GROUP . (int)$roleId;
            foreach ( array( 'PanelOrder' => $rule['order'], 'HiddenPanelList' => $rule['hidden'] ) as $variable => $list )
            {
                $editor->clearArray( $group, $variable );
                foreach ( $list as $id )
                    $editor->add( $group, $variable, $id );
            }
        }
        return $editor;
    }

    // ------------------------------------------------------------------ the installation

    /**
     * The panel rule of the current user on this siteaccess (the boot answer): null when no role of RoleList[] is
     * the user's.
     *
     * @param eZUser $user
     * @param eZINI|null $ini expwt.ini of this siteaccess
     * @return array|null order, hidden
     */
    public static function forCurrentUser( $user, $ini = null )
    {
        $ini = $ini ?: eZINI::instance( 'expwt.ini' );
        if ( !$ini->hasVariable( self::BLOCK, 'RoleList' ) )
            return null;
        $values = array( self::BLOCK => array( 'RoleList' => (array)$ini->variable( self::BLOCK, 'RoleList' ) ) );
        foreach ( (array)$ini->variable( self::BLOCK, 'RoleList' ) as $roleId )
            if ( is_scalar( $roleId ) && $ini->hasGroup( self::GROUP . (int)$roleId ) )
                $values[self::GROUP . (int)$roleId] = (array)$ini->group( self::GROUP . (int)$roleId );
        $roles = self::fromValues( $values );
        if ( !$roles )
            return null;
        try
        {
            $ids = (array)$user->roleIDList();
        }
        catch ( Throwable $e )
        {
            eZDebug::writeError( get_class( $e ) . ': ' . $e->getMessage(), __METHOD__ );
            return null;
        }
        return self::forUser( $roles, $ids );
    }

    /**
     * The editor of a siteaccess's expwt.ini.append.php.
     *
     * @param string $siteAccess
     * @return expIniEditor
     * @throws expIniException for an unknown siteaccess
     */
    public static function editor( $siteAccess )
    {
        return new expIniEditor( expIniEditor::scope( 'siteaccess:' . $siteAccess ), 'expwt' );
    }

    /** @return string the backup root of the kernel's settings editor */
    public static function backupRoot()
    {
        return expIniEditor::root() . 'var/backup/ini';
    }

    /**
     * The earlier versions of a siteaccess's file (the editor's backups), newest first, each with its role settings.
     *
     * @param expIniEditor $editor
     * @return array[] stamp, time, roles (fromValues())
     */
    public static function history( expIniEditor $editor )
    {
        $relative = $editor->relativePath();
        $found = glob( self::backupRoot() . '/*/' . $relative, GLOB_NOSORT );
        $out = array();
        foreach ( array_slice( self::sortBackups( $found ?: array(), self::backupRoot(), $relative ), 0, self::HISTORY ) as $b )
        {
            try
            {
                $roles = self::fromValues( expIniWriter::fromFile( $b['path'] )->values() );
            }
            catch ( Throwable $e )
            {
                continue;
            }
            $out[] = array( 'stamp' => $b['stamp'], 'time' => $b['time'], 'roles' => $roles );
        }
        return $out;
    }

    /**
     * The role settings of one backup of a siteaccess's file.
     *
     * @param expIniEditor $editor
     * @param string $stamp Ymd-His[-N]
     * @return array|null roles (fromValues()); null when there is no such backup
     */
    public static function backupRoles( expIniEditor $editor, $stamp )
    {
        if ( !is_string( $stamp ) || !preg_match( '/^\d{8}-\d{6}(?:-\d+)?$/', $stamp ) )
            return null;
        $path = self::backupRoot() . '/' . $stamp . '/' . $editor->relativePath();
        if ( !is_file( $path ) )
            return null;
        return self::fromValues( expIniWriter::fromFile( $path )->values() );
    }
}
