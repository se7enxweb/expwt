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
        // a share link to a draft and the fields as a file ask as the administration's exports do (E13)
        $this->assertSame( 'export', expwtReauthTriggerHandler::groupOf( 'share.create', $settings ) );
        $this->assertSame( 'export', expwtReauthTriggerHandler::groupOf( 'export.fields', $settings ) );
        $this->assertNull( expwtReauthTriggerHandler::groupOf( 'share.load', $settings ) );
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

    /** E4: what a moderation does, the subject of a reply, a comment's text; every refusal is translated */
    public function testTheCommentsOfThePage()
    {
        $this->assertSame( 'reveal', expwtGateway::commentStep( 'approve', true ) );
        $this->assertNull( expwtGateway::commentStep( 'approve', false ), 'an approved comment approved again: nothing to do' );
        $this->assertSame( 'hide', expwtGateway::commentStep( 'hide', false ) );
        $this->assertNull( expwtGateway::commentStep( 'hide', true ) );
        $this->assertSame( 'trash', expwtGateway::commentStep( 'spam', false ) );
        $this->assertSame( 'trash', expwtGateway::commentStep( 'spam', true ) );
        $this->assertNull( expwtGateway::commentStep( 'remove', false ) );

        $this->assertSame( 'Re: Great article', expwtGateway::replySubject( 'Great article', 'Healthy eating' ) );
        $this->assertSame( 'Re: Great article', expwtGateway::replySubject( 'Re: Great article', 'Healthy eating' ), 'once' );
        $this->assertSame( 'RE: shouting', expwtGateway::replySubject( 'RE: shouting', 'x' ) );
        $this->assertSame( 'Re: Healthy eating', expwtGateway::replySubject( '  ', 'Healthy eating' ), 'a reply on the page' );
        $this->assertSame( 255, mb_strlen( expwtGateway::replySubject( str_repeat( 'ü', 400 ), '' ), 'UTF-8' ) );

        $this->assertSame( "Line one\nline two", expwtGateway::commentText( "  Line one\r\nline\x07 two\x00  " ) );
        $this->assertSame( 5000, mb_strlen( expwtGateway::commentText( str_repeat( 'é', 6000 ) ), 'UTF-8' ) );

        foreach ( array( 'There are no comments here.', 'This comment is not there any more, or you may not read it.', 'There is no such decision.',
                         'Write the reply first.', '%n comments wait for moderation' ) as $text )
            $this->assertContains( $text, expwtStrings::SOURCES, 'translated' );
        $this->assertArrayNotHasKey( 'comments.spam', expwtAccess::REAUTH_GROUPS, 'spam asks as the administration\'s removal asks' );
    }

    /** E5: the not-found log read for the page, its addresses as typed, the wildcards that lead to it */
    public function testTheAddressesOfThePage()
    {
        $lines = array(
            '[ Oct 31 2026 16:40:40 ] [203.0.x.x] 404 GET /site/old-section/running-free?utm=*** siteaccess=site reason=module:old-section node=- referrer=- ua=- ""',
            '[ Oct 31 2026 16:41:40 ] [203.0.x.x] 404 GET /site/old-section/running-free siteaccess=site reason=module:old-section node=- referrer=- ua=- ""',
            '[ Oct 31 2026 16:42:40 ] [198.51.x.x] 410 GET /smarty/blog/Running-Free.html siteaccess=smarty reason=kernel:kernel/20 node=- referrer=- ua=- ""',
            '[ Oct 31 2026 16:43:40 ] [198.51.x.x] 404 GET /site/unrelated siteaccess=site reason=module:unrelated node=- referrer=- ua=- ""',
            '[ Oct 31 2026 16:44:40 ] [198.51.x.x] 404 POST /site/old-section/running-free siteaccess=site reason=module:x node=- referrer=- ua=- ""',
            '[ Oct 31 2026 16:45:40 ] [198.51.x.x] 404 GET /fitness/running-free siteaccess=- reason=module:x node=- referrer=- ua=- ""',
            'not a line of the log',
        );
        $counts = expwtGateway::notFoundCounts( $lines );
        $this->assertSame( array( 'count' => 2, 'last' => 'Oct 31 2026 16:41:40' ), $counts['/old-section/running-free'], 'without the siteaccess and the query; POST is not a link' );
        $this->assertSame( 1, $counts['/blog/Running-Free.html']['count'], '410 too' );
        $this->assertArrayHasKey( '/unrelated', $counts );
        $this->assertArrayHasKey( '/fitness/running-free', $counts );
        foreach ( $counts as $path => $seen )
            $this->assertStringNotContainsString( '203.0', $path . json_encode( $seen ), 'no visitor addresses' );
        $broken = expwtGateway::brokenFor( $counts, array( 'running-free' ), array( '/fitness/running-free' ) );
        $this->assertSame( array( '/old-section/running-free', '/blog/Running-Free.html' ), array_keys( $broken ), 'like the page, not the page itself, most often first' );
        $this->assertSame( 'running-free', expwtGateway::slug( 'Running_Free.HTML' ) );
        $this->assertSame( 'über-uns', expwtGateway::slug( 'Über uns' ) );

        $this->assertSame( 'promo/run', expwtGateway::addressInput( ' /promo//run/ ' ) );
        $this->assertSame( 'promo/run', expwtGateway::addressInput( 'https://alpha.se7enx.com/promo/run?x=1#top' ) );
        $this->assertSame( 'old/*', expwtGateway::addressInput( '/old/*' ) );
        foreach ( array( '', '  / ', '../etc', 'a/../../b', "a\x00b", str_repeat( 'a', 301 ) ) as $bad )
            $this->assertNull( expwtGateway::addressInput( $bad ), json_encode( $bad ) );

        $this->assertTrue( expwtGateway::wildcardLeadsTo( 'fitness/running-free', array( '/fitness/running-free' ), 128 ) );
        $this->assertTrue( expwtGateway::wildcardLeadsTo( '/Fitness/Running-Free/?x', array( '/fitness/running-free' ), 128 ) );
        $this->assertTrue( expwtGateway::wildcardLeadsTo( 'content/view/full/128', array(), 128 ) );
        $this->assertFalse( expwtGateway::wildcardLeadsTo( 'fitness/running-free-2', array( '/fitness/running-free' ), 128 ) );
        $this->assertFalse( expwtGateway::wildcardLeadsTo( 'content/view/full/1280', array(), 128 ) );

        $this->assertSame( '/fitness/run', expwtGateway::withoutPrefix( 'fit-healthy/fitness/run', 'fit-healthy' ) );
        $this->assertSame( '/fit-healthy-old/run', expwtGateway::withoutPrefix( '/fit-healthy-old/run', 'fit-healthy' ) );
        $this->assertSame( '/fitness/run', expwtGateway::withoutPrefix( 'fitness/run', '' ) );
        $this->assertContains( 'urls', expwtState::PANELS );
        $this->assertContains( 'urls', expwtRegistry::CORE_PANELS );
    }

    /** E6: the calendar's scope, its stretch of time, the layout variants' time conditions read and moved */
    public function testTheCalendar()
    {
        $this->assertSame( 'page', expwtCalendar::scope( 'everything' ) );
        $this->assertSame( 'site', expwtCalendar::scope( 'site' ) );
        $now = mktime( 15, 20, 0, 11, 10, 2026 );
        $w = expwtCalendar::window( 0, 0, $now );
        $this->assertSame( mktime( 0, 0, 0, 11, 10, 2026 ), $w['from'], 'from the start of today' );
        $this->assertSame( $w['from'] + 42 * 86400 - 1, $w['to'] );
        $w = expwtCalendar::window( 1000, 1000 + 400 * 86400, $now );
        $this->assertSame( 1000 + expwtCalendar::MAX_DAYS * 86400, $w['to'], 'never more than a month grid and a little' );

        $this->assertTrue( expwtCalendar::inScope( 'page', 128, array( '/1/2/59/128/' ) ) );
        $this->assertFalse( expwtCalendar::inScope( 'page', 128, array( '/1/2/128/130/' ) ), 'a page below is not the page' );
        $this->assertTrue( expwtCalendar::inScope( 'subtree', 128, array( '/1/2/77/', '/1/2/128/130/' ) ), 'any of its places' );
        $this->assertTrue( expwtCalendar::inScope( 'subtree', 128, array( '/1/2/128/0/' ) ), 'a new page to be placed below it' );
        $this->assertFalse( expwtCalendar::inScope( 'subtree', 12, array( '/1/2/128/' ) ), 'node 12 is not node 128' );
        $this->assertTrue( expwtCalendar::inScope( 'site', 128, array() ) );

        $this->assertTrue( expwtCalendar::targetInScope( 'page', 128, '/1/2/59/128/', 'node', '/1/2/59/128/' ) );
        $this->assertTrue( expwtCalendar::targetInScope( 'page', 128, '/1/2/59/128/', 'subtree', '/1/2/59/' ), 'a subtree above covers the page' );
        $this->assertFalse( expwtCalendar::targetInScope( 'page', 128, '/1/2/59/128/', 'node', '/1/2/59/128/130/' ) );
        $this->assertTrue( expwtCalendar::targetInScope( 'subtree', 128, '/1/2/59/128/', 'node', '/1/2/59/128/130/' ) );
        $this->assertFalse( expwtCalendar::targetInScope( 'subtree', 128, '/1/2/59/128/', 'node', '/1/2/60/' ) );
        $this->assertNull( expwtCalendar::targetInScope( 'page', 128, '/1/2/59/128/', 'path_prefix', '' ), 'the resolver decides' );
        $this->assertFalse( expwtCalendar::targetInScope( 'page', 128, '/1/2/59/128/', 'node', '' ), 'a node that is not there' );
        $this->assertTrue( expwtCalendar::targetInScope( 'site', 128, '', 'path', '' ) );

        $text = json_encode( array( 'from' => '2026-11-20 08:00:00', 'to' => '2026-11-27 20:00:00', 'note' => 'x' ) );
        $range = expwtCalendar::timeRange( $text );
        $this->assertSame( array( 'from' => mktime( 8, 0, 0, 11, 20, 2026 ), 'to' => mktime( 20, 0, 0, 11, 27, 2026 ), 'form' => 'text' ), $range );
        $moved = expwtCalendar::moveRange( $text, 'start', mktime( 8, 0, 0, 11, 22, 2026 ) );
        $this->assertNull( $moved['error'] );
        $this->assertSame( array( 'from' => '2026-11-22 08:00:00', 'to' => '2026-11-29 20:00:00', 'note' => 'x' ), json_decode( $moved['value'], true ), 'the end moves with the start, other keys stay' );
        $moved = expwtCalendar::moveRange( $text, 'end', mktime( 9, 0, 0, 11, 21, 2026 ) );
        $this->assertSame( '2026-11-20 08:00:00', json_decode( $moved['value'], true )['from'], 'only the end' );
        $this->assertSame( 'end_before_start', expwtCalendar::moveRange( $text, 'end', mktime( 7, 0, 0, 11, 20, 2026 ) )['error'] );
        $parts = json_encode( array( 'from' => array( 'year' => 2026, 'month' => 12, 'day' => 1 ) ) );
        $moved = expwtCalendar::moveRange( $parts, 'start', mktime( 6, 30, 0, 12, 3, 2026 ) );
        $this->assertSame( array( 'year' => 2026, 'month' => 12, 'day' => 3, 'hour' => 6, 'minute' => 30, 'second' => 0 ), json_decode( $moved['value'], true )['from'], 'parts stay parts' );
        $this->assertSame( 'no_end', expwtCalendar::moveRange( $parts, 'end', $now )['error'] );
        $until = json_encode( array( 'to' => '2026-12-24 18:00' ) );
        $this->assertSame( '2026-12-26 18:00:00', json_decode( expwtCalendar::moveRange( $until, 'start', mktime( 18, 0, 0, 12, 26, 2026 ) )['value'], true )['to'], 'a range with only an end moves its end' );
        foreach ( array( '', 'nonsense', '{}', '{"from":"not a date"}', '{"from":{"year":2026}}', '{"from":{"datetime":"2026-11-20 08:00","timezone":"UTC"}}' ) as $bad )
            $this->assertNull( expwtCalendar::timeRange( $bad ), $bad );
        $this->assertSame( 'unreadable', expwtCalendar::moveRange( 'x', 'start', $now )['error'] );

        $w = array( 'from' => 100, 'to' => 200 );
        $this->assertTrue( expwtCalendar::touches( 50, 150, $w ) );
        $this->assertTrue( expwtCalendar::touches( null, 200, $w ) );
        $this->assertFalse( expwtCalendar::touches( 50, 300, $w ), 'only its start and its end are marked' );

        $sorted = expwtCalendar::sorted( array( array( 'at' => 5, 'kind' => 'takedown', 'title' => 'B' ), array( 'at' => 5, 'kind' => 'publish', 'title' => 'Z' ),
                                                array( 'at' => 1, 'kind' => 'variant-end', 'title' => 'A' ) ) );
        $this->assertSame( array( 'A', 'Z', 'B' ), array_column( $sorted, 'title' ) );

        $this->assertSame( 'bad_value', expwtCalendar::moveError( null, $now ) );
        $this->assertSame( 'past', expwtCalendar::moveError( $now, $now ) );
        $this->assertNull( expwtCalendar::moveError( $now + 60, $now ) );
        $this->assertContains( 'calendar', expwtState::PANELS );
        $this->assertContains( 'calendar', expwtRegistry::CORE_PANELS );
    }

    /** E6: the month grid and the moves the panel sends (tests/js/calendar.test.js, Node) */
    public function testTheCalendarGrid()
    {
        $node = trim( (string)shell_exec( 'command -v node 2>&1' ) );
        if ( $node === '' || !is_executable( $node ) )
            $this->markTestSkipped( 'Node is not installed' );
        exec( escapeshellarg( $node ) . ' ' . escapeshellarg( dirname( __DIR__, 2 ) . '/js/calendar.test.js' ) . ' 2>&1', $out, $status );
        $this->assertSame( 0, $status, implode( "\n", $out ) );
        $this->assertStringStartsWith( 'PASS', (string)end( $out ) );
    }

    /** E7: the focal point and crop the browser sends, and the aliases an image field previews */
    public function testTheFocalPointValuesAndTheAliasesPreviewed()
    {
        $this->assertSame( array( 'x' => 0.25, 'y' => 1.0 ), expwtImages::point( array( 'x' => '0.25', 'y' => 1 ) ) );
        $this->assertNull( expwtImages::point( null ) );
        $this->assertNull( expwtImages::point( '' ) );
        foreach ( array( array( 'x' => 2, 'y' => 0 ), array( 'x' => 0.5 ), array( 'x' => 'a', 'y' => 0 ), 'middle', array( 'x' => -0.1, 'y' => 0 ) ) as $bad )
            $this->assertFalse( expwtImages::point( $bad ), var_export( $bad, true ) );
        $this->assertSame( array( 'x' => 0.1, 'y' => 0.0, 'width' => 0.5, 'height' => 0.75 ),
                           expwtImages::rectangle( array( 'x' => 0.1, 'y' => '0', 'width' => 0.5, 'height' => 0.75 ) ) );
        $this->assertNull( expwtImages::rectangle( array( 'x' => 0, 'y' => 0, 'width' => 1, 'height' => 1 ) ), 'the whole image is none' );
        $this->assertNull( expwtImages::rectangle( null ) );
        foreach ( array( array( 'x' => 0.6, 'y' => 0, 'width' => 0.5, 'height' => 1 ), array( 'x' => 0, 'y' => 0, 'width' => 0.001, 'height' => 1 ),
                         array( 'x' => 0, 'y' => 0, 'width' => 0.5 ), 'invalid' ) as $bad )
            $this->assertFalse( expwtImages::rectangle( $bad ), var_export( $bad, true ) );

        if ( !class_exists( 'expImageFocalPoint' ) )
            $this->markTestSkipped( 'The kernel has no focal points (Exponential 6.0.15 has them)' );
        $this->assertTrue( expwtImages::available() );
        $f = function ( $name, $data ) { return array( 'name' => $name, 'data' => $data ); };
        $aliasList = array(
            'original' => array( 'reference' => false, 'filters' => array() ),
            'small' => array( 'reference' => false, 'filters' => array( $f( 'geometry/scaledownonly', array( 100, 100 ) ) ) ),
            'unused' => array( 'reference' => false, 'filters' => array( $f( 'geometry/scaledownonly', array( 50, 50 ) ) ) ),
            'reference' => array( 'reference' => false, 'filters' => array( $f( 'geometry/scaledownonly', array( 600, 600 ) ) ) ),
            'teaser' => array( 'reference' => 'reference', 'filters' => array( $f( 'geometry/crop', array( 300, 200, 0, 0 ) ) ) ),
        );
        $this->assertSame( array( 'small', 'teaser' ), expwtImages::relevantAliases( $aliasList, array( 'original', 'small' ) ),
                           'the aliases made, and those that crop; never the original' );
        $this->assertSame( array( array( 'name' => 'geometry/scaledownonly', 'data' => array( '600', '600' ) ),
                                  array( 'name' => 'geometry/crop', 'data' => array( '300', '200', '0', '0' ) ) ),
                           expwtImages::chain( $aliasList, 'teaser' ), 'the filters from the original, through the reference' );
        $this->assertTrue( expwtImages::crops( expwtImages::chain( $aliasList, 'teaser' ) ) );
        $this->assertFalse( expwtImages::crops( expwtImages::chain( $aliasList, 'small' ) ) );
        $many = array();
        for ( $i = 0; $i < 40; $i++ )
            $many['a' . $i] = array( 'reference' => false, 'filters' => array( $f( 'geometry/crop', array( 10, 10, 0, 0 ) ) ) );
        $this->assertCount( expwtImages::MAX_ALIASES, expwtImages::relevantAliases( $many, array() ) );
    }

    /** E7: the live previews compute what the kernel computes (tests/js/focal.test.js, Node) */
    public function testTheFocalPointPreviews()
    {
        $node = trim( (string)shell_exec( 'command -v node 2>&1' ) );
        if ( $node === '' || !is_executable( $node ) )
            $this->markTestSkipped( 'Node is not installed' );
        exec( escapeshellarg( $node ) . ' ' . escapeshellarg( dirname( __DIR__, 2 ) . '/js/focal.test.js' ) . ' 2>&1', $out, $status );
        $this->assertSame( 0, $status, implode( "\n", $out ) );
        $this->assertStringStartsWith( 'PASS', (string)end( $out ) );
    }

    /** E5: what the panel sends as an address (tests/js/urls.test.js, Node) */
    public function testTheAddressesAsTyped()
    {
        $node = trim( (string)shell_exec( 'command -v node 2>&1' ) );
        if ( $node === '' || !is_executable( $node ) )
            $this->markTestSkipped( 'Node is not installed' );
        exec( escapeshellarg( $node ) . ' ' . escapeshellarg( dirname( __DIR__, 2 ) . '/js/urls.test.js' ) . ' 2>&1', $out, $status );
        $this->assertSame( 0, $status, implode( "\n", $out ) );
        $this->assertStringStartsWith( 'PASS', (string)end( $out ) );
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
                                  'mode' => 'overlay', 'theme' => 'dark', 'labels' => '', 'panel' => 'me', 'keys' => array() ), $state );
        $bad = expwtState::normalize( array( 'edge' => 'middle', 'align' => 'center', 'height' => 99999 ), $s );
        $this->assertSame( array( 'right', 'end', 640 ), array( $bad['edge'], $bad['align'], $bad['height'] ) );
        $this->assertSame( expwtState::defaults( $s ), expwtState::normalize( 'nonsense', $s ) );
        $this->assertSame( expwtState::defaults( $s ), expwtState::decode( '{broken', $s ) );
        $this->assertFalse( expwtState::defaults( $s )['open'], 'decision 4: closed on the first visit' );
    }

    /** The panel pushed last is kept whatever it is: every panel of the sidebar and an extension's panel, through a save */
    public function testStateKeepsEveryPanelPushedLast()
    {
        $s = self::settings();
        foreach ( array_merge( expwtState::PANELS, array( 'shop', 'my_panel2' ) ) as $panel )
        {
            $saved = expwtState::normalize( array( 'panel' => $panel ), $s );
            $this->assertSame( $panel, expwtState::decode( json_encode( $saved ), $s )['panel'], $panel );
        }
        foreach ( array( '', 'Me', '1me', 'me-too', '<x>', str_repeat( 'a', 41 ), array( 'me' ) ) as $bad )
            $this->assertSame( 'page', expwtState::normalize( array( 'panel' => $bad ), $s )['panel'], json_encode( $bad ) );
    }

    /** The panel pushed last is open again after a reload (tests/js/panelmemory.test.js, Node) */
    public function testThePanelPushedLastIsOpenAgainAfterAReload()
    {
        $node = trim( (string)shell_exec( 'command -v node 2>&1' ) );
        if ( $node === '' || !is_executable( $node ) )
            $this->markTestSkipped( 'Node is not installed' );
        exec( escapeshellarg( $node ) . ' ' . escapeshellarg( dirname( __DIR__, 2 ) . '/js/panelmemory.test.js' ) . ' 2>&1', $out, $status );
        $this->assertSame( 0, $status, implode( "\n", $out ) );
        $this->assertStringStartsWith( 'PASS', (string)end( $out ) );
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

    /** E10 and E11: the branding in the browser and the findings of the accessibility check (tests/js, Node) */
    public function testTheBrandingAndTheAccessibilityCheckInTheBrowser()
    {
        $node = trim( (string)shell_exec( 'command -v node 2>&1' ) );
        if ( $node === '' || !is_executable( $node ) )
            $this->markTestSkipped( 'Node is not installed' );
        foreach ( array( 'brand.test.js', 'a11y.test.js' ) as $script )
        {
            $out = array();
            exec( escapeshellarg( $node ) . ' ' . escapeshellarg( dirname( __DIR__, 2 ) . '/js/' . $script ) . ' 2>&1', $out, $status );
            $this->assertSame( 0, $status, implode( "\n", $out ) );
            $this->assertStringStartsWith( 'PASS', (string)end( $out ), $script );
        }
    }

    /** E11: axe-core is shipped as published (4.11.3, MPL-2.0, its notice kept) and only the SEO panel loads it */
    public function testAxeCoreIsShippedUnchangedAndLoadedOnDemand()
    {
        $root = dirname( __DIR__, 3 );
        $axe = $root . '/design/standard/javascript/expwt-axe.min.js';
        $this->assertSame( 'aa3d175a5355071c889fe0dabe2e6b1dbe82f986', sha1_file( $axe ), 'axe-core 4.11.3 from the npm registry, unchanged' );
        $this->assertStringStartsWith( "/*! axe v4.11.3\n", (string)file_get_contents( $axe, false, null, 0, 20 ) );
        $panels = (string)file_get_contents( $root . '/design/standard/javascript/expwt-panels.js' );
        $this->assertStringContainsString( 'ctx.boot.urls.axe', $panels );
        $this->assertStringNotContainsString( 'expwt-axe', (string)file_get_contents( $root . '/design/standard/javascript/expwt.js' ), 'not in the closed tab' );
        $this->assertStringContainsString( 'axe-core', (string)file_get_contents( $root . '/README.md' ), 'named with its license' );
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

    /** E4: the buttons of a comment in the Page panel (tests/js/comments.test.js, Node) */
    public function testTheButtonsOfAComment()
    {
        $node = trim( (string)shell_exec( 'command -v node 2>&1' ) );
        if ( $node === '' || !is_executable( $node ) )
            $this->markTestSkipped( 'Node is not installed' );
        exec( escapeshellarg( $node ) . ' ' . escapeshellarg( dirname( __DIR__, 2 ) . '/js/comments.test.js' ) . ' 2>&1', $out, $status );
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

    /** R4.5: what the Migrations panel shows of a status report (exp-migration/1), and nothing more */
    public function testTheMigrationsViewOfAStatusReport()
    {
        $report = array(
            'schema' => 'exp-migration/1', 'mode' => 'status', 'table' => true, 'errors' => array( 'x: broken' ),
            'migrations' => array(
                array( 'id' => '20261031120000_add_news', 'source' => 'site', 'description' => 'Adds the news class', 'file' => 'migrations/a.php', 'state' => 'pending', 'record' => null ),
                array( 'id' => '20261030120000_old', 'source' => 'site', 'description' => '', 'state' => 'done', 'record' => array( 'state' => 'done', 'run_id' => 'r1' ) ),
                array( 'id' => '20261029120000_bad', 'source' => 'extension:shop', 'description' => 'Breaks', 'state' => 'failed', 'record' => array( 'state' => 'failed', 'run_id' => '20261031-1/x' ) ),
                array( 'id' => '6.0.15-x', 'source' => 'kernel', 'description' => 'Kernel step', 'state' => 'not-needed', 'record' => null ),
            ),
            'orphans' => array( array( 'id' => 'gone', 'source' => 'site', 'state' => 'done', 'file' => '/secret/path.php', 'run_id' => 'r0', 'finished_at' => null ) ),
            'runs' => array( array( 'run_id' => 'r2', 'state' => 'failed', 'started_at' => '2026-10-31T10:00:00+00:00', 'finished_at' => '2026-10-31T10:01:00+00:00',
                                    'run_by' => 'admin', 'environment' => 'prod', 'caption_pre' => 'pre-r2', 'caption_post' => '', 'commit_id' => 'abc',
                                    'tag_name' => 'migrations/prod/r2', 'push_state' => 'pending', 'message' => str_repeat( 'e', 400 ) ) ),
            'lock' => array( 'name' => 'upgrade', 'purpose' => 'migration run r3', 'user' => 'admin', 'pid' => 4242, 'host' => 'db1', 'started' => 1793000000 ),
        );
        $v = expwtGateway::migrationsView( $report, '/admin/' );
        $this->assertSame( array( array( 'id' => '20261031120000_add_news', 'source' => 'site', 'description' => 'Adds the news class', 'url' => '/admin/migration/status' ) ), $v['pending'] );
        $this->assertSame( '/admin/migration/log/20261031-1%2Fx', $v['failed'][0]['url'], 'a failed one links to the log of its run' );
        $this->assertSame( array( array( 'id' => 'gone', 'source' => 'site', 'state' => 'done', 'url' => '/admin/migration/log/r0' ) ), $v['orphans'], 'no file path' );
        $this->assertSame( 3, $v['attention'] );
        $this->assertSame( 1, $v['problems'] );
        $this->assertSame( 'pre-r2', $v['last']['caption'], 'the caption after the run, else the one before' );
        $this->assertSame( 'migrations/prod/r2', $v['last']['tag'] );
        $this->assertSame( 'pending', $v['last']['push'] );
        $this->assertSame( 300, strlen( $v['last']['message'] ) );
        $this->assertSame( '/admin/migration/log/r2', $v['last']['url'] );
        $this->assertSame( array( 'purpose', 'by', 'started', 'url' ), array_keys( $v['lock'] ), 'no process id, no host' );
        $this->assertSame( date( 'c', 1793000000 ), $v['lock']['started'] );

        $none = expwtGateway::migrationsView( array( 'table' => false, 'migrations' => array(), 'runs' => array(), 'lock' => null ), null );
        $this->assertSame( array(), $none['pending'] );
        $this->assertNull( $none['last'] );
        $this->assertNull( $none['lock'] );
        $this->assertFalse( $none['table'] );
        $this->assertSame( 0, $none['attention'] );
        $this->assertNull( $none['url'], 'no links without an administration' );

        $many = array( 'migrations' => array() );
        for ( $i = 0; $i < expwtGateway::MIGRATIONS_LISTED + 5; $i++ )
            $many['migrations'][] = array( 'id' => "m$i", 'source' => 'site', 'description' => '', 'state' => 'pending' );
        $v = expwtGateway::migrationsView( $many, '/admin/' );
        $this->assertCount( expwtGateway::MIGRATIONS_LISTED, $v['pending'] );
        $this->assertSame( 5, $v['more'] );
        $this->assertSame( expwtGateway::MIGRATIONS_LISTED + 5, $v['attention'] );
    }

    /** The deploys of the Migrations panel (WP18): the kernel's words, kinds, links; never an address. */
    public function testDeploysView()
    {
        $history = array(
            array( 'deploy_id' => '20261102-120000-a1b2c3', 'mode' => 'apply', 'state' => 'rolled-back', 'in_sync' => true, 'started_at' => '2026-11-02T12:00:00+00:00',
                   'remotes' => array( 'stage' => 'rolled-back', 'prod' => 'restored' ), 'user' => 'admin', 'address' => 'ssh://deploy@node1:22/srv' ),
            array( 'deploy_id' => '20261101-090000-d4e5f6', 'mode' => 'transfer-only', 'state' => 'out-of-sync', 'in_sync' => false, 'started_at' => null,
                   'remotes' => array( 'ftp1' => 'out-of-sync' ), 'user' => '' ),
        );
        $tr = function ( $text ) { return "[$text]"; };
        $v = expwtGateway::deploysView( $history, '/admin/', $tr );
        $this->assertCount( 2, $v['items'] );
        $first = $v['items'][0];
        $this->assertSame( array( 'id', 'state', 'text', 'kind', 'in_sync', 'transfer_only', 'remotes', 'by', 'started', 'url' ), array_keys( $first ), 'no address' );
        $this->assertSame( '[rolled back]', $first['text'] );
        $this->assertSame( 'ok', $first['kind'] );
        $this->assertSame( array( 'id' => 'prod', 'state' => 'restored', 'text' => '[failed, restored itself]', 'kind' => 'warn' ), $first['remotes'][1] );
        $this->assertSame( '/admin/migration/deployment/20261102-120000-a1b2c3', $first['url'] );
        $this->assertTrue( $v['items'][1]['transfer_only'] );
        $this->assertSame( 'bad', $v['items'][1]['kind'] );
        $this->assertSame( '[Deploys]', $v['labels']['title'] );
        $this->assertSame( '/admin/migration/deploy', $v['url'] );
        $none = expwtGateway::deploysView( array(), null, $tr );
        $this->assertSame( array(), $none['items'] );
        $this->assertNull( $none['url'] );
    }

    /** Without expMigrationStatus (a kernel before 6.0.15) the panel is never offered */
    public function testNoMigrationsPanelWithoutTheKernelsMigrations()
    {
        if ( class_exists( 'expMigrationStatus' ) )
            $this->markTestSkipped( 'this kernel has its migrations' );
        $this->assertFalse( expwtGateway::migrationKernel() );
    }

    /** WP13: what the sidebar shows of the recordings that run for the user, and nothing when recording is off */
    public function testTheRecordingViewOfTheUsersRecordings()
    {
        $this->assertNull( expwtGateway::recordingView( false, array( 'mine' => array( 'recording_id' => 'r', 'started_at' => 1 ), 'site' => null ) ),
                           'switched off: not offered' );
        $this->assertSame( array( 'active' => false, 'since' => null, 'site' => false ), expwtGateway::recordingView( true, array( 'mine' => null, 'site' => null ) ) );
        $this->assertSame( array( 'active' => true, 'since' => date( 'c', 1793000000 ), 'site' => false ),
                           expwtGateway::recordingView( true, array( 'mine' => array( 'recording_id' => '20261101-120000-a1b2c3', 'kind' => 'session', 'user_id' => 14, 'started_at' => 1793000000 ), 'site' => null ) ),
                           'no recording id, no user id' );
        $this->assertSame( array( 'active' => false, 'since' => null, 'site' => true ),
                           expwtGateway::recordingView( true, array( 'mine' => null, 'site' => array( 'recording_id' => 'x', 'started_at' => 5 ) ) ),
                           'the site-wide window is only shown' );
        $this->assertSame( array( 'active' => false, 'since' => null, 'site' => false ), expwtGateway::recordingView( true, array() ) );
    }

    /**
     * Without expMigrationRecording (a kernel before the recording of changes) nothing about recording is offered: the
     * sidebar degrades to the read-only panel, and no write reaches the kernel.
     */
    public function testNoRecordingWithoutTheKernelsRecording()
    {
        if ( class_exists( 'expMigrationRecording' ) )
            $this->markTestSkipped( 'this kernel can record changes' );
        $this->assertFalse( expwtGateway::recordingKernel() );
        $gateway = new class extends expwtGateway {
            public function __construct() {}
            public function capabilities() { return array_fill_keys( expwtAccess::FUNCTIONS, true ); }
            protected function may( $module, $function ) { return true; }
        };
        $this->assertFalse( $gateway->recordsMigrations() );
        $this->assertNull( $gateway->recording() );
        try
        {
            $gateway->recordStart( array() );
            $this->fail( 'refused' );
        }
        catch ( expwtApiError $e )
        {
            $this->assertSame( 403, $e->status );
        }
        try
        {
            $gateway->recordStop( array() );
            $this->fail( 'refused' );
        }
        catch ( expwtApiError $e )
        {
            $this->assertSame( 403, $e->status );
        }
    }

    /** E8: the devices of the preview from the settings, what the preview gets, and its frames without a sidebar */
    public function testTheDevicesOfThePreview()
    {
        $shipped = self::settings( array() );
        $this->assertSame( array( 'iphone13', 'pixel7', 'tablet', 'zoom200', 'desktop' ), array_column( $shipped['devices'], 'id' ) );
        $this->assertSame( array( 'id' => 'zoom200', 'name' => '1920 wide at 200% zoom', 'width' => 960, 'height' => 900 ), $shipped['devices'][3] );
        $this->assertSame( 1, preg_match( '/^\[DevicePreviewSettings\]$(.*?)^\[/ms', file_get_contents( dirname( __DIR__, 3 ) . '/settings/expwt.ini' ), $group ) );
        preg_match_all( '/^#DeviceList\[\]=(.+)$/m', $group[1], $lines );
        $lines = $lines[1];
        $this->assertSame( $shipped['devices'], expwtSettings::devices( $lines ), 'expwt.ini ships the defaults' );

        $d = expwtSettings::devices( array( 'a;A;390;844', 'a;Again;400;800', 'b;B;239;800', 'c;C;2561;800', 'd;D;800;2401', 'e;;800;800',
                                            'F;F;800;800', 'g;G;80O;800', 'h;H;800', 'i; Wide ;2560;240', '' ) );
        $this->assertSame( array( array( 'id' => 'a', 'name' => 'A', 'width' => 390, 'height' => 844 ),
                                  array( 'id' => 'i', 'name' => 'Wide', 'width' => 2560, 'height' => 240 ) ), $d, 'each id once, sizes within the limits' );
        $s = self::settings( array( 'devices' => array( 'a;A;390;844', 'b;B;800;600' ), 'devices_shown' => array( 'b', 'x', 'b' ) ) );
        $this->assertSame( array( 'b' ), $s['devices_shown'], 'only devices there are, each once' );

        $tr = function ( $name ) { return $name === 'Tablet' ? 'Tablett' : $name; };
        $v = expwtGateway::devicesView( $shipped, '/site/news/a', null, array( 'version' => 3, 'modified' => 1 ), $tr );
        $this->assertSame( array( 'iphone13', 'pixel7', 'tablet', 'zoom200', 'desktop' ), $v['shown'], 'all of them without ShownDeviceList[]' );
        $this->assertSame( 'Tablett', $v['devices'][2]['name'] );
        $this->assertSame( 820, $v['devices'][2]['width'] );
        $this->assertSame( array( 'published' => '/site/news/a?in=sidebar', 'draft' => null ), $v['urls'] );
        $this->assertNull( $v['draft'], 'no draft, nothing about one' );
        $this->assertSame( array( 'min' => 240, 'max' => 2560, 'height' => 900 ), $v['custom'] );
        $v = expwtGateway::devicesView( $s, '/site/news/a', '/site/content/versionview/7/3/eng-US', array( 'version' => 3, 'modified' => 1 ), $tr );
        $this->assertSame( array( 'b' ), $v['shown'] );
        $this->assertSame( '/site/content/versionview/7/3/eng-US?in=sidebar', $v['urls']['draft'] );
        $this->assertSame( 3, $v['draft']['version'] );
        $this->assertSame( '/a?x=1&in=sidebar#top', expwtGateway::framed( '/a?x=1#top' ) );
        $this->assertTrue( expwtHook::framePage( expwtGateway::framed( '/site/news/a' ) ), 'the frames get no sidebar of their own' );
        foreach ( array( 'Devices', 'Device preview', 'Tablet', 'Desktop', '1920 wide at 200% zoom', 'Frames shown', 'Show all', 'All frames are shown.' ) as $text )
            $this->assertContains( $text, expwtStrings::SOURCES, 'translated' );
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

    /**
     * The shipped expwt.ini sets nothing (every value commented out), because the kernel reads an extension in
     * ActiveExtensions[] after settings/siteaccess/<siteaccess>/: a value set there would win over every siteaccess.
     * Its commented values are the code's defaults.
     */
    public function testTheShippedSettingsSetNothingAndDocumentTheDefaults()
    {
        $text = (string)file_get_contents( dirname( __DIR__, 3 ) . '/settings/expwt.ini' );
        $this->assertSame( 0, preg_match( '/^[A-Za-z][A-Za-z0-9_]*(\[\])?\s*(=|$)/m', $text, $set ), 'expwt.ini sets ' . ( $set ? $set[0] : '' ) );
        // the commented values, read as if they were set
        $groups = array();
        $group = null;
        foreach ( preg_split( '/\R/', $text ) as $line )
        {
            if ( preg_match( '/^\[([A-Za-z0-9_]+)\]\s*$/', $line, $m ) )
                $group = $m[1];
            else if ( $group !== null && preg_match( '/^#([A-Z][A-Za-z0-9]*)(\[\])?(?:=(.*))?$/', $line, $m ) )
            {
                if ( isset( $m[2] ) && $m[2] === '[]' )
                {
                    if ( !isset( $m[3] ) )
                        $groups[$group][$m[1]] = array();
                    else
                        $groups[$group][$m[1]][] = $m[3];
                }
                else if ( isset( $m[3] ) )
                    $groups[$group][$m[1]] = $m[3];
            }
        }
        $ini = new class( $groups ) {
            private $g;
            public function __construct( array $g ) { $this->g = $g; }
            public function hasVariable( $group, $name ) { return isset( $this->g[$group] ) && array_key_exists( $name, $this->g[$group] ); }
            public function variable( $group, $name ) { return $this->g[$group][$name]; }
        };
        $this->assertSame( self::settings( array() ), expwtSettings::fromIni( $ini ), 'the commented values are the defaults' );
        $this->assertSame( 'disabled', $groups['Comments']['HideNew'] );
        $this->assertSame( expwtBranding::fromArray( array() ), expwtBranding::fromIni( $ini ), 'no branding shipped' );
        $this->assertSame( array(), $groups['RolePanelSettings']['RoleList'], 'no panels per role shipped' );
    }


    /** The tabs' labels: icons, icons and text, text, and the kernel's icons (tests/js/tablabels.test.js, Node) */
    public function testTheTabLabels()
    {
        $node = trim( (string)shell_exec( 'command -v node 2>&1' ) );
        if ( $node === '' || !is_executable( $node ) )
            $this->markTestSkipped( 'Node is not installed' );
        // run from the installation's root: the test checks that the kernel ships every icon
        exec( 'cd ' . escapeshellarg( getcwd() ) . ' && ' . escapeshellarg( $node ) . ' '
              . escapeshellarg( dirname( __DIR__, 2 ) . '/js/tablabels.test.js' ) . ' 2>&1', $out, $status );
        $this->assertSame( 0, $status, implode( "\n", $out ) );
        $this->assertStringStartsWith( 'PASS', (string)end( $out ) );
    }
}
