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
        $this->assertSame( 'disabled', $s['show_to_anonymous'], 'decision 8 (owner 2026-10-31): visitors get nothing by default' );
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

    /**
     * No page of the sidebar is the session's last page (LastAccessesURI, LastAccessedModifyingURI): content/edit's
     * Discard and Publish return there, and must land on the website's page, not on a field editor of the frame.
     */
    public function testViewsOfTheSidebarAreNeverTheLastPageOfTheSession()
    {
        $ViewList = array();
        include dirname( __DIR__, 3 ) . '/modules/expwt/module.php';
        $this->assertSame( 'ajax', $ViewList['api']['ui_context'] ?? null, 'api' );
        $this->assertSame( 'ajax', $ViewList['field']['ui_context'] ?? null, 'field' );
        $this->assertSame( 'authentication', $ViewList['reauth']['ui_context'] ?? null, 'reauth' );
        // a browse the frame goes to records itself as the last modifying page; field.php keeps it and puts it back
        $field = (string)file_get_contents( dirname( __DIR__, 3 ) . '/modules/expwt/field.php' );
        $this->assertStringContainsString( "setSessionVariable( 'expwtFrameModifyingURI'", $field );
        $this->assertStringContainsString( "setSessionVariable( 'LastAccessedModifyingURI', \$before )", $field );
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
        // E3: the real send of a newsletter asks in its own group, whatever the administration's rules say;
        // the test send and making an edition do not
        $this->assertSame( 'newsletter', expwtReauthTriggerHandler::groupOf( 'newsletter.send', $settings ) );
        $this->assertNull( expwtReauthTriggerHandler::groupOf( 'newsletter.test', $settings ) );
        $this->assertNull( expwtReauthTriggerHandler::groupOf( 'newsletter.load', $settings ) );
        $this->assertSame( 'newsletter', expwtAccess::REAUTH_GROUPS['newsletter.send'] );
        $this->assertSame( expwtGateway::NEWSLETTER_REAUTH_GROUP, expwtAccess::REAUTH_GROUPS['newsletter.send'] );
    }

    /** E3: the group of the real send is shipped switched on, and added to the kernel's groups (never replacing them) */
    public function testTheNewsletterReauthGroupIsShippedOn()
    {
        $ini = file_get_contents( dirname( __DIR__, 3 ) . '/settings/site.ini.append.php' );
        $this->assertMatchesRegularExpression( '/^Groups\[\]=newsletter$/m', $ini );
        $this->assertDoesNotMatchRegularExpression( '/^Groups\[\]$/m', $ini, 'a bare Groups[] would drop the kernel\'s groups' );
        $this->assertMatchesRegularExpression( '/\[ReauthGroup_newsletter\]\nEnabled=true\n/', $ini );
    }

    /** E3: the state of a send, why an edition cannot go out, the addresses of a test send, the introduction as XML */
    public function testTheNewsletterOfThePage()
    {
        $this->assertSame( 'waiting', expwtGateway::sendState( 0 ) );
        $this->assertSame( 'waiting', expwtGateway::sendState( 4 ) );
        $this->assertSame( 'queued', expwtGateway::sendState( 1 ) );
        $this->assertSame( 'sending', expwtGateway::sendState( 2 ) );
        $this->assertSame( 'sent', expwtGateway::sendState( 3 ) );
        $this->assertSame( 'aborted', expwtGateway::sendState( 9 ) );

        $none = array( 'required' => false, 'state' => 'none' );
        $this->assertSame( '', expwtGateway::editionBlocker( true, 'draft', $none, 40 ) );
        $this->assertSame( 'This edition has no newsletter data.', expwtGateway::editionBlocker( false, 'draft', $none, 40 ) );
        $this->assertSame( 'This edition is being sent.', expwtGateway::editionBlocker( true, 'process', $none, 40 ) );
        $this->assertSame( 'This edition was sent and is in the archive.', expwtGateway::editionBlocker( true, 'archive', $none, 40 ) );
        $this->assertSame( 'The list has no subscribers.', expwtGateway::editionBlocker( true, 'draft', $none, 0 ) );
        $this->assertSame( 'This edition waits for its approval. It can be sent when it is approved.',
                           expwtGateway::editionBlocker( true, 'draft', array( 'required' => true, 'state' => 'pending' ), 40 ) );
        $this->assertSame( 'This edition was rejected. Change it and ask for the approval again.',
                           expwtGateway::editionBlocker( true, 'draft', array( 'required' => true, 'state' => 'rejected' ), 40 ) );
        $this->assertStringStartsWith( 'The list needs an approval', expwtGateway::editionBlocker( true, 'draft', array( 'required' => true, 'state' => 'none' ), 40 ) );
        $this->assertSame( '', expwtGateway::editionBlocker( true, 'draft', array( 'required' => true, 'state' => 'approved' ), 40 ) );
        foreach ( array( 'This edition is being sent.', 'This edition was sent and is in the archive.', 'The list has no subscribers.',
                         'This edition has no newsletter data.' ) as $reason )
            $this->assertContains( $reason, expwtStrings::SOURCES, 'every reason is translated' );

        $r = expwtGateway::testRecipients( "Ann@Example.org, ann@example.org;\nbob@example.org\n\nnot an address", 25 );
        $this->assertSame( array( 'ann@example.org', 'bob@example.org' ), $r['valid'], 'lower case, each once' );
        $this->assertSame( array( 'not an address' ), $r['invalid'] );
        $this->assertFalse( $r['over'] );
        $this->assertTrue( expwtGateway::testRecipients( 'a@example.org, b@example.org, c@example.org', 2 )['over'] );
        $this->assertSame( array(), expwtGateway::testRecipients( ' , ;', 5 )['valid'] );

        $xml = expwtGateway::paragraphsXml( "Hello <you> & yours,\nsecond line\n\n\nNew paragraph\r\n" );
        $dom = new DOMDocument();
        $this->assertTrue( $dom->loadXML( $xml ) );
        $this->assertSame( 'section', $dom->documentElement->nodeName );
        $paragraphs = $dom->getElementsByTagName( 'paragraph' );
        $this->assertSame( 2, $paragraphs->length );
        $this->assertSame( 'Hello <you> & yours,second line', $paragraphs->item( 0 )->textContent );
        $this->assertSame( 1, $paragraphs->item( 0 )->getElementsByTagName( 'line' )->length );
        $this->assertSame( 'New paragraph', $paragraphs->item( 1 )->textContent );

        $this->assertContains( 'newsletter', expwtState::PANELS );
        $this->assertContains( 'newsletter', expwtRegistry::CORE_PANELS );
    }

    // ------------------------------------------------------------------------------------------------ state

    public function testStateKeepsOnlyValidValues()
    {
        $s = self::settings();
        $state = expwtState::normalize( array( 'edge' => 'top', 'align' => 'start', 'offset' => '120', 'width' => 99999, 'height' => 5,
                                               'open' => 'true', 'mode' => 'overlay', 'theme' => 'dark', 'panel' => 'me', 'evil' => '<script>' ), $s );
        $this->assertSame( array( 'edge' => 'top', 'align' => 'start', 'offset' => 120, 'width' => 720, 'height' => 160, 'open' => true,
                                  'mode' => 'overlay', 'theme' => 'dark', 'panel' => 'me', 'keys' => array() ), $state );
        $bad = expwtState::normalize( array( 'edge' => 'middle', 'align' => 'center', 'height' => 99999 ), $s );
        $this->assertSame( array( 'right', 'end', 640 ), array( $bad['edge'], $bad['align'], $bad['height'] ) );
        $this->assertSame( expwtState::defaults( $s ), expwtState::normalize( 'nonsense', $s ) );
        $this->assertSame( expwtState::defaults( $s ), expwtState::decode( '{broken', $s ) );
        $this->assertFalse( expwtState::defaults( $s )['open'], 'decision 4: closed on the first visit' );
    }

    public function testVisitorsOnlyByShowToAnonymousOrTheAnonymousRole()
    {
        $this->assertSame( 'disabled', expwtSettings::anonymousMode( null ) );
        $this->assertSame( 'enabled', expwtSettings::anonymousMode( 'enabled' ) );
        $this->assertSame( 'policy', expwtSettings::anonymousMode( ' Policy ' ) );
        $this->assertSame( 'disabled', expwtSettings::anonymousMode( 'sometimes' ), 'unknown: disabled' );
        $this->assertTrue( expwtSettings::anonymousMayUse( 'enabled', 'no' ) );
        $this->assertTrue( expwtSettings::anonymousMayUse( 'policy', 'yes' ) );
        $this->assertTrue( expwtSettings::anonymousMayUse( 'policy', 'limited' ) );
        $this->assertFalse( expwtSettings::anonymousMayUse( 'policy', 'no' ), 'policy without expwt/use for Anonymous' );
        $this->assertFalse( expwtSettings::anonymousMayUse( 'disabled', 'yes' ) );
        $visitor = array( 'enabled' => true, 'anonymous' => true, 'script' => '/x.js', 'config' => array() );
        $this->assertSame( '', expwtHook::markup( $visitor + array( 'show_to_anonymous' => 'disabled', 'may_use' => false ) ), 'F12: zero bytes' );
        $this->assertSame( '', expwtHook::markup( $visitor + array( 'show_to_anonymous' => 'policy', 'may_use' => false ) ), 'policy without access: zero bytes' );
        $this->assertStringContainsString( '<exp-wt', expwtHook::markup( $visitor + array( 'show_to_anonymous' => 'policy', 'may_use' => true ) ) );
        $this->assertStringContainsString( '<exp-wt', expwtHook::markup( $visitor + array( 'show_to_anonymous' => 'enabled', 'may_use' => true ) ) );
        $this->assertSame( 'policy', self::settings( array( 'show_to_anonymous' => 'policy' ) )['show_to_anonymous'] );
    }

    public function testNotInTheAdministrationUnlessShowInAdmin()
    {
        $on = self::settings( array( 'enabled' => 'enabled' ) );
        $this->assertFalse( $on['show_in_admin'], 'owner 2026-10-31: off in the administration by default' );
        $this->assertTrue( expwtSettings::showHere( $on, 'site', false ) );
        $this->assertFalse( expwtSettings::showHere( $on, 'admin', true ), 'an administration gets nothing' );
        $this->assertTrue( expwtSettings::showHere( self::settings( array( 'enabled' => 'enabled', 'show_in_admin' => 'enabled' ) ), 'admin', true ), 'ShowInAdmin=enabled' );
        $this->assertFalse( expwtSettings::showHere( self::settings(), 'site', false ), 'switched off' );
        $only = self::settings( array( 'enabled' => 'enabled', 'siteaccess_list' => array( 'site', ' twig ', '' ) ) );
        $this->assertSame( array( 'site', 'twig' ), $only['siteaccess_list'] );
        $this->assertTrue( expwtSettings::showHere( $only, 'twig', false ) );
        $this->assertFalse( expwtSettings::showHere( $only, 'smarty', false ), 'SiteAccessList[]' );
        $this->assertFalse( expwtSettings::showHere( self::settings( array( 'enabled' => 'enabled', 'siteaccess_exclude' => array( 'smarty' ) ) ), 'smarty', false ), 'SiteAccessExcludeList[]' );
        $this->assertTrue( expwtSettings::showHere( self::settings( array( 'enabled' => 'enabled', 'show_in_admin' => 'enabled', 'siteaccess_list' => array( 'admin4l' ) ) ), 'admin4l', true ) );
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
        $this->assertSame( array( 7 => 10, 5 => 20, 6 => 30 ), expwtSchedule::priorities( array( 7, 5, 6 ), array( 5, 6, 7 ) ) );
        $this->assertSame( array(), expwtSchedule::priorities( array( 7, 7 ), array( 7 ) ), 'twice' );
        $this->assertSame( array(), expwtSchedule::priorities( array( 9 ), array( 7 ) ), 'not a child' );
    }

    /** F21: the dates of draft.publish as the kernel's expContentSchedule::plan() takes them */
    public function testScheduleRequest()
    {
        $this->assertSame( array( 'at' => null, 'until' => null, 'error' => null ), expwtSchedule::request( array() ), 'publish now, take-down left' );
        $this->assertSame( array( 'at' => 2000, 'until' => 3000, 'error' => null ),
                           expwtSchedule::request( array( 'publishAt' => '2000', 'unpublishAt' => '3000' ) ) );
        $this->assertSame( array( 'at' => null, 'until' => 0, 'error' => null ),
                           expwtSchedule::request( array( 'publishAt' => '', 'unpublishAt' => '' ) ), 'empty take-down: cleared' );
        $this->assertSame( 'bad_value', expwtSchedule::request( array( 'publishAt' => 'tomorrow' ) )['error'] );
        $this->assertSame( 'bad_value', expwtSchedule::request( array( 'unpublishAt' => 'never' ) )['error'] );
    }

    /** F21: what the Page panel shows for publish at and take down at */
    public function testScheduleView()
    {
        $can = array( 'publish' => array( 'identifier' => 'publish_date', 'datatype' => 'ezdatetime', 'precision' => 'datetime' ),
                      'takeDown' => null );
        $view = expwtSchedule::view( $can, 5000, 0, 1000 );
        $this->assertSame( array( 'identifier' => 'publish_date', 'precision' => 'datetime', 'value' => 5000 ), $view['publish'] );
        $this->assertNull( $view['takeDown'], 'no field: the panel says why' );
        $this->assertNull( expwtSchedule::view( $can, 500, 0, 1000 )['publish']['value'], 'a date passed is not offered again' );
        $this->assertSame( array( 'publish' => null, 'takeDown' => null ), expwtSchedule::view( array(), 0, 0, 1000 ) );
    }

    // ------------------------------------------------------------------------------------------------ the hook

    private static function hookContext( array $change = array() )
    {
        return $change + array( 'enabled' => true, 'anonymous' => false, 'show_to_anonymous' => 'enabled', 'may_use' => true,
                                'script' => '/var/cache/public/javascript/expwt.js', 'config' => array( 'node' => 2, 'label' => 'a "quoted" <b>' ) );
    }

    /** F19: the package replaces ezwt, so Composer never installs the two together (decision 1). */
    public function testPackageReplacesEzwt()
    {
        $composer = json_decode( (string)file_get_contents( dirname( __DIR__, 3 ) . '/composer.json' ), true );
        $this->assertIsArray( $composer );
        $this->assertSame( 'se7enxweb/expwt', $composer['name'] );
        $this->assertSame( '*', $composer['replace']['se7enxweb/ezwt'] ?? null );
        $this->assertArrayNotHasKey( 'se7enxweb/ezwt', $composer['require'] ?? array() );
    }

    /** Editing on the page is its own state, never tied to the panel being open (tests/js/editstate.test.js, Node). */
    public function testEditingOnThePageIsItsOwnState()
    {
        $node = trim( (string)shell_exec( 'command -v node 2>&1' ) );
        if ( $node === '' || !is_executable( $node ) )
            $this->markTestSkipped( 'Node is not installed' );
        $script = dirname( __DIR__, 2 ) . '/js/editstate.test.js';
        exec( escapeshellarg( $node ) . ' ' . escapeshellarg( $script ) . ' 2>&1', $out, $status );
        $this->assertSame( 0, $status, implode( "\n", $out ) );
        $this->assertStringStartsWith( 'PASS', (string)end( $out ) );
    }

    /** The rules of the SEO check (tests/js/seo.test.js, Node) */
    public function testTheRulesOfTheSeoCheck()
    {
        $node = trim( (string)shell_exec( 'command -v node 2>&1' ) );
        if ( $node === '' || !is_executable( $node ) )
            $this->markTestSkipped( 'Node is not installed' );
        exec( escapeshellarg( $node ) . ' ' . escapeshellarg( dirname( __DIR__, 2 ) . '/js/seo.test.js' ) . ' 2>&1', $out, $status );
        $this->assertSame( 0, $status, implode( "\n", $out ) );
        $this->assertStringStartsWith( 'PASS', (string)end( $out ) );
    }

    /** F30: the user's own shortcuts are checked on the server as in the browser, and only those that differ are kept */
    public function testTheUsersShortcutsAreCheckedAndKeptOnlyWhenTheyDiffer()
    {
        $this->assertSame( array(), expwtState::keys( null ) );
        $this->assertSame( array( 'save' => 'Q' ), expwtState::keys( array( 'save' => 'q' ) ) );
        $this->assertSame( array(), expwtState::keys( array( 'save' => 'D' ) ), 'the default is not kept' );
        $this->assertSame( array(), expwtState::keys( array( 'save' => 'S', 'help' => 'I' ) ), 'reserved: the browser\'s or the system\'s' );
        $this->assertSame( array(), expwtState::keys( array( 'save' => '7', 'inline' => 'KK', 'help' => array() ) ), 'one letter only' );
        $this->assertSame( array(), expwtState::keys( array( 'save' => 'E' ) ), 'taken by open' );
        $this->assertSame( array( 'save' => 'Q' ), expwtState::keys( array( 'save' => 'Q', 'help' => 'Q' ) ), 'each key once' );
        $this->assertSame( array( 'open' => 'E' ), expwtState::keys( array( 'open' => 'E' ), 'W' ), 'against the setting ShortcutKey' );
        $this->assertSame( array(), expwtState::keys( array( 'publish' => 'P' ) ), 'unknown actions are dropped' );
        $s = self::settings();
        $this->assertSame( array( 'inline' => 'J' ), expwtState::normalize( array( 'keys' => array( 'inline' => 'j', 'save' => 'B' ) ), $s )['keys'] );
        $this->assertSame( array(), expwtState::defaults( $s )['keys'] );
        // the browser's list is the same (expwt.js ExpwtKeys)
        $js = file_get_contents( dirname( __DIR__, 3 ) . '/design/standard/javascript/expwt.js' );
        $this->assertSame( 1, preg_match( "/RESERVED: '([A-Z]+)'/", $js, $m ) );
        $this->assertSame( expwtState::RESERVED_KEYS, $m[1] );
        $this->assertSame( 1, preg_match( "/DEFAULTS: \{ open: '(\w)', save: '(\w)', inline: '(\w)', help: '(\w)' \}/", $js, $d ) );
        $this->assertSame( array_values( expwtState::KEYS ), array_slice( $d, 1 ) );
    }

    /** F30: the rules of the shortcuts in the browser (tests/js/shortcuts.test.js, Node) */
    public function testTheRulesOfTheShortcuts()
    {
        $node = trim( (string)shell_exec( 'command -v node 2>&1' ) );
        if ( $node === '' || !is_executable( $node ) )
            $this->markTestSkipped( 'Node is not installed' );
        exec( escapeshellarg( $node ) . ' ' . escapeshellarg( dirname( __DIR__, 2 ) . '/js/shortcuts.test.js' ) . ' 2>&1', $out, $status );
        $this->assertSame( 0, $status, implode( "\n", $out ) );
        $this->assertStringStartsWith( 'PASS', (string)end( $out ) );
    }

    /** E2: an answer as text and the preview of a submission in the Forms panel */
    public function testTheAnswersOfASubmission()
    {
        $this->assertSame( 'Hello', expwtGateway::collectedValue( 'Hello', 0, 0.0 ) );
        $this->assertSame( '3', expwtGateway::collectedValue( '', 3, 0.0 ) );
        $this->assertSame( '2.5', expwtGateway::collectedValue( '  ', 0, 2.5 ) );
        $this->assertSame( '', expwtGateway::collectedValue( '', 0, 0.0 ) );
        $long = str_repeat( 'ä', 100 );
        $preview = expwtGateway::previewOf( array( array( 'value' => '' ), array( 'value' => "Ann\n  Smith" ), array( 'value' => $long ), array( 'value' => 'third' ) ) );
        $this->assertSame( 'Ann Smith · ' . str_repeat( 'ä', 79 ) . '…', $preview, 'two answers with text, one line each, cut to 80 characters' );
        $this->assertSame( '', expwtGateway::previewOf( array() ) );
        $this->assertContains( 'forms', expwtState::PANELS );
        $this->assertContains( 'forms', expwtRegistry::CORE_PANELS );
    }

    /** E1: which fields get Exponential Intelligence and the text of a suggestion (tests/js/ai.test.js, Node) */
    public function testExponentialIntelligenceOnTheFields()
    {
        $this->assertContains( 'ezxmltext', expwtGateway::AI_SUMMARY_DATATYPES );
        $node = trim( (string)shell_exec( 'command -v node 2>&1' ) );
        if ( $node === '' || !is_executable( $node ) )
            $this->markTestSkipped( 'Node is not installed' );
        exec( escapeshellarg( $node ) . ' ' . escapeshellarg( dirname( __DIR__, 2 ) . '/js/ai.test.js' ) . ' 2>&1', $out, $status );
        $this->assertSame( 0, $status, implode( "\n", $out ) );
        $this->assertStringStartsWith( 'PASS', (string)end( $out ) );
    }

    /** Lost work (F28): autosave and the copy on this device (tests/js/autosave.test.js, Node) */
    public function testTheRulesOfAutosaveAndTheCopyOnThisDevice()
    {
        $node = trim( (string)shell_exec( 'command -v node 2>&1' ) );
        if ( $node === '' || !is_executable( $node ) )
            $this->markTestSkipped( 'Node is not installed' );
        exec( escapeshellarg( $node ) . ' ' . escapeshellarg( dirname( __DIR__, 2 ) . '/js/autosave.test.js' ) . ' 2>&1', $out, $status );
        $this->assertSame( 0, $status, implode( "\n", $out ) );
        $this->assertStringStartsWith( 'PASS', (string)end( $out ) );
    }

    /** The SEO check looks a link up in this siteaccess: its prefix off, decoded, without query and anchor */
    public function testTheLinksOfTheSeoCheckArePathsOfThisSiteaccess()
    {
        $others = array( 'twig', 'admin' );
        $this->assertSame( 'news/a-page', expwtGateway::linkPath( '/smarty/news/a-page?x=1#top', '/smarty', $others ) );
        $this->assertSame( '', expwtGateway::linkPath( '/smarty', '/smarty', $others ) );
        $this->assertSame( '', expwtGateway::linkPath( '/smarty/', '/smarty', $others ) );
        $this->assertNull( expwtGateway::linkPath( '/twig/news', '/smarty', $others ), 'another siteaccess with a prefix of its own' );
        $this->assertNull( expwtGateway::linkPath( '/smartyx/news', '/smarty', $others ) );
        $this->assertSame( "news/caf\u{e9}", expwtGateway::linkPath( '/news/caf%C3%A9', '', $others ) );
        $this->assertNull( expwtGateway::linkPath( '/twig/news', '', $others ), 'from the default siteaccess too' );
        $this->assertSame( 'content/view/full/2', expwtGateway::linkPath( '/index.php/content/view/full/2', '', $others ) );
        $this->assertNull( expwtGateway::linkPath( '//other.example/x', '', $others ), 'not a path' );
        $this->assertNull( expwtGateway::linkPath( 'news', '', $others ) );
        $this->assertNull( expwtGateway::linkPath( '/var/../settings/site.ini', '', $others ), 'never outside the root' );
        $this->assertSame( 'edit', expwtAccess::functionFor( 'seo.check' ) );
        $this->assertFalse( expwtAccess::writes( 'seo.check' ) );
        // the page on the other sites and in its languages (F27): read with expwt/use
        $this->assertSame( 'use', expwtAccess::functionFor( 'page.elsewhere' ) );
        $this->assertFalse( expwtAccess::writes( 'page.elsewhere' ) );
        $this->assertContains( 'sites', expwtState::PANELS );
        $this->assertContains( 'sites', expwtRegistry::CORE_PANELS );
    }

    /** The debug report stays out of the pages of the sidebar's frame (expwtHook::preOutput()); the outer page keeps it. */
    public function testFramePagesAreKnownByTheirAddress()
    {
        foreach ( array( '/site/expwt/field/134/0/eng-US/title', '/expwt/field/1/2/eng-US/body?theme=dark',
                         '/twig/layout/set/expwtframe/content/browse/2', '/site/healthy-eating?in=sidebar', '/site/expwt/reauth/newsletter.send' ) as $uri )
            $this->assertTrue( expwtHook::framePage( $uri ), $uri );
        foreach ( array( '/site/healthy-eating', '/site/content/edit/134/2/eng-US', '/layout/set/print/healthy-eating',
                         '/site/expwt/api', '/site/my-expwt/field-notes' ) as $uri )
            $this->assertFalse( expwtHook::framePage( $uri ), $uri );
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
        $this->assertNotSame( '', expwtHook::markup( self::hookContext( array( 'anonymous' => true, 'may_use' => true ) ) ), 'decision 8: ShowToAnonymous=enabled (or policy with expwt/use)' );
    }

    // ------------------------------------------------------------------------------- field names and categories

    /** a class attribute as fieldName() and categoryOf() read it */
    private static function classAttribute( $name, $category, $datatypeName = 'Metadata' )
    {
        $datatype = new class( $datatypeName ) {
            private $name;
            public function __construct( $name ) { $this->name = $name; }
            public function attribute( $a ) { return $a === 'information' ? array( 'string' => 'x', 'name' => $this->name ) : null; }
        };
        return new class( $name, $category, $datatype ) {
            private $values;
            private $datatype;
            public function __construct( $name, $category, $datatype ) { $this->values = array( 'name' => $name, 'category' => $category ); $this->datatype = $datatype; }
            public function attribute( $a ) { return isset( $this->values[$a] ) ? $this->values[$a] : null; }
            public function dataType() { return $this->datatype; }
        };
    }

    public function testAFieldWithoutANameShowsItsDatatypesName()
    {
        $this->assertSame( 'Meta Data', expwtGateway::fieldName( self::classAttribute( 'Meta Data', 'meta' ), 'metadata' ) );
        $this->assertSame( 'Metadata', expwtGateway::fieldName( self::classAttribute( '', 'meta' ), 'metadata' ), 'a class attribute added by a script, with no name' );
        $this->assertSame( 'metadata', expwtGateway::fieldName( self::classAttribute( ' ', 'meta', '' ), 'metadata' ), 'the identifier at last' );
        $this->assertSame( 'metadata', expwtGateway::fieldName( null, 'metadata' ) );
    }

    public function testTheCategoryOfAFieldIsOneOfTheList()
    {
        if ( !class_exists( 'eZINI' ) )
            $this->markTestSkipped( 'the kernel is not loaded' );
        $default = (string)eZINI::instance( 'content.ini' )->variable( 'ClassAttributeSettings', 'DefaultCategory' );
        $this->assertSame( 'meta', expwtGateway::categoryOf( self::classAttribute( 'Meta Data', 'meta' ) ) );
        $this->assertSame( $default, expwtGateway::categoryOf( self::classAttribute( 'Title', '' ) ), 'none: the default' );
        $this->assertSame( $default, expwtGateway::categoryOf( self::classAttribute( 'Title', 'nonexistent' ) ), 'not in CategoryList: the default' );
        $categories = expwtGateway::categories();
        $this->assertSame( $default, $categories[0]['id'] );
        $this->assertTrue( $categories[0]['default'] );
        $this->assertContains( 'meta', array_column( $categories, 'id' ) );
    }

    public function testTheSearchesOfAPageAreThoseWithAWordOfItsName()
    {
        $log = array( array( 'phrase' => 'healthy', 'phrase_count' => 1530, 'result_count' => 30 ),
                      array( 'phrase' => 'news', 'phrase_count' => 464, 'result_count' => 4 ),
                      array( 'phrase' => 'Vegetables soup', 'phrase_count' => 12, 'result_count' => 2.6 ),
                      array( 'phrase' => 'eat', 'phrase_count' => 9, 'result_count' => 1 ),
                      array( 'phrase' => 'vegetablesoup', 'phrase_count' => 3, 'result_count' => 0 ) );
        $this->assertSame( array( array( 'phrase' => 'Vegetables soup', 'searches' => 12, 'results' => 3 ),
                                  array( 'phrase' => 'eat', 'searches' => 9, 'results' => 1 ) ),
                           expwtGateway::searchesFor( 'You Should Eat More Vegetables', $log ),
                           'whole words of three letters or more, any case; most searched first' );
        $this->assertSame( array(), expwtGateway::searchesFor( 'Ab', $log ) );
        $this->assertSame( 'insights', expwtAccess::functionFor( 'insights.load' ) );
        $this->assertFalse( expwtAccess::writes( 'insights.load' ) );
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
