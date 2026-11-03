<?php
/**
 * What extensions register for the website sidebar (decision 32): expwtRegistry and its actions through expwtApi.
 * No database: the gateway of expwtApiTest answers from arrays.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

require_once __DIR__ . '/expwtApiTest.php';

/** An extension's action handler for expwtRegistryApiTest: answers its input, refuses "no" */
class expwtTestMigrationsActions implements expwtActionHandler
{
    public function run( $action, array $input, expwtGateway $gateway )
    {
        if ( isset( $input['say'] ) && $input['say'] === 'no' )
            throw new expwtApiError( 409, 'busy', 'A migration is running.' );
        return array( 'action' => $action, 'input' => $input );
    }
}

/**
 * What extensions register (decision 32) through expwtApi: their actions with the same checks in the same order as
 * the sidebar's own, the boot answer's registry, and the registry's refusals of wrong entries.
 */
class expwtRegistryApiTest extends PHPUnit\Framework\TestCase
{
    private $gateway;

    private static function raw()
    {
        return array(
            'panels' => array( 'deploys' => array( 'Label' => 'Deploys', 'Context' => 'extension/expmigrate', 'Script' => 'expmigrate-expwt.js',
                                                      'Stylesheet' => 'expmigrate-expwt.css', 'Before' => 'me', 'Function' => 'admin', 'Policy' => array( 'setup/system_info' ) ),
                               'page' => array( 'Label' => 'Mine', 'Script' => 'x.js' ),
                               'nolabel' => array( 'Script' => 'x.js' ),
                               'badscript' => array( 'Label' => 'X', 'Script' => '../../etc/passwd' ) ),
            'actions' => array( 'expmigrate.status' => array( 'Class' => 'expwtTestMigrationsActions', 'Function' => 'admin', 'Policy' => array( 'setup/system_info' ) ),
                                'expmigrate.run' => array( 'Class' => 'expwtTestMigrationsActions', 'Writes' => 'true', 'Function' => 'admin',
                                                           'Policy' => array( 'setup/system_info' ), 'KernelView' => 'setup/system_upgrade' ),
                                'expmigrate.broken' => array( 'Class' => 'NoSuchClassAnywhere' ),
                                'draft.save' => array( 'Class' => 'expwtTestMigrationsActions' ),
                                'nodot' => array( 'Class' => 'expwtTestMigrationsActions' ) ),
            'highlights' => array( 'migration_tables' => array( 'Panel' => 'deploys', 'Selector' => 'table.migration-status', 'Label' => 'Migration status' ),
                                   'evil' => array( 'Panel' => 'deploys', 'Selector' => 'x}body{display:none' ),
                                   'orphan' => array( 'Panel' => 'nowhere', 'Selector' => 'p' ) ),
            'search' => array( 'deploys' => array( 'Label' => 'Deploys', 'Action' => 'expmigrate.status', 'Function' => 'admin' ),
                               'lost' => array( 'Label' => 'Lost', 'Action' => 'expmigrate.none' ) ),
        );
    }

    private function api( array $policies = array( 'setup/system_info' ), $caps = null )
    {
        $settings = expwtSettings::fromArray( array() );
        $caps = $caps === null ? array_fill_keys( expwtAccess::FUNCTIONS, true ) : $caps;
        $this->gateway = new expwtApiTestGateway( $settings, $caps );
        $this->gateway->registered = expwtRegistry::fromArray( self::raw() );
        $this->gateway->policies = $policies;
        return new expwtApi( $this->gateway, $settings );
    }

    /** The worked example of the guide (tests/example/expwtexample) registers every kind, none refused */
    public function testTheWorkedExampleRegistersCleanly()
    {
        $groups = array();
        $group = null;
        foreach ( file( dirname( __DIR__, 2 ) . '/example/expwtexample/settings/expwt.ini.append.php' ) as $line )
        {
            $line = trim( $line );
            if ( preg_match( '/^\[(.+)\]$/', $line, $m ) )
                $groups[$group = $m[1]] = array();
            else if ( $group !== null && preg_match( '/^([A-Za-z]+)(\[\])?=(.*)$/', $line, $m ) )
            {
                if ( $m[2] )
                    $groups[$group][$m[1]][] = $m[3];
                else
                    $groups[$group][$m[1]] = $m[3];
            }
        }
        $raw = array();
        foreach ( expwtRegistry::KINDS as $kind => $names )
            foreach ( isset( $groups['RegistrySettings'][$names[0]] ) ? $groups['RegistrySettings'][$names[0]] : array() as $id )
                $raw[$kind][$id] = isset( $groups[$names[1] . $id] ) ? $groups[$names[1] . $id] : null;
        $r = expwtRegistry::fromArray( $raw );
        $this->assertSame( array(), $r['refused'] );
        $this->assertSame( array( 'pagecache' ), array_keys( $r['panels'] ) );
        $this->assertSame( array( 'expwtexample.info', 'expwtexample.clearcache', 'expwtexample.search' ), array_keys( $r['actions'] ) );
        $this->assertTrue( $r['actions']['expwtexample.clearcache']['writes'] );
        $this->assertSame( array( 'setup/managecache' ), $r['panels']['pagecache']['policies'] );
        $this->assertCount( 1, $r['highlights'] );
        $this->assertCount( 1, $r['search'] );
        $js = (string)file_get_contents( dirname( __DIR__, 2 ) . '/example/expwtexample/design/standard/javascript/expwtexample-expwt.js' );
        $this->assertStringContainsString( "window.expwtRegister('panel', 'pagecache'", $js );
    }

    public function testWrongEntriesAreLeftOutAndNamed()
    {
        $r = expwtRegistry::fromArray( self::raw() );
        $this->assertSame( array( 'deploys' ), array_keys( $r['panels'] ) );
        $this->assertSame( array( 'expmigrate.status', 'expmigrate.run', 'expmigrate.broken' ), array_keys( $r['actions'] ), 'a name of the sidebar or without a dot is refused' );
        $this->assertSame( array( 'migration_tables' ), array_keys( $r['highlights'] ), 'no selector that breaks out of a rule, no unknown panel' );
        $this->assertSame( array( 'deploys' ), array_keys( $r['search'] ) );
        $this->assertCount( 8, $r['refused'] );
        $this->assertSame( array( 'module' => 'setup', 'view' => 'system_upgrade', 'post' => array() ), $r['actions']['expmigrate.run']['kernelView'] );
        $this->assertTrue( $r['actions']['expmigrate.run']['writes'] );
        $this->assertFalse( $r['actions']['expmigrate.status']['writes'] );
        $this->assertSame( 'use', $r['actions']['expmigrate.broken']['function'], 'Function defaults to use' );
    }

    public function testARegisteredActionIsAnsweredByItsClass()
    {
        $r = $this->api()->call( 'expmigrate.status', array( 'all' => 1 ), 'GET' );
        $this->assertSame( 200, $r['status'] );
        $this->assertSame( array( 'action' => 'expmigrate.status', 'input' => array( 'all' => 1 ) ), $r['body']['data'] );
        $r = $this->api()->call( 'expmigrate.status', array( 'say' => 'no' ), 'GET' );
        $this->assertSame( array( 409, 'busy' ), array( $r['status'], $r['body']['error'] ), 'a refusal of the class keeps its status' );
    }

    public function testRegisteredActionsAreCheckedAsTheSidebarsOwn()
    {
        $this->assertSame( 404, $this->api()->call( 'expmigrate.nothing', array() )['status'] );
        $this->assertSame( 404, $this->api()->call( 'nodot', array() )['status'] );
        $this->assertSame( 405, $this->api()->call( 'expmigrate.run', array(), 'GET' )['status'], 'a writing action only by POST' );
        $this->assertSame( 200, $this->api()->call( 'expmigrate.run', array(), 'POST' )['status'] );
        $this->assertSame( 403, $this->api( array() )->call( 'expmigrate.status', array(), 'GET' )['status'], 'without the kernel policy' );
        $caps = array_fill_keys( expwtAccess::FUNCTIONS, true );
        $caps['admin'] = false;
        $this->assertSame( 403, $this->api( array( 'setup/system_info' ), $caps )->call( 'expmigrate.status', array(), 'GET' )['status'], 'without expwt/admin' );
        $r = $this->api()->call( 'expmigrate.broken', array(), 'GET' );
        $this->assertSame( array( 500, 'internal' ), array( $r['status'], $r['body']['error'] ), 'a class that is no handler' );
    }

    public function testBootTellsWhatTheUserGets()
    {
        $d = $this->api()->call( 'boot', array(), 'GET' )['body']['data']['registry'];
        $this->assertSame( array( array( 'id' => 'deploys', 'label' => 'Deploys', 'before' => 'me', 'icon' => '', 'script' => '/packed/expmigrate-expwt.js',
                                         'stylesheet' => '/packed/expmigrate-expwt.css' ) ), $d['panels'] );
        $this->assertSame( array( 'expmigrate.status' => array( 'writes' => false ), 'expmigrate.run' => array( 'writes' => true ),
                                  'expmigrate.broken' => array( 'writes' => false ) ), $d['actions'] );
        $this->assertSame( 'table.migration-status', $d['highlights'][0]['selector'] );
        $this->assertSame( 'expmigrate.status', $d['search'][0]['action'] );
        $none = $this->api( array() )->call( 'boot', array(), 'GET' )['body']['data']['registry'];
        $this->assertSame( array(), $none['panels'], 'without the policy: no panel, its highlights and search provider go with it' );
        $this->assertSame( array(), $none['highlights'] );
        $this->assertSame( array(), $none['search'] );
        $this->assertSame( array( 'expmigrate.broken' ), array_keys( $none['actions'] ) );
    }


    /** A root with the kernel's Bootstrap Icons holding only cart.svg, for the icons of extension panels */
    private static function iconRoot()
    {
        $root = sys_get_temp_dir() . '/expwt-registry-icons-' . getmypid();
        if ( !is_dir( $root . '/share/icons/bootstrap-icons/svg' ) )
            mkdir( $root . '/share/icons/bootstrap-icons/svg', 0777, true );
        file_put_contents( $root . '/share/icons/bootstrap-icons/svg/cart.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>' );
        return $root;
    }

    private static function shop( array $extra )
    {
        return array( 'panels' => array( 'shop' => $extra + array( 'Label' => 'Shop', 'Script' => 'shop-expwt.js' ) ) );
    }

    protected function tearDown(): void
    {
        expwtRegistry::$iconRoot = null;
    }

    /** A panel of an extension names one of the kernel's Bootstrap Icons; the boot answer passes its name */
    public function testAnExtensionPanelNamesAKernelIcon()
    {
        expwtRegistry::$iconRoot = self::iconRoot();
        $r = expwtRegistry::fromArray( self::shop( array( 'Icon' => 'cart' ) ) );
        $this->assertSame( array(), $r['refused'] );
        $this->assertSame( 'cart', $r['panels']['shop']['icon'] );
        $boot = expwtRegistry::forBoot( $r, function ( $c, $s ) { return $s; }, function ( $t, $f ) { return '/x/' . $f; } );
        $this->assertSame( 'cart', $boot['panels'][0]['icon'] );
    }

    /** An icon the kernel does not ship, or no icon name at all: the panel stays, without its icon, and says why */
    public function testAnUnknownIconIsLeftOutAndNamed()
    {
        expwtRegistry::$iconRoot = self::iconRoot();
        foreach ( array( 'bag', '../x', '<svg>', 'Cart' ) as $icon )
        {
            $r = expwtRegistry::fromArray( self::shop( array( 'Icon' => $icon ) ) );
            $this->assertSame( array( 'shop' ), array_keys( $r['panels'] ), "$icon: the panel is still registered" );
            $this->assertSame( '', $r['panels']['shop']['icon'], $icon );
            $this->assertSame( array( "panel shop: the icon $icon is not in share/icons/bootstrap-icons" ), $r['refused'], $icon );
        }
    }

    public function testAPanelWithoutAnIconHasNone()
    {
        expwtRegistry::$iconRoot = self::iconRoot();
        $r = expwtRegistry::fromArray( self::shop( array() ) );
        $this->assertSame( array(), $r['refused'] );
        $this->assertSame( '', $r['panels']['shop']['icon'] );
        $boot = expwtRegistry::forBoot( $r, function ( $c, $s ) { return $s; }, function ( $t, $f ) { return '/x/' . $f; } );
        $this->assertSame( '', $boot['panels'][0]['icon'] );
    }

    /** The worked example names an icon the kernel ships */
    public function testTheWorkedExampleNamesAKernelIcon()
    {
        $ini = (string)file_get_contents( dirname( __DIR__, 2 ) . '/example/expwtexample/settings/expwt.ini.append.php' );
        $this->assertMatchesRegularExpression( '/^Icon=speedometer$/m', $ini );
    }
}
