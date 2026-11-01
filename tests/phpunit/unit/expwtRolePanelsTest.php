<?php
/**
 * The panels per role and siteaccess (E9): the rules of expwtRolePanels (pure), and the writing, history and restore
 * through the kernel's settings editor on a fixture installation under var/tmp/expwt-role-panels-tests/ (never the
 * real settings).
 *
 * Run: php vendor/bin/phpunit extension/expwt/tests/phpunit/unit/expwtRolePanelsTest.php
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

require_once dirname( __DIR__ ) . '/bootstrap.php';

class expwtRolePanelsTest extends PHPUnit\Framework\TestCase
{
    private $root = null;

    public function tearDown(): void
    {
        if ( class_exists( 'expIniEditor' ) )
            expIniEditor::setRoot( null );
        if ( $this->root !== null && is_dir( $this->root ) )
            self::removeTree( $this->root );
        parent::tearDown();
    }

    private static function removeTree( $dir )
    {
        foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
                                                 RecursiveIteratorIterator::CHILD_FIRST ) as $f )
            $f->isDir() ? rmdir( $f->getPathname() ) : unlink( $f->getPathname() );
        rmdir( $dir );
    }

    private function fixture()
    {
        if ( !class_exists( 'expIniEditor' ) )
            $this->markTestSkipped( 'the kernel settings editor (expIniEditor) is not loaded' );
        $base = expIniEditor::realRoot() . 'var/tmp/expwt-role-panels-tests';
        $this->root = $base . '/' . getmypid() . '-' . bin2hex( random_bytes( 4 ) ) . '/';
        mkdir( $this->root . 'settings/siteaccess/site', 0755, true );
        mkdir( $this->root . 'settings/override', 0755, true );
        file_put_contents( $this->root . 'settings/site.ini', "[SiteSettings]\nSiteName=Test\n" );
        expIniEditor::setRoot( $this->root, array(), array( 'site' ) );
        return $this->root;
    }

    // ------------------------------------------------------------------------------------------------ rules

    public function testRoleSettingsAreCleaned()
    {
        $r = expwtRolePanels::role( array( 'page', 'Bad Id', 'page', 'calendar', array( 'x' ) ), array( 'me', 'page', 'forms', 'forms', '' ) );
        $this->assertSame( array( 'page', 'calendar' ), $r['order'], 'panel ids only, each once' );
        $this->assertSame( array( 'forms' ), $r['hidden'], 'Page and Me are never hidden' );
        $this->assertSame( array( 'order' => array(), 'hidden' => array() ), expwtRolePanels::role( 'x', null ) );
    }

    public function testTheFirstRoleOfTheListDecidesTheOrderAndAShowingRoleWins()
    {
        $roles = array( 3 => array( 'order' => array( 'calendar', 'page' ), 'hidden' => array( 'forms', 'seo' ) ),
                        1 => array( 'order' => array( 'me' ), 'hidden' => array( 'seo', 'urls' ) ) );
        $this->assertNull( expwtRolePanels::forUser( $roles, array( 7, 9 ) ), 'no role of the list: the sidebar\'s own order' );
        $this->assertSame( array( 'order' => array( 'calendar', 'page' ), 'hidden' => array( 'forms', 'seo' ) ), expwtRolePanels::forUser( $roles, array( '3' ) ) );
        $both = expwtRolePanels::forUser( $roles, array( 1, 3 ) );
        $this->assertSame( array( 'calendar', 'page' ), $both['order'], 'the order of the first role of RoleList[], whatever the user\'s order' );
        $this->assertSame( array( 'seo' ), $both['hidden'], 'hidden only where every role of the user hides it' );
        $this->assertSame( array( 'me' ), expwtRolePanels::forUser( $roles, array( 1 ) )['order'] );
    }

    public function testApplyOrdersAndHidesButNeverEmptiesTheList()
    {
        $ids = array( 'page', 'create', 'seo', 'calendar', 'deploys', 'me' );
        $this->assertSame( $ids, expwtRolePanels::apply( $ids, null ) );
        $rule = array( 'order' => array( 'calendar', 'gone', 'me', 'page' ), 'hidden' => array( 'seo', 'me', 'deploys' ) );
        $this->assertSame( array( 'calendar', 'me', 'page', 'create' ), expwtRolePanels::apply( $ids, $rule ),
                           'the ordered ones first, then the rest in the sidebar\'s order; a panel the user does not get is skipped; Me stays' );
        $this->assertSame( array( 'seo' ), expwtRolePanels::apply( array( 'seo' ), array( 'order' => array(), 'hidden' => array( 'seo' ) ) ), 'never empty' );
    }

    public function testRowsAndMove()
    {
        $rows = expwtRolePanels::rows( array( 'page', 'seo', 'me' ), array( 'order' => array( 'me', 'seo' ), 'hidden' => array( 'seo', 'me' ) ) );
        $this->assertSame( array( 'me', 'seo', 'page' ), array_column( $rows, 'id' ) );
        $this->assertSame( array( true, false, true ), array_column( $rows, 'shown' ) );
        $this->assertSame( array( true, false, true ), array_column( $rows, 'fixed' ) );
        $this->assertSame( array( 'b', 'a', 'c' ), expwtRolePanels::move( array( 'a', 'b', 'c' ), 'b', -1 ) );
        $this->assertSame( array( 'a', 'c', 'b' ), expwtRolePanels::move( array( 'a', 'b', 'c' ), 'b', 1 ) );
        $this->assertSame( array( 'a', 'b', 'c' ), expwtRolePanels::move( array( 'a', 'b', 'c' ), 'a', -1 ), 'unchanged at the ends' );
        $this->assertSame( array( 3, 1 ), expwtRolePanels::move( array( 1, 3 ), 3, -1 ) );
        $this->assertSame( array( 'a', 'b' ), expwtRolePanels::move( array( 'a', 'b' ), 'z', 1 ) );
    }

    public function testFromValuesFollowsRoleList()
    {
        $roles = expwtRolePanels::fromValues( array(
            'RolePanelSettings' => array( 'RoleList' => array( '5', 'x', '2', '5', '0' ) ),
            'RolePanels_5' => array( 'PanelOrder' => array( 'seo' ), 'HiddenPanelList' => array( 'forms' ) ),
            'RolePanels_9' => array( 'PanelOrder' => array( 'urls' ) ) ) );
        $this->assertSame( array( 5, 2 ), array_keys( $roles ), 'only RoleList[], in its order; a group without its role is ignored' );
        $this->assertSame( array( 'order' => array(), 'hidden' => array() ), $roles[2], 'a role without its group: the sidebar\'s own' );
        $this->assertSame( array(), expwtRolePanels::fromValues( array() ) );
    }

    public function testBackupsNewestFirst()
    {
        $root = '/x/var/backup/ini';
        $rel = 'settings/siteaccess/site/expwt.ini.append.php';
        $sorted = expwtRolePanels::sortBackups( array( "$root/20261031-100000/$rel", "$root/20261101-090000/$rel", "$root/20261101-090000-2/$rel",
                                                       "$root/junk/$rel", "/elsewhere/20261101-090000/$rel", "$root/20261101-090000/settings/site.ini" ), $root, $rel );
        $this->assertSame( array( '20261101-090000-2', '20261101-090000', '20261031-100000' ), array_column( $sorted, 'stamp' ) );
    }

    // ------------------------------------------------------------------------------------------------ files

    public function testWriteHistoryAndRestoreThroughTheKernelEditor()
    {
        $root = $this->fixture();
        $file = $root . 'settings/siteaccess/site/expwt.ini.append.php';
        file_put_contents( $file, "<?php /* #?ini charset=\"utf-8\"?\n\n[ExpwtSettings]\nEnabled=enabled\n\n*/ ?>\n" );

        $editor = expwtRolePanels::editor( 'site' );
        expwtRolePanels::write( $editor, array( 3 => array( 'order' => array( 'calendar', 'page' ), 'hidden' => array( 'forms' ) ) ) );
        $result = $editor->save();
        $this->assertTrue( $result->written() );
        $this->assertNotEmpty( $result->backup(), 'a backup of the file before the write' );
        $text = file_get_contents( $file );
        $this->assertStringContainsString( "[ExpwtSettings]\nEnabled=enabled", $text, 'the rest of the file stays' );
        $this->assertStringContainsString( "[RolePanelSettings]\nRoleList[]\nRoleList[]=3", $text );
        $this->assertStringContainsString( "[RolePanels_3]\nPanelOrder[]\nPanelOrder[]=calendar\nPanelOrder[]=page\nHiddenPanelList[]\nHiddenPanelList[]=forms", $text );

        // a second role, the first one changed: the backup holds the first version
        sleep( 1 );
        $editor = expwtRolePanels::editor( 'site' );
        expwtRolePanels::write( $editor, array( 1 => array( 'order' => array(), 'hidden' => array( 'seo' ) ),
                                                3 => array( 'order' => array( 'me' ), 'hidden' => array() ) ) );
        $editor->save();
        $values = expIniWriter::fromFile( $file )->values();
        $this->assertSame( array( 1, 3 ), array_keys( expwtRolePanels::fromValues( $values ) ) );
        $this->assertSame( array( 'me' ), expwtRolePanels::fromValues( $values )[3]['order'] );

        $history = expwtRolePanels::history( $editor );
        $this->assertCount( 2, $history );
        $this->assertSame( array(), $history[1]['roles'], 'the oldest: before any role had panels' );
        $this->assertSame( array( 3 ), array_keys( $history[0]['roles'] ) );

        // restore the first version: role 1 goes, role 3 has its first panels again; the rest of the file stays
        $editor = expwtRolePanels::editor( 'site' );
        $restored = expwtRolePanels::backupRoles( $editor, $history[0]['stamp'] );
        expwtRolePanels::write( $editor, $restored );
        $editor->save();
        $values = expIniWriter::fromFile( $file )->values();
        $this->assertSame( array( 3 => array( 'order' => array( 'calendar', 'page' ), 'hidden' => array( 'forms' ) ) ), expwtRolePanels::fromValues( $values ) );
        $this->assertArrayNotHasKey( 'RolePanels_1', $values, 'the group of a role no longer in the list is removed' );
        $this->assertSame( 'enabled', $values['ExpwtSettings']['Enabled'] );
        $this->assertCount( 3, expwtRolePanels::history( $editor ), 'the restore made a backup of its own' );

        // no role left: the settings are gone from the file
        $editor = expwtRolePanels::editor( 'site' );
        expwtRolePanels::write( $editor, array() );
        $editor->save();
        $this->assertStringNotContainsString( 'RolePanel', file_get_contents( $file ) );
        $this->assertNull( expwtRolePanels::backupRoles( $editor, '../../etc' ), 'only a backup stamp' );
        $this->assertNull( expwtRolePanels::backupRoles( $editor, '20200101-000000' ), 'no such backup' );
    }
}
