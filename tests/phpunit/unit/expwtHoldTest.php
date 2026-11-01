<?php
/**
 * The review hold in the website sidebar (E14, expwtHold): what the Page panel shows and offers, the reason as it is
 * kept, the actions, their policies, the input they get, and the audit events of the sidebar.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

require_once dirname( __DIR__ ) . '/bootstrap.php';
if ( !class_exists( 'expwtAuditBranch' ) )
    require_once dirname( __DIR__, 3 ) . '/classes/expwtauditbranch.php';

class expwtHoldTest extends PHPUnit\Framework\TestCase
{
    public function testAPageNotHeldOffersAHoldOnlyToThoseWhoMayPlaceIt()
    {
        $v = expwtHold::view( false, true, true, true, array( 'name' => 'Old', 'since' => 5, 'reason' => 'gone' ) );
        $this->assertFalse( $v['held'] );
        $this->assertTrue( $v['canPlace'] );
        $this->assertFalse( $v['canRelease'], 'nothing to release' );
        $this->assertSame( '', $v['reason'], 'what is kept of an earlier hold is not shown' );
        $this->assertFalse( expwtHold::view( false, false, true, true, null )['canPlace'] );
    }

    public function testAHeldPageShowsWhoWhenAndWhyAndOffersTheRelease()
    {
        $v = expwtHold::view( true, true, true, false, array( 'user' => 14, 'name' => 'Rita Reviewer', 'since' => 1800000000, 'reason' => 'Legal check' ) );
        $this->assertSame( array( 'held' => true, 'canPlace' => false, 'canRelease' => true, 'editable' => false,
                                  'by' => 'Rita Reviewer', 'since' => 1800000000, 'reason' => 'Legal check' ), $v );
        $this->assertFalse( expwtHold::view( true, true, false, false, null )['canRelease'], 'state/assign of not_held decides' );
    }

    public function testAHoldPlacedInTheAdministrationHasNoDetails()
    {
        $v = expwtHold::view( true, false, false, true, null );
        $this->assertSame( array( '', 0, '' ), array( $v['by'], $v['since'], $v['reason'] ) );
        $this->assertTrue( $v['editable'], 'a role whose policy allows the held state still edits' );
    }

    public function testTheReasonIsOneTrimmedParagraphOfLimitedLength()
    {
        $this->assertSame( 'Claims in the second paragraph', expwtHold::reason( "  Claims in the\n\tsecond paragraph \x07" ) );
        $this->assertSame( '', expwtHold::reason( null ) );
        $this->assertSame( expwtHold::REASON_MAX, mb_strlen( expwtHold::reason( str_repeat( 'ä', 900 ) ), 'UTF-8' ) );
    }

    public function testTheActionsWriteAndNeedOnlyExpwtUse()
    {
        foreach ( array( 'hold.place', 'hold.release' ) as $action )
        {
            $this->assertTrue( expwtAccess::exists( $action ), $action );
            $this->assertTrue( expwtAccess::writes( $action ), $action );
            $this->assertSame( 'use', expwtAccess::ACTIONS[$action][0], 'the kernel state/assign decides, a reviewer may have expwt/use only' );
            $this->assertSame( array( 'state', 'assign' ), array( expwtAccess::ACTIONS[$action][2], expwtAccess::ACTIONS[$action][3] ) );
            $caps = array_fill_keys( expwtAccess::FUNCTIONS, false );
            $this->assertFalse( expwtAccess::allows( $action, $caps ), "$action without expwt/use" );
        }
    }

    public function testTheGroupAndStatesAreTheKernelsOnes()
    {
        $this->assertSame( array( 'review_hold', 'not_held', 'held' ), array( expwtHold::GROUP, expwtHold::STATE_FREE, expwtHold::STATE_HELD ) );
        if ( class_exists( 'expReviewHold' ) )
            $this->assertSame( array( expReviewHold::GROUP, expReviewHold::STATE_FREE, expReviewHold::STATE_HELD ),
                               array( expwtHold::GROUP, expwtHold::STATE_FREE, expwtHold::STATE_HELD ) );
    }

    public function testPlacingAndReleasingAreAudited()
    {
        $events = ( new expwtAuditBranch() )->events();
        foreach ( array( 'content.expwt.hold.place', 'content.expwt.hold.release' ) as $name )
        {
            $this->assertArrayHasKey( $name, $events );
            $this->assertSame( 'on', $events[$name]['default'] );
            $this->assertSame( 'content', $events[$name]['channel'] );
        }
    }

    public function testEveryStringOfTheHoldIsASource()
    {
        foreach ( array( 'Review hold', 'Held for review', 'Hold for review', 'Release', 'Held by %name since %time.',
                         'This page is held for review. It can be published once a reviewer releases it.',
                         'You may not hold this page.', 'You may not release this page.' ) as $s )
            $this->assertContains( $s, expwtStrings::SOURCES, $s );
    }
}
