<?php
/**
 * Comments held for moderation (expwt.ini [Comments] HideNew): who is held, the switch, and the shipped settings
 * that register the content edit handler.
 *
 * No database, no request.
 *
 * Run: php vendor/bin/phpunit extension/expwt/tests/phpunit/unit/expwtCommentModerationTest.php
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

require_once dirname( __DIR__ ) . '/bootstrap.php';

class expwtCommentModerationTest extends PHPUnit\Framework\TestCase
{
    public function testSwitchIsOnlyOnWhenEnabled()
    {
        foreach ( array( 'enabled', 'Enabled', ' enabled ', 'true', '1', 'yes' ) as $on )
            $this->assertTrue( expwtCommentModeration::isOn( $on ), $on );
        foreach ( array( 'disabled', '', '0', 'false', 'no', 'on?', null ) as $off )
            $this->assertFalse( expwtCommentModeration::isOn( $off ), var_export( $off, true ) );
    }

    public function testVisitorsNewCommentIsHeld()
    {
        $this->assertTrue( expwtCommentModeration::holds( true, 'comment', 'comment', true, false, false ) );
    }

    public function testNothingChangesWhileSwitchedOff()
    {
        $this->assertFalse( expwtCommentModeration::holds( false, 'comment', 'comment', true, false, false ) );
    }

    public function testModeratorsOwnCommentAndReplyStayVisible()
    {
        $this->assertFalse( expwtCommentModeration::holds( true, 'comment', 'comment', true, true, false ),
                            'a creator with content/hide of the comment may approve it: it is not held' );
    }

    public function testOnlyTheFirstPublicationIsHeld()
    {
        $this->assertFalse( expwtCommentModeration::holds( true, 'comment', 'comment', false, false, false ),
                            'a later version of an approved comment keeps the visibility a moderator gave it' );
    }

    public function testOtherClassesAndAHiddenNodeAreLeftAlone()
    {
        $this->assertFalse( expwtCommentModeration::holds( true, 'article', 'comment', true, false, false ) );
        $this->assertFalse( expwtCommentModeration::holds( true, 'comment', null, true, false, false ), 'no comment class here' );
        $this->assertFalse( expwtCommentModeration::holds( true, 'comment', 'comment', true, false, true ), 'hidden already' );
        $this->assertTrue( expwtCommentModeration::holds( true, 'review', 'review', true, false, false ), 'CommentClass names the class' );
    }

    public function testShippedSettingsAreOffAndRegisterTheEditHandler()
    {
        $root = dirname( __DIR__, 3 );
        $ini = (string)file_get_contents( $root . '/settings/expwt.ini' );
        $this->assertMatchesRegularExpression( '/^\[Comments\]\s*$/m', $ini );
        $this->assertMatchesRegularExpression( '/^#HideNew=disabled\s*$/m', $ini, 'off by default for every other installation (documented, commented out)' );
        $content = (string)file_get_contents( $root . '/settings/content.ini.append.php' );
        $this->assertMatchesRegularExpression( '/^\[EditSettings\]\s*\nExtensionDirectories\[\]=expwt\s*$/m', $content );
        // the kernel includes <extension>/content/<extension>handler.php and calls <extension>Handler::publish()
        $handler = (string)file_get_contents( $root . '/content/expwthandler.php' );
        $this->assertStringContainsString( 'class expwtHandler extends eZContentObjectEditHandler', $handler );
        $this->assertStringContainsString( 'expwtCommentModeration::published(', $handler );
    }
}
