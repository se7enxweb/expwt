<?php
/**
 * Every action of the sidebar through expwtApi, the code behind all three transports (expwt/api, /api/expwt/v1 and
 * the GraphQL fields): the checks in their order (unknown action 404, a write by GET 405, the expwt policy 403), the
 * input handed to the gateway, the refusals of the gateway and of the kernel's writer, and the boot answer.
 *
 * No database: the gateway answers from arrays and records its calls.
 *
 * Run: php vendor/bin/phpunit extension/expwt/tests/phpunit/unit/expwtApiTest.php
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

require_once dirname( __DIR__ ) . '/bootstrap.php';

class expwtApiTestGateway extends expwtGateway
{
    public $caps;
    public $calls = array();
    public $saved = null;
    public $throw = null;

    public function __construct( array $settings, array $caps )
    {
        parent::__construct( $settings );
        $this->caps = $caps;
    }

    private function record( $name, $args )
    {
        $this->calls[] = array( $name, $args );
        if ( $this->throw )
            throw $this->throw;
        return array( 'called' => $name );
    }

    public function capabilities() { return $this->caps; }
    public function user() { return array( 'id' => 14, 'login' => 'anna', 'name' => 'Anna' ); }
    public function state() { return expwtState::defaults( $this->settings ); }
    public function saveState( array $state ) { $this->saved = $state; }
    public function adminColorMode() { return 'dark'; }
    public function token() { return 'tok'; }
    public function transport() { return array( 'layer' => 'rest', 'rest' => '/expwt/api', 'graphql' => '/graphql/query', 'reauth' => '/expwt/reauth' ); }
    public function urls() { return array( 'logout' => '/user/logout', 'admin' => null ); }
    public function strings() { return array( 'Edit' => 'Bearbeiten' ); }
    public function me() { return $this->record( 'me', array() ); }
    public function page( array $in ) { return $this->record( 'page', $in ); }
    public function saveDraft( array $in ) { return $this->record( 'saveDraft', $in ); }
    public function publish( array $in ) { return $this->record( 'publish', $in ); }
    public function discard( array $in ) { return $this->record( 'discard', $in ); }
    public function lock( array $in ) { return $this->record( 'lock', $in ); }
    public function unlock( array $in ) { return $this->record( 'unlock', $in ); }
    public function createClasses( array $in ) { return $this->record( 'createClasses', $in ); }
    public function createNew( array $in ) { return $this->record( 'createNew', $in ); }
    public function children( array $in ) { return $this->record( 'children', $in ); }
    public function move( array $in ) { return $this->record( 'move', $in ); }
    public function addLocation( array $in ) { return $this->record( 'addLocation', $in ); }
    public function copy( array $in ) { return $this->record( 'copy', $in ); }
    public function visibility( array $in, $hide ) { return $this->record( $hide ? 'hide' : 'reveal', $in ); }
    public function trash( array $in ) { return $this->record( 'trash', $in ); }
    public function sort( array $in ) { return $this->record( 'sort', $in ); }
    public $collaborates = true;
    public function collaborates() { return $this->collaborates; }
    public function waitingCount( $fresh = false ) { return 3; }
    public function noticeCount( $fresh = false ) { return array( 'waiting' => $this->collaborates ? 3 : 0, 'count' => $this->collaborates ? 5 : 1 ); }
    public function markRead( array $in ) { return $this->record( 'markRead', $in ); }
    public function inbox( array $in ) { return $this->record( 'inbox', $in ); }
    public function decide( array $in ) { return $this->record( 'decide', $in ); }
    public function insights( array $in ) { return $this->record( 'insights', $in ); }
    public function seoCheck( array $in ) { return $this->record( 'seoCheck', $in ); }
    public function elsewhere( array $in ) { return $this->record( 'elsewhere', $in ); }
    public function aiField( array $in ) { return $this->record( 'aiField', $in ); }
    public function aiApply( array $in ) { return $this->record( 'aiApply', $in ); }
    public $registered = array( 'panels' => array(), 'actions' => array(), 'highlights' => array(), 'search' => array() );
    public $policies = array();
    public function registeredActions() { return $this->registered['actions']; }
    public function registry()
    {
        $policies = $this->policies;
        return expwtRegistry::forUser( $this->registered, $this->caps, function ( $m, $f ) use ( $policies ) { return in_array( "$m/$f", $policies, true ); } );
    }
    public function registryForBoot() { return expwtRegistry::forBoot( $this->registry(), function ( $c, $s ) { return $s; }, function ( $t, $f ) { return "/packed/$f"; } ); }
}

class expwtApiTest extends PHPUnit\Framework\TestCase
{
    private $gateway;

    private function api( $caps = null )
    {
        $settings = expwtSettings::fromArray( array() );
        $caps = $caps === null ? array_fill_keys( expwtAccess::FUNCTIONS, true ) : $caps;
        $this->gateway = new expwtApiTestGateway( $settings, $caps );
        return new expwtApi( $this->gateway, $settings );
    }

    public function testUnknownActionIs404()
    {
        $r = $this->api()->call( 'drop.tables', array() );
        $this->assertSame( 404, $r['status'] );
        $this->assertSame( 'unknown_action', $r['body']['error'] );
        $this->assertFalse( $r['body']['ok'] );
    }

    public function testWritingByGetIs405AndNeverReachesTheGateway()
    {
        $r = $this->api()->call( 'draft.publish', array( 'objectId' => 5, 'version' => 2 ), 'GET' );
        $this->assertSame( 405, $r['status'] );
        $this->assertSame( array(), $this->gateway->calls );
    }

    public function testEveryActionChecksItsPolicy()
    {
        foreach ( expwtAccess::ACTIONS as $action => $a )
        {
            $caps = array_fill_keys( expwtAccess::FUNCTIONS, true );
            $caps[$a[0]] = false;
            $r = $this->api( $caps )->call( $action, array( 'fields' => array( 'x' => 'y' ) ), 'POST' );
            $this->assertSame( 403, $r['status'], "$action without expwt/{$a[0]}" );
            $this->assertSame( array(), $this->gateway->calls, $action );
        }
    }

    public function testEveryActionIsAnsweredWithAllPolicies()
    {
        foreach ( array_keys( expwtAccess::ACTIONS ) as $action )
        {
            $r = $this->api()->call( $action, array( 'objectId' => 5, 'version' => 2, 'nodeId' => 61, 'parentId' => 2, 'fields' => array( 'title' => 'x' ),
                                                     'classIdentifier' => 'folder', 'title' => 'T', 'order' => array( '3', '2' ),
                                                     'state' => array( 'side' => 'left' ) ), 'POST' );
            $this->assertSame( 200, $r['status'], $action );
            $this->assertTrue( $r['body']['ok'], $action );
        }
    }

    public function testInputIsCleanedBeforeTheGateway()
    {
        $api = $this->api();
        $api->call( 'page.load', array( 'nodeId' => '61', 'objectId' => -3, 'version' => 'x', 'language' => ' ger-DE ', 'extra' => 1 ), 'GET' );
        $this->assertSame( array( 'page', array( 'nodeId' => 61, 'language' => 'ger-DE' ) ), $this->gateway->calls[0] );
        $api->call( 'tree.sort', array( 'nodeId' => 2, 'order' => array( '7', 5 ) ) );
        $this->assertSame( array( 'nodeId' => 2, 'order' => array( 7, 5 ) ), $this->gateway->calls[1][1] );
        $api->call( 'lock.acquire', array( 'objectId' => 5, 'takeOver' => 1 ) );
        $this->assertSame( array( 'objectId' => 5, 'takeOver' => true ), $this->gateway->calls[2][1] );
        $api->call( 'draft.save', array( 'objectId' => 5, 'fields' => array( 'title' => 'x' ), 'base' => 'nope', 'publishedVersion' => 3 ) );
        $this->assertSame( array( 'objectId' => 5, 'publishedVersion' => 3, 'fields' => array( 'title' => 'x' ), 'base' => array(), 'choices' => array() ),
                           $this->gateway->calls[3][1] );
    }

    public function testTheInboxGoesToTheGatewayCleaned()
    {
        $api = $this->api();
        $this->assertFalse( expwtAccess::writes( 'inbox.load' ) );
        $this->assertTrue( expwtAccess::writes( 'inbox.decide' ) );
        $this->assertSame( array( 'module' => 'collaboration', 'view' => 'action', 'post' => array( 'CollaborationActionCustom' => '1' ) ),
                           expwtAccess::kernelView( 'inbox.decide' ), '"Verify it\'s you" asks as for the administration\'s decision' );
        $this->assertSame( 405, $api->call( 'inbox.decide', array( 'itemId' => 3, 'decision' => 'approve' ), 'GET' )['status'] );
        $api->call( 'inbox.decide', array( 'itemId' => '7', 'decision' => ' deny ', 'comment' => "  Please fix\nthe intro.  ", 'extra' => 1 ) );
        $this->assertSame( array( 'decide', array( 'itemId' => 7, 'decision' => 'deny', 'comment' => "Please fix\nthe intro." ) ), $this->gateway->calls[0] );
        $api->call( 'inbox.load', array( 'offset' => 'x' ), 'GET' );
        $this->assertSame( array( 'inbox', array() ), $this->gateway->calls[1] );
        // F29: marking an item as read is a write (POST, the CSRF token), with no "Verify it's you"
        $this->assertTrue( expwtAccess::writes( 'inbox.read' ) );
        $this->assertNull( expwtAccess::kernelView( 'inbox.read' ) );
        $this->assertSame( 405, $api->call( 'inbox.read', array( 'itemId' => 3 ), 'GET' )['status'] );
        $api->call( 'inbox.read', array( 'itemId' => '9', 'other' => 1 ) );
        $this->assertSame( array( 'markRead', array( 'itemId' => 9 ) ), $this->gateway->calls[2] );
    }

    /** E1: asking Exponential Intelligence and taking its suggestion are writes for those who edit, cleaned */
    public function testExponentialIntelligenceGoesToTheGatewayCleaned()
    {
        $api = $this->api();
        foreach ( array( 'ai.field', 'ai.apply' ) as $action )
        {
            $this->assertSame( 'edit', expwtAccess::functionFor( $action ) );
            $this->assertTrue( expwtAccess::writes( $action ), $action . ' is sent by POST with the token' );
            $this->assertSame( 405, $api->call( $action, array( 'objectId' => 5 ), 'GET' )['status'] );
        }
        $this->assertNull( expwtAccess::kernelView( 'ai.field' ), 'asking writes no content' );
        $this->assertSame( array( 'module' => 'content', 'view' => 'edit', 'post' => array( 'StoreButton' => '1' ) ), expwtAccess::kernelView( 'ai.apply' ) );
        $api->call( 'ai.field', array( 'objectId' => '5', 'version' => 'x', 'language' => ' eng-GB ', 'field' => 'title', 'action' => 'rewrite',
                                       'instruction' => '  ' . str_repeat( 'a', 5000 ) . ' ', 'value' => 'Typed', 'other' => 1 ) );
        $call = $this->gateway->calls[0];
        $this->assertSame( 'aiField', $call[0] );
        $this->assertSame( array( 'objectId', 'language', 'field', 'action', 'instruction', 'value' ), array_keys( $call[1] ) );
        $this->assertSame( 4000, strlen( $call[1]['instruction'] ) );
        $this->assertSame( 'eng-GB', $call[1]['language'] );
        $api->call( 'ai.field', array( 'objectId' => 5, 'field' => 'intro', 'action' => 'summarize', 'value' => array( 'not text' ) ) );
        $this->assertArrayNotHasKey( 'value', $this->gateway->calls[1][1], 'a value that is no text is left out' );
        $api->call( 'ai.apply', array( 'objectId' => 5, 'version' => 3, 'language' => 'eng-GB', 'field' => 'body', 'value' => '<p>New</p>' ) );
        $this->assertSame( array( 'aiApply', array( 'objectId' => 5, 'version' => 3, 'language' => 'eng-GB', 'field' => 'body', 'value' => '<p>New</p>' ) ),
                           $this->gateway->calls[2] );
    }

    /** F29: the counts on the tabs for every user of the sidebar: approvals and comments with the collaboration,
        failed scheduled tasks for everyone */
    public function testBootHasTheInboxCountsForEveryUserOfTheSidebar()
    {
        $api = $this->api();
        $this->assertSame( array( 'waiting' => 3, 'count' => 5 ), $api->call( 'boot', array(), 'GET' )['body']['data']['inbox'] );
        $this->gateway->collaborates = false;
        $this->assertSame( array( 'waiting' => 0, 'count' => 1 ), $api->call( 'boot', array(), 'GET' )['body']['data']['inbox'] );
    }

    /** F29: each item counts once; news are comments of others after the last read, and the user's own decided pages */
    public function testTheNotificationsAreCountedOnceAndOnlyWhatIsNew()
    {
        $this->assertSame( 4, expwtGateway::noticeTotal( array( 1, 2 ), array( 2, 3 ), array( 3 ), 1 ), 'items 1, 2, 3 and one failed task' );
        $this->assertSame( 0, expwtGateway::noticeTotal( array(), array(), array(), 0 ) );
        $me = 14;
        $status = array( array( 'collaboration_id' => 1, 'last_read' => 100 ), array( 'collaboration_id' => 2, 'last_read' => 100 ),
                         array( 'collaboration_id' => 3, 'last_read' => 100 ), array( 'collaboration_id' => 4, 'last_read' => 500 ) );
        $items = array(
            array( 'id' => 1, 'creator_id' => $me, 'modified' => 200, 'type_identifier' => 'ezapprove', 'data_int3' => 1 ),   // approved since
            array( 'id' => 2, 'creator_id' => 99, 'modified' => 200, 'type_identifier' => 'ezapprove', 'data_int3' => 2 ),  // someone else's
            array( 'id' => 3, 'creator_id' => $me, 'modified' => 200, 'type_identifier' => 'ezapprove', 'data_int3' => 0 ),  // still waiting
            array( 'id' => 4, 'creator_id' => $me, 'modified' => 400, 'type_identifier' => 'ezapprove', 'data_int3' => 2 ),  // read after
            array( 'id' => 5, 'creator_id' => $me, 'modified' => 900, 'type_identifier' => 'ezapprove', 'data_int3' => 1 ),  // not mine to read
        );
        $messages = array( array( 'collaboration_id' => 2, 'participant_id' => 99, 'modified' => 150 ),
                           array( 'collaboration_id' => 3, 'participant_id' => $me, 'modified' => 300 ),
                           array( 'collaboration_id' => 4, 'participant_id' => 99, 'modified' => 450 ) );
        $news = expwtGateway::unreadFrom( $status, $items, $messages, $me, 0 );
        $this->assertSame( array( 2 ), $news['comments'], 'a comment of someone else after the last read; not my own, not one read already' );
        $this->assertSame( array( 1 ), $news['results'], 'my own page decided since I last looked' );
        $this->assertSame( array(), expwtGateway::unreadFrom( $status, $items, $messages, $me, 300 )['results'], 'older than the days looked back' );
    }

    /** F29: a mention is @login, not part of a longer name or an e-mail address */
    public function testAMentionIsTheLoginWithAnAt()
    {
        $this->assertTrue( expwtGateway::mentions( 'Please look, @anna.', 'anna' ) );
        $this->assertTrue( expwtGateway::mentions( '@Anna can you check', 'anna' ) );
        $this->assertFalse( expwtGateway::mentions( 'ask @annabel', 'anna' ) );
        $this->assertFalse( expwtGateway::mentions( 'mail anna@example.com', 'anna' ) );
        $this->assertFalse( expwtGateway::mentions( 'nobody here', 'anna' ) );
        $this->assertFalse( expwtGateway::mentions( '@', '' ) );
    }

    public function testDraftSaveNeedsFields()
    {
        $r = $this->api()->call( 'draft.save', array( 'objectId' => 5 ) );
        $this->assertSame( 400, $r['status'] );
        $this->assertSame( 'no_fields', $r['body']['error'] );
    }

    public function testStateIsNormalizedAndSaved()
    {
        $r = $this->api()->call( 'state.save', array( 'state' => array( 'side' => 'left', 'width' => 5, 'panel' => 'Secret!' ) ) );
        $this->assertSame( 'left', $this->gateway->saved['edge'], 'an old client sending side' );
        $this->api()->call( 'state.save', array( 'state' => array( 'edge' => 'bottom', 'align' => 'start', 'height' => 333 ) ) );
        $this->assertSame( array( 'bottom', 'start', 333 ), array( $this->gateway->saved['edge'], $this->gateway->saved['align'], $this->gateway->saved['height'] ) );
        $r = $this->api()->call( 'state.save', array( 'state' => array( 'side' => 'left', 'width' => 5, 'panel' => 'Secret!' ) ) );
        $this->assertSame( 300, $this->gateway->saved['width'] );
        $this->assertSame( 'page', $this->gateway->saved['panel'] );
        $this->assertSame( $this->gateway->saved, $r['body']['data']['state'] );
    }

    public function testBootTellsTheSidebarEverythingOnce()
    {
        $r = $this->api()->call( 'boot', array(), 'GET' );
        $d = $r['body']['data'];
        $this->assertSame( array( 'user', 'capabilities', 'state', 'theme', 'token', 'transport', 'urls', 'strings', 'inbox', 'registry', 'settings' ), array_keys( $d ) );
        $this->assertSame( 'dark', $d['theme'], 'follows the administration (F10)' );
        $this->assertSame( 'tok', $d['token'] );
        $this->assertSame( 'Bearbeiten', $d['strings']['Edit'] );
    }

    public function testRefusalsOfTheGatewayKeepTheirStatus()
    {
        $api = $this->api();
        $this->gateway->throw = new expwtApiError( 409, 'conflict', 'Someone else changed these fields in the meantime.', array( 'conflicts' => array( 'title' => array() ) ) );
        $r = $api->call( 'draft.save', array( 'objectId' => 5, 'fields' => array( 'title' => 'x' ) ) );
        $this->assertSame( 409, $r['status'] );
        $this->assertSame( 'conflict', $r['body']['error'] );
        $this->assertArrayHasKey( 'conflicts', $r['body']['details'] );
    }

    public function testAnythingElseIsA500WithoutDetails()
    {
        $api = $this->api();
        $this->gateway->throw = new RuntimeException( 'SQLSTATE secret details' );
        $r = $api->call( 'me', array(), 'GET' );
        $this->assertSame( 500, $r['status'] );
        $this->assertStringNotContainsString( 'SQLSTATE', json_encode( $r ) );
    }

    public function testRefusalsOfTheKernelsWriter()
    {
        if ( !class_exists( 'expGraphQLError' ) )
            $this->markTestSkipped( 'no GraphQL writer in this kernel' );
        $this->assertSame( 403, expwtApi::fromGraphQLError( new expGraphQLError( 'no', expGraphQLError::FORBIDDEN ) )['status'] );
        $this->assertSame( 404, expwtApi::fromGraphQLError( new expGraphQLError( 'no', expGraphQLError::NOT_FOUND ) )['status'] );
        $this->assertSame( 400, expwtApi::fromGraphQLError( new expGraphQLError( 'bad' ) )['status'] );
    }
}
