<?php
/**
 * What other extensions add to the website sidebar (decision 32), registered in their own expwt.ini.append.php:
 *
 *   panel            a panel of its own (a tab beside Page, Create, Structure ...): its label, the script that draws it
 *                    (in the extension's design, javascript/), optionally a stylesheet for it (stylesheets/), where
 *                    its tab goes, and who sees it
 *   action           a server action of the extension, answered by its class through the sidebar's data layer
 *                    (expwt/api, /api/expwt/v1, GraphQL): the same checks, CSRF token and "Verify it's you" as the
 *                    sidebar's own actions; named <extension>.<name>
 *   highlight        a region of the page (a CSS selector) outlined while a panel is shown; a click on it tells the
 *                    panel which element was picked
 *   search provider  a source of results for the sidebar's search (decision 29): an action that answers a query
 *
 * Who sees an entry: the expwt function it names (Function, default use) AND every kernel policy of Policy[]
 * (module/function, e.g. setup/managecache): the sidebar never grants anything; an action's class checks the kernel's
 * policy of what it changes, as the sidebar's own actions do.
 *
 *   [RegistrySettings]
 *   PanelList[]=migrations
 *   ActionList[]=expmigrate.status
 *
 *   [Panel_migrations]
 *   Label=Migrations                       (the English source; translated in Context)
 *   Context=extension/expmigrate
 *   Script=expmigrate-expwt.js             (registers the panel: expwtRegister( 'panel', 'migrations', fn ))
 *   Stylesheet=expmigrate-expwt.css        (optional; inside the sidebar's Shadow DOM, the sidebar's tokens apply)
 *   Before=me                              (optional: the tab before this panel's; default: before Me)
 *   Function=admin                         (optional: the expwt function needed; default use)
 *   Policy[]=setup/system_info             (optional: kernel policies needed, all of them)
 *
 *   [Action_expmigrate.status]
 *   Class=expmigrateExpwtActions           (implements expwtActionHandler)
 *   Writes=false                           (true: POST only, CSRF token, never cached)
 *   Function=admin
 *   Policy[]=setup/system_info
 *   KernelView=setup/system_upgrade        (optional: the administration view it stands for, for "Verify it's you")
 *
 *   [Highlight_migrations_tables]
 *   Panel=migrations
 *   Selector=table.migration-status
 *   Label=Migration status
 *
 *   [SearchProvider_migrations]
 *   Label=Migrations
 *   Context=extension/expmigrate
 *   Action=expmigrate.search               (an action of the registry; input query, offset; answers items)
 *
 * Pure: plain arrays, tested without a database; fromIni() reads the settings.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

class expwtRegistry
{
    /** The panels of the sidebar itself: no extension takes their ids */
    const CORE_PANELS = array( 'welcome', 'page', 'create', 'structure', 'inbox', 'me' );

    /** kinds => the list setting of [RegistrySettings] and the prefix of their groups */
    const KINDS = array( 'panels' => array( 'PanelList', 'Panel_' ), 'actions' => array( 'ActionList', 'Action_' ),
                         'highlights' => array( 'HighlightList', 'Highlight_' ),
                         'search' => array( 'SearchProviderList', 'SearchProvider_' ) );

    /** @var array a registry of no entries */
    const EMPTY_REGISTRY = array( 'panels' => array(), 'actions' => array(), 'highlights' => array(), 'search' => array() );

    /**
     * The registry of the installation's settings (expwt.ini of every active extension).
     *
     * @param eZINI|null $ini
     * @return array
     */
    public static function fromIni( $ini = null )
    {
        $ini = $ini ?: eZINI::instance( 'expwt.ini' );
        $raw = array();
        foreach ( self::KINDS as $kind => $names )
        {
            $raw[$kind] = array();
            $ids = $ini->hasVariable( 'RegistrySettings', $names[0] ) ? (array)$ini->variable( 'RegistrySettings', $names[0] ) : array();
            foreach ( $ids as $id )
            {
                $group = $names[1] . $id;
                $raw[$kind][(string)$id] = $ini->hasGroup( $group ) ? (array)$ini->group( $group ) : null;
            }
        }
        return self::fromArray( $raw );
    }

    /**
     * A registry from plain arrays (kind => id => settings), every entry checked: an entry with a wrong id, without
     * what it needs, or taking a panel id of the sidebar is left out (and named in 'refused').
     *
     * @param array $raw
     * @return array panels, actions, highlights, search, refused
     */
    public static function fromArray( array $raw )
    {
        $out = self::EMPTY_REGISTRY + array( 'refused' => array() );
        $refuse = function ( $kind, $id, $why ) use ( &$out ) { $out['refused'][] = "$kind $id: $why"; };
        foreach ( isset( $raw['panels'] ) ? (array)$raw['panels'] : array() as $id => $s )
        {
            $id = (string)$id;
            if ( !preg_match( '/^[a-z][a-z0-9_]{0,39}$/', $id ) )
                $refuse( 'panel', $id, 'the id is not [a-z][a-z0-9_]' );
            else if ( in_array( $id, self::CORE_PANELS, true ) )
                $refuse( 'panel', $id, 'the id is a panel of the sidebar' );
            else if ( !is_array( $s ) || !self::file( self::get( $s, 'Script' ), 'js' ) || self::get( $s, 'Label' ) === '' )
                $refuse( 'panel', $id, 'Label and Script (a .js file name) are needed' );
            else
                $out['panels'][$id] = array( 'id' => $id, 'label' => self::get( $s, 'Label' ), 'context' => self::context( $s ),
                                             'script' => self::get( $s, 'Script' ),
                                             'stylesheet' => self::file( self::get( $s, 'Stylesheet' ), 'css' ) ? self::get( $s, 'Stylesheet' ) : '',
                                             'before' => in_array( self::get( $s, 'Before' ), self::CORE_PANELS, true ) ? self::get( $s, 'Before' ) : 'me' )
                                      + self::who( $s );
        }
        foreach ( isset( $raw['actions'] ) ? (array)$raw['actions'] : array() as $id => $s )
        {
            $id = (string)$id;
            if ( !self::isActionName( $id ) )
                $refuse( 'action', $id, 'the name is not <extension>.<name>' );
            else if ( expwtAccess::exists( $id ) )
                $refuse( 'action', $id, 'the name is an action of the sidebar' );
            else if ( !is_array( $s ) || !preg_match( '/^[A-Za-z_][A-Za-z0-9_]*$/', self::get( $s, 'Class' ) ) )
                $refuse( 'action', $id, 'Class is needed' );
            else
            {
                $view = explode( '/', self::get( $s, 'KernelView' ) );
                $out['actions'][$id] = array( 'id' => $id, 'class' => self::get( $s, 'Class' ),
                                              'writes' => in_array( strtolower( self::get( $s, 'Writes' ) ), array( 'true', 'enabled', '1', 'yes' ), true ),
                                              'kernelView' => count( $view ) === 2 && $view[0] !== '' && $view[1] !== '' ? array( 'module' => $view[0], 'view' => $view[1], 'post' => array() ) : null )
                                       + self::who( $s );
            }
        }
        foreach ( isset( $raw['highlights'] ) ? (array)$raw['highlights'] : array() as $id => $s )
        {
            $id = (string)$id;
            $panel = is_array( $s ) ? self::get( $s, 'Panel' ) : '';
            if ( !preg_match( '/^[a-z][a-z0-9_]{0,39}$/', $id ) )
                $refuse( 'highlight', $id, 'the id is not [a-z][a-z0-9_]' );
            else if ( !is_array( $s ) || self::get( $s, 'Selector' ) === '' || strpbrk( self::get( $s, 'Selector' ), '{};<>' ) !== false
                      || ( !isset( $out['panels'][$panel] ) && !in_array( $panel, self::CORE_PANELS, true ) ) )
                $refuse( 'highlight', $id, 'Selector (a CSS selector) and Panel (a registered panel) are needed' );
            else
                $out['highlights'][$id] = array( 'id' => $id, 'panel' => $panel, 'selector' => self::get( $s, 'Selector' ),
                                                 'label' => self::get( $s, 'Label' ), 'context' => self::context( $s ) ) + self::who( $s );
        }
        foreach ( isset( $raw['search'] ) ? (array)$raw['search'] : array() as $id => $s )
        {
            $id = (string)$id;
            if ( !preg_match( '/^[a-z][a-z0-9_]{0,39}$/', $id ) )
                $refuse( 'search provider', $id, 'the id is not [a-z][a-z0-9_]' );
            else if ( !is_array( $s ) || self::get( $s, 'Label' ) === '' || !isset( $out['actions'][self::get( $s, 'Action' )] ) )
                $refuse( 'search provider', $id, 'Label and Action (an action of the registry) are needed' );
            else
                $out['search'][$id] = array( 'id' => $id, 'label' => self::get( $s, 'Label' ), 'context' => self::context( $s ),
                                             'action' => self::get( $s, 'Action' ) ) + self::who( $s );
        }
        return $out;
    }

    /**
     * The entries a user may see: the expwt function of each (capabilities) and every kernel policy it names.
     * A highlight or search provider whose panel or action the user does not get is left out with it.
     *
     * @param array $registry of fromArray()
     * @param array $capabilities expwtAccess::capabilities()
     * @param callable $may function( string $module, string $function ): bool
     * @return array
     */
    public static function forUser( array $registry, array $capabilities, callable $may )
    {
        $allowed = function ( array $e ) use ( $capabilities, $may ) {
            if ( empty( $capabilities['use'] ) || empty( $capabilities[$e['function']] ) )
                return false;
            foreach ( $e['policies'] as $policy )
            {
                list( $module, $function ) = explode( '/', $policy, 2 );
                if ( !call_user_func( $may, $module, $function ) )
                    return false;
            }
            return true;
        };
        $out = self::EMPTY_REGISTRY;
        foreach ( array( 'panels', 'actions' ) as $kind )
            foreach ( $registry[$kind] as $id => $e )
                if ( $allowed( $e ) )
                    $out[$kind][$id] = $e;
        foreach ( $registry['highlights'] as $id => $e )
            if ( $allowed( $e ) && ( isset( $out['panels'][$e['panel']] ) || in_array( $e['panel'], self::CORE_PANELS, true ) ) )
                $out['highlights'][$id] = $e;
        foreach ( $registry['search'] as $id => $e )
            if ( $allowed( $e ) && isset( $out['actions'][$e['action']] ) )
                $out['search'][$id] = $e;
        return $out;
    }

    /**
     * What the boot answer tells the sidebar's script (no class names, no policies): panels with their label in the
     * user's language and the addresses of their script and stylesheet, highlights, search providers.
     *
     * @param array $registry of forUser()
     * @param callable $tr function( string $context, string $source ): string
     * @param callable $url function( string $type js|css, string $file ): string, the file's address in the design
     * @return array
     */
    public static function forBoot( array $registry, callable $tr, callable $url )
    {
        $out = array( 'panels' => array(), 'actions' => array(), 'highlights' => array(), 'search' => array() );
        // the client sends a writing action by POST with the CSRF token, a reading one by GET
        foreach ( $registry['actions'] as $e )
            $out['actions'][$e['id']] = array( 'writes' => $e['writes'] );
        foreach ( $registry['panels'] as $e )
            $out['panels'][] = array( 'id' => $e['id'], 'label' => call_user_func( $tr, $e['context'], $e['label'] ), 'before' => $e['before'],
                                      'script' => call_user_func( $url, 'js', $e['script'] ),
                                      'stylesheet' => $e['stylesheet'] !== '' ? call_user_func( $url, 'css', $e['stylesheet'] ) : '' );
        foreach ( $registry['highlights'] as $e )
            $out['highlights'][] = array( 'id' => $e['id'], 'panel' => $e['panel'], 'selector' => $e['selector'],
                                          'label' => $e['label'] !== '' ? call_user_func( $tr, $e['context'], $e['label'] ) : '' );
        foreach ( $registry['search'] as $e )
            $out['search'][] = array( 'id' => $e['id'], 'label' => call_user_func( $tr, $e['context'], $e['label'] ), 'action' => $e['action'] );
        return $out;
    }

    /** @return bool an action name of an extension: <extension>.<name> */
    public static function isActionName( $name )
    {
        return is_string( $name ) && (bool)preg_match( '/^[a-z][a-z0-9_]{0,39}\.[a-z][a-z0-9_.]{0,59}$/', $name );
    }

    /** @return array function (an expwt function, default use) and policies (module/function) of an entry */
    protected static function who( array $s )
    {
        $function = self::get( $s, 'Function' );
        $policies = array();
        foreach ( isset( $s['Policy'] ) ? (array)$s['Policy'] : array() as $policy )
            if ( is_string( $policy ) && preg_match( '#^[a-z][a-z0-9_]*/[a-z*][a-z0-9_]*$#i', trim( $policy ) ) )
                $policies[] = trim( $policy );
        return array( 'function' => in_array( $function, expwtAccess::FUNCTIONS, true ) ? $function : 'use', 'policies' => $policies );
    }

    protected static function get( array $s, $name )
    {
        return isset( $s[$name] ) && is_scalar( $s[$name] ) ? trim( (string)$s[$name] ) : '';
    }

    protected static function context( array $s )
    {
        $context = self::get( $s, 'Context' );
        return preg_match( '#^[A-Za-z0-9_/.-]{1,120}$#', $context ) ? $context : expwtStrings::CONTEXT;
    }

    /** @return bool a plain file name of the type (no directory: the design finds it) */
    protected static function file( $name, $extension )
    {
        return is_string( $name ) && (bool)preg_match( '/^[A-Za-z0-9][A-Za-z0-9_.-]{0,119}\.' . $extension . '$/', $name );
    }
}
