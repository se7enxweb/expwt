<?php
/**
 * The pure parts of expwt: settings, policies and actions, the user's sidebar state, the field by field merge, soft
 * locks, the field inputs, the schedule, the markup of the hook and the "Verify it's you" groups.
 *
 * No database, no request.
 *
 * Run: php vendor/bin/phpunit extension/expwt/tests/phpunit/unit/expwtPureTest.php
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

require_once dirname( __DIR__ ) . '/bootstrap.php';

class expwtPureTest extends PHPUnit\Framework\TestCase
{
    private static function settings( array $values = array() )
    {
        return expwtSettings::fromArray( $values );
    }

    // ------------------------------------------------------------------------------------------------ settings

    public function testSettingsDefaultsAreOffUntilOneDotZero()
    {
        $s = self::settings();
        $this->assertFalse( $s['enabled'], 'rollout behind a setting: off by default (F14)' );
        $this->assertTrue( $s['show_to_anonymous'], 'decision 8' );
        $this->assertSame( 'rest', $s['data_layer'] );
        $this->assertSame( 'right', $s['default_side'] );
    }

    public function testSettingsRepairWrongValues()
    {
        $s = self::settings( array( 'enabled' => 'enabled', 'data_layer' => 'soap', 'default_side' => 'top', 'min_width' => 50,
                                    'max_width' => 10, 'default_width' => 9999, 'shortcut_key' => '%%', 'lock_seconds' => 1 ) );
        $this->assertTrue( $s['enabled'] );
        $this->assertSame( 'rest', $s['data_layer'] );
        $this->assertSame( 'right', $s['default_side'] );
        $this->assertSame( 200, $s['min_width'] );
        $this->assertSame( 200, $s['max_width'] );
        $this->assertSame( 200, $s['default_width'] );
        $this->assertSame( 'E', $s['shortcut_key'] );
        $this->assertSame( 30, $s['lock_seconds'] );
        $this->assertSame( 'graphql', self::settings( array( 'data_layer' => 'graphql' ) )['data_layer'] );
    }

    // ------------------------------------------------------------------------------------------------ policies

    public function testEveryActionNeedsAFunctionOfTheModule()
    {
        $module = dirname( __DIR__, 3 ) . '/modules/expwt/module.php';
        $FunctionList = array();
        include $module;
        $this->assertSame( expwtAccess::FUNCTIONS, array_keys( $FunctionList ), 'module.php and expwtAccess::FUNCTIONS' );
        foreach ( expwtAccess::ACTIONS as $action => $a )
        {
            $this->assertContains( $a[0], expwtAccess::FUNCTIONS, $action );
            $this->assertIsBool( $a[1], $action );
        }
    }

    public function testReadingActionsDoNotWrite()
    {
        foreach ( array( 'boot', 'me', 'page.load', 'create.classes', 'tree.children' ) as $action )
            $this->assertFalse( expwtAccess::writes( $action ), $action );
        foreach ( array( 'state.save', 'draft.save', 'draft.publish', 'tree.trash', 'tree.sort', 'lock.acquire' ) as $action )
            $this->assertTrue( expwtAccess::writes( $action ), $action );
        $this->assertFalse( expwtAccess::writes( 'nope' ) );
    }

    public function testCapabilitiesNeedUse()
    {
        $all = expwtAccess::capabilities( function () { return true; } );
        $this->assertTrue( $all['edit'] );
        $withoutUse = expwtAccess::capabilities( function ( $f ) { return $f !== 'use'; } );
        $this->assertSame( array_fill_keys( expwtAccess::FUNCTIONS, false ), $withoutUse, 'no sidebar, no area' );
        $editor = expwtAccess::capabilities( function ( $f ) { return in_array( $f, array( 'use', 'edit' ), true ); } );
        $this->assertTrue( expwtAccess::allows( 'draft.save', $editor ) );
        $this->assertFalse( expwtAccess::allows( 'tree.move', $editor ) );
        $this->assertFalse( expwtAccess::allows( 'nope', $editor ) );
    }

    public function testKernelViewsOfTheActions()
    {
        $this->assertSame( array( 'module' => 'content', 'view' => 'edit', 'post' => array( 'PublishButton' => '1' ) ), expwtAccess::kernelView( 'draft.publish' ) );
        $this->assertSame( 'removeobject', expwtAccess::kernelView( 'tree.trash' )['view'] );
        $this->assertNull( expwtAccess::kernelView( 'boot' ) );
    }

    public function testReauthAsksWithTheGroupsOfTheAdministration()
    {
        if ( !class_exists( 'expReauth' ) || !class_exists( 'expwtReauthTriggerHandler' ) )
            $this->markTestSkipped( 'the kernel has no "Verify it\'s you"' );
        $settings = array( 'enabled' => true, 'groups' => array(
            'content' => array( 'enabled' => true, 'user_groups' => array(),
                                'rules' => array( 'content/removeobject ConfirmButton', 'content/edit PublishButton PublishNotNotifyButton' ) ) ) );
        $this->assertSame( 'content', expwtReauthTriggerHandler::groupOf( 'draft.publish', $settings ) );
        $this->assertSame( 'content', expwtReauthTriggerHandler::groupOf( 'tree.trash', $settings ) );
        $this->assertNull( expwtReauthTriggerHandler::groupOf( 'draft.save', $settings ), 'StoreButton is not in the rules' );
        $this->assertNull( expwtReauthTriggerHandler::groupOf( 'boot', $settings ) );
        $settings['groups']['content']['enabled'] = false;
        $this->assertNull( expwtReauthTriggerHandler::groupOf( 'draft.publish', $settings ), 'a group switched off asks nothing' );
    }

    // ------------------------------------------------------------------------------------------------ state

    public function testStateKeepsOnlyValidValues()
    {
        $s = self::settings();
        $state = expwtState::normalize( array( 'edge' => 'top', 'align' => 'start', 'offset' => '120', 'width' => 99999, 'height' => 5,
                                               'open' => 'true', 'mode' => 'overlay', 'theme' => 'dark', 'panel' => 'me', 'evil' => '<script>' ), $s );
        $this->assertSame( array( 'edge' => 'top', 'align' => 'start', 'offset' => 120, 'width' => 720, 'height' => 160, 'open' => true,
                                  'mode' => 'overlay', 'theme' => 'dark', 'panel' => 'me' ), $state );
        $bad = expwtState::normalize( array( 'edge' => 'middle', 'align' => 'center', 'height' => 99999 ), $s );
        $this->assertSame( array( 'right', 'end', 640 ), array( $bad['edge'], $bad['align'], $bad['height'] ) );
        $this->assertSame( expwtState::defaults( $s ), expwtState::normalize( 'nonsense', $s ) );
        $this->assertSame( expwtState::defaults( $s ), expwtState::decode( '{broken', $s ) );
        $this->assertFalse( expwtState::defaults( $s )['open'], 'decision 4: closed on the first visit' );
    }

    public function testFieldMarkersOnlyForAUserWhoMayEditWithTheSidebar()
    {
        $this->assertTrue( expwtFieldMarkerHandler::decide( true, true, 'yes', 'yes' ) );
        $this->assertTrue( expwtFieldMarkerHandler::decide( true, true, 'yes', 'limited' ) );
        $this->assertFalse( expwtFieldMarkerHandler::decide( true, false, 'yes', 'yes' ), 'F20: never for a visitor' );
        $this->assertFalse( expwtFieldMarkerHandler::decide( false, true, 'yes', 'yes' ), 'the sidebar switched off' );
        $this->assertFalse( expwtFieldMarkerHandler::decide( true, true, 'no', 'yes' ) );
        $this->assertFalse( expwtFieldMarkerHandler::decide( true, true, 'yes', 'no' ) );
        $ini = file_get_contents( dirname( __DIR__, 3 ) . '/settings/site.ini.append.php' );
        $this->assertStringContainsString( "[ContentFieldMarkerSettings]\nHandlers[]=expwtFieldMarkerHandler", $ini );
    }

    public function testStateFromBeforeTheTopAndBottomEdgesIsMigratedOnRead()
    {
        $s = self::settings();
        $old = expwtState::decode( '{"side":"left","offset":40,"width":400,"open":true}', $s );
        $this->assertSame( 'left', $old['edge'], 'F17: side becomes edge' );
        $this->assertArrayNotHasKey( 'side', $old, 'the next save writes edge only' );
        $this->assertSame( array( 40, 400, true ), array( $old['offset'], $old['width'], $old['open'] ) );
        $this->assertSame( 'bottom', expwtState::normalize( array( 'edge' => 'bottom', 'side' => 'left' ), $s )['edge'], 'edge wins over side' );
    }

    public function testDefaultEdgeFallsBackToTheOldDefaultSide()
    {
        $this->assertSame( 'right', self::settings()['default_edge'] );
        $this->assertSame( 'left', self::settings( array( 'default_side' => 'left' ) )['default_edge'], 'an override with DefaultSide only' );
        $this->assertSame( 'top', self::settings( array( 'default_side' => 'left', 'default_edge' => 'top' ) )['default_edge'] );
        $this->assertSame( 'left', self::settings( array( 'default_side' => 'left', 'default_edge' => 'diagonal' ) )['default_edge'] );
        $s = self::settings( array( 'default_edge' => 'bottom', 'default_align' => 'start', 'min_height' => 10, 'max_height' => 5, 'default_height' => 900 ) );
        $first = expwtState::defaults( $s );
        $this->assertSame( array( 'bottom', 'start', 120, 120 ), array( $first['edge'], $first['align'], $s['min_height'], $first['height'] ) );
        $this->assertSame( 'end', self::settings( array( 'default_align' => 'middle' ) )['default_align'] );
    }

    public function testThemeFollowsTheSidebarThenTheAdministration()
    {
        $s = expwtState::defaults( self::settings() );
        $this->assertSame( 'dark', expwtState::theme( $s, 'dark' ) );
        $this->assertSame( 'system', expwtState::theme( $s, 'system' ) );
        $this->assertSame( 'system', expwtState::theme( $s, null ) );
        $s['theme'] = 'light';
        $this->assertSame( 'light', expwtState::theme( $s, 'dark' ) );
    }

    // ------------------------------------------------------------------------------------------------ merge, locks

    public function testMergeKeepsWhatOnlyOneSideChanged()
    {
        $base = array( 'title' => 'A', 'intro' => 'I', 'body' => 'B' );
        $theirs = array( 'title' => 'A2', 'intro' => 'I', 'body' => 'B' );
        $r = expwtMerge::merge( $base, array( 'intro' => 'I2' ), $theirs );
        $this->assertSame( array( 'intro' => 'I2' ), $r['merged'] );
        $this->assertSame( array(), $r['conflicts'] );
        $same = expwtMerge::merge( $base, array( 'title' => 'A2' ), $theirs );
        $this->assertSame( array( 'title' => 'A2' ), $same['merged'], 'the same change on both sides' );
        $untouched = expwtMerge::merge( $base, array( 'title' => 'A' ), $theirs );
        $this->assertSame( array(), $untouched['merged'], 'not changed here: theirs stays' );
    }

    public function testMergeAsksPerFieldWhenBothChanged()
    {
        $r = expwtMerge::merge( array( 'title' => 'A' ), array( 'title' => 'Mine' ), array( 'title' => 'Theirs' ) );
        $this->assertSame( array( 'title' => array( 'base' => 'A', 'mine' => 'Mine', 'theirs' => 'Theirs' ) ), $r['conflicts'] );
        $this->assertSame( array( 'title' => 'Mine' ), expwtMerge::merge( array( 'title' => 'A' ), array( 'title' => 'Mine' ), array( 'title' => 'Theirs' ),
                                                                         array( 'title' => 'mine' ) )['merged'] );
        $this->assertSame( array(), expwtMerge::merge( array( 'title' => 'A' ), array( 'title' => 'Mine' ), array( 'title' => 'Theirs' ),
                                                      array( 'title' => 'theirs' ) )['merged'] );
    }

    public function testLocksAreSoftAndCanBeTakenOver()
    {
        $now = 1000;
        $a = expwtLock::decide( null, 14, 'Anna', $now, 300 );
        $this->assertTrue( $a['changed'] );
        $this->assertSame( 1300, $a['lock']['until'] );
        $b = expwtLock::decide( $a['lock'], 15, 'Ben', $now + 10, 300 );
        $this->assertFalse( $b['changed'] );
        $this->assertSame( 'Anna', $b['held_by_other']['name'] );
        $take = expwtLock::decide( $a['lock'], 15, 'Ben', $now + 10, 300, true );
        $this->assertSame( 14, $take['lock']['took_over_from'] );
        $renew = expwtLock::decide( $a['lock'], 14, 'Anna', $now + 60, 300 );
        $this->assertSame( $now, $renew['lock']['since'], 'a heartbeat keeps the start' );
        $expired = expwtLock::decide( $a['lock'], 15, 'Ben', $now + 301, 300 );
        $this->assertTrue( $expired['changed'] );
        $this->assertNull( expwtLock::heldByOther( $a['lock'], 14, $now ) );
        $this->assertNotNull( expwtLock::heldByOther( $a['lock'], 15, $now ) );
        $this->assertNull( expwtLock::heldByOther( $a['lock'], 15, $now + 301 ) );
    }

    // ------------------------------------------------------------------------------------------------ fields

    public function testFieldInputs()
    {
        $this->assertSame( array( 'input' => 'text', 'inline' => true, 'editable' => true, 'embed' => false ), expwtFields::describe( 'ezstring' ) );
        // decision 12: rich text in the installation's editor (expwt/field), never the sidebar's own controls
        $this->assertSame( array( 'input' => 'richtext', 'inline' => false, 'editable' => false, 'embed' => true ), expwtFields::describe( 'ezxmltext' ) );
        foreach ( array( 'eztags', 'ezobjectrelationlist', 'ezimage', 'ezbinaryfile', 'xrowmetadata', 'ezgmaplocation', 'ezmatrix' ) as $datatype )
            $this->assertTrue( expwtFields::describe( $datatype )['embed'], "$datatype in its own editor" );
        $this->assertSame( 'richtext', expwtFields::describe( 'ezxmltext' )['input'] );
        $this->assertSame( 'multiselect', expwtFields::describe( 'ezselection', true )['input'] );
        $this->assertFalse( expwtFields::describe( 'ezuser' )['editable'] );
        $this->assertSame( 'hidden', expwtFields::describe( 'ezuser' )['input'] );
        $this->assertFalse( expwtFields::describe( 'ezimage' )['editable'], 'an image needs an upload (the full editor for now)' );
        $this->assertSame( 'full', expwtFields::describe( 'ezmatrix' )['input'] );
        $this->assertSame( 'full', expwtFields::describe( 'xrowmetadata' )['input'], 'SEO metadata: its XML form is no field to type in; the full editor until its own panel' );
        $this->assertSame( 'full', expwtFields::describe( 'ezgmaplocation' )['input'], 'a map location: the full editor' );
        // tags and relations show their names and are never written from the sidebar's form: their string form is the
        // datatype's storage ("|#|#|#" for eztags, object ids for relations)
        foreach ( array( 'eztags' => 'tags', 'ezobjectrelationlist' => 'relations', 'ezobjectrelation' => 'relations' ) as $datatype => $input )
        {
            $d = expwtFields::describe( $datatype );
            $this->assertSame( $input, $d['input'], $datatype );
            $this->assertFalse( $d['editable'], "$datatype is not written from the form" );
            $this->assertTrue( $d['embed'], "$datatype: the datatype's own editor" );
        }
        $this->assertSame( array( 'https://example.org/a', 'Text | with bar' ), expwtFields::urlParts( 'https://example.org/a|Text | with bar' ) );
        $this->assertSame( array( 'https://example.org/', '' ), expwtFields::urlParts( 'https://example.org/' ) );
    }

    public function testValuesInTheStringFormOfTheirDatatype()
    {
        $this->assertSame( '1', expwtFields::toStringForm( array( 'input' => 'checkbox' ), 'on' ) );
        $this->assertSame( '0', expwtFields::toStringForm( array( 'input' => 'checkbox' ), '' ) );
        $this->assertFalse( expwtFields::toStringForm( array( 'input' => 'number' ), '1.5' ) );
        $this->assertSame( '-3', expwtFields::toStringForm( array( 'input' => 'number' ), '-3' ) );
        $this->assertSame( '1.5', expwtFields::toStringForm( array( 'input' => 'decimal' ), '1.5' ) );
        $this->assertFalse( expwtFields::toStringForm( array( 'input' => 'email' ), 'no' ) );
        $this->assertSame( 'a b', expwtFields::toStringForm( array( 'input' => 'text' ), "a\nb" ) );
        $this->assertSame( 'x|y', expwtFields::toStringForm( array( 'input' => 'multiselect' ), array( 'x', 'y' ) ) );
        $this->assertSame( '1700000000', expwtFields::toStringForm( array( 'input' => 'date' ), '1700000000' ) );
        $this->assertSame( '', expwtFields::toStringForm( array( 'input' => 'date' ), '' ) );
        $this->assertSame( '37800', expwtFields::toStringForm( array( 'input' => 'time' ), '10:30' ) );
        $this->assertFalse( expwtFields::toStringForm( array( 'input' => 'time' ), '25:00' ) );
        $this->assertFalse( expwtFields::toStringForm( array( 'input' => 'date' ), '2026-02-31' ) );
        $this->assertMatchesRegularExpression( '/^\d+$/', (string)expwtFields::toStringForm( array( 'input' => 'datetime' ), '2026-11-02T09:30' ) );
    }

    // ------------------------------------------------------------------------------------------------ schedule

    public function testSchedule()
    {
        $this->assertSame( 1700000000, expwtSchedule::parse( '1700000000' ) );
        $this->assertNull( expwtSchedule::parse( 'tomorrow' ) );
        $this->assertSame( strtotime( '2026-11-02T09:30:00Z' ), expwtSchedule::parse( '2026-11-02T09:30:00Z' ) );
        $p = expwtSchedule::publishEntry( 59, 4, 14, 2000, 3000 );
        $this->assertSame( 'expwt_publish_59_4', $p['name'] );
        $this->assertSame( 3000, $p['data']['until'] );
        $due = expwtSchedule::due( array( 'b' => array( 'at' => 20 ), 'a' => array( 'at' => 10 ), 'c' => array( 'at' => 99 ) ), 50 );
        $this->assertSame( array( 'a', 'b' ), array_keys( $due ), 'the earliest first, the future left' );
        $this->assertSame( array( 7 => 10, 5 => 20, 6 => 30 ), expwtSchedule::priorities( array( 7, 5, 6 ), array( 5, 6, 7 ) ) );
        $this->assertSame( array(), expwtSchedule::priorities( array( 7, 7 ), array( 7 ) ), 'twice' );
        $this->assertSame( array(), expwtSchedule::priorities( array( 9 ), array( 7 ) ), 'not a child' );
    }

    // ------------------------------------------------------------------------------------------------ the hook

    private static function hookContext( array $change = array() )
    {
        return $change + array( 'enabled' => true, 'anonymous' => false, 'show_to_anonymous' => true, 'may_use' => true,
                                'script' => '/var/cache/public/javascript/expwt.js', 'config' => array( 'node' => 2, 'label' => 'a "quoted" <b>' ) );
    }

    public function testHookPrintsNothingWhereTheSidebarIsNotShown()
    {
        $this->assertSame( '', expwtHook::markup( self::hookContext( array( 'enabled' => false ) ) ), 'switched off' );
        $this->assertSame( '', expwtHook::markup( self::hookContext( array( 'anonymous' => true, 'show_to_anonymous' => false ) ) ),
                           'F12: not one byte for anonymous visitors while ShowToAnonymous is off' );
        $this->assertSame( '', expwtHook::markup( self::hookContext( array( 'may_use' => false ) ) ), 'no expwt/use' );
        $this->assertSame( '', expwtHook::markup( self::hookContext( array( 'script' => '' ) ) ), 'no script, no element' );
    }

    public function testHookMarkupIsOneElementAndOneDeferredScript()
    {
        $html = expwtHook::markup( self::hookContext() );
        $this->assertSame( 1, substr_count( $html, '<exp-wt ' ) );
        $this->assertSame( 1, substr_count( $html, '<script ' ) );
        $this->assertStringContainsString( ' defer></script>', $html );
        $this->assertStringNotContainsString( 'style=', $html );
        $this->assertStringContainsString( '\&quot;quoted\&quot; &lt;b&gt;', $html, 'the configuration escaped for the attribute' );
        $this->assertStringNotContainsString( '<b>', $html );
        preg_match( '/data-config="([^"]*)"/', $html, $m );
        $this->assertSame( array( 'node' => 2, 'label' => 'a "quoted" <b>' ), json_decode( html_entity_decode( $m[1], ENT_QUOTES ), true ) );
        $this->assertNotSame( '', expwtHook::markup( self::hookContext( array( 'anonymous' => true, 'may_use' => false ) ) ), 'decision 8' );
    }

    public function testInjectBeforeTheLastBodyEndOnce()
    {
        $page = '<html><body><p>x</p></body></html>';
        $this->assertSame( '<html><body><p>x</p><M></body></html>', expwtHook::inject( $page, '<M>' ) );
        $this->assertSame( $page, expwtHook::inject( $page, '' ) );
        $with = '<html><body><exp-wt></exp-wt></body></html>';
        $this->assertSame( $with, expwtHook::inject( $with, '<M>' ), 'the hook placed it already' );
        $this->assertSame( '{"a":1}', expwtHook::inject( '{"a":1}', '<M>' ), 'not a page' );
    }
}
