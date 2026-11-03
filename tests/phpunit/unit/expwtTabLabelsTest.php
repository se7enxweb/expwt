<?php
/**
 * How the website sidebar labels its tabs: the installation's default (expwt.ini [ExpwtSettings] TabLabels), the
 * user's own choice in the sidebar's state, and where the kernel's Bootstrap Icons are. No database.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

class expwtTabLabelsTest extends PHPUnit\Framework\TestCase
{
    private $root;

    protected function tearDown(): void
    {
        if ( $this->root && is_dir( $this->root ) )
        {
            @rmdir( $this->root . '/share/icons/bootstrap-icons/svg' );
            @rmdir( $this->root . '/share/icons/bootstrap-icons' );
            @rmdir( $this->root . '/share/icons' );
            @rmdir( $this->root . '/share' );
            @rmdir( $this->root );
        }
        $this->root = null;
    }

    private function root( $withIcons )
    {
        $this->root = sys_get_temp_dir() . '/expwt-tablabels-' . getmypid() . '-' . mt_rand();
        mkdir( $withIcons ? $this->root . '/share/icons/bootstrap-icons/svg' : $this->root, 0777, true );
        return $this->root;
    }

    public function testTabLabelsDefaultIsIcons()
    {
        $this->assertSame( 'icons', expwtSettings::fromArray( array() )['tab_labels'] );
    }

    public function testTabLabelsKeepsAValidValue()
    {
        $this->assertSame( 'text', expwtSettings::fromArray( array( 'tab_labels' => 'text' ) )['tab_labels'] );
        $this->assertSame( 'both', expwtSettings::fromArray( array( 'tab_labels' => 'both' ) )['tab_labels'] );
        $this->assertSame( 'icons', expwtSettings::fromArray( array( 'tab_labels' => 'icons' ) )['tab_labels'] );
    }

    public function testTabLabelsUnknownValueIsIcons()
    {
        $this->assertSame( 'icons', expwtSettings::fromArray( array( 'tab_labels' => 'Words' ) )['tab_labels'] );
        $this->assertSame( 'icons', expwtSettings::fromArray( array( 'tab_labels' => '' ) )['tab_labels'] );
        $this->assertSame( 'icons', expwtSettings::fromArray( array( 'tab_labels' => array( 'text' ) ) )['tab_labels'] );
    }

    public function testTheShippedSettingsDocumentTabLabels()
    {
        $ini = (string)file_get_contents( dirname( __DIR__, 3 ) . '/settings/expwt.ini' );
        $this->assertMatchesRegularExpression( '/^#TabLabels=icons$/m', $ini, 'documented, commented out, with its default' );
        $this->assertDoesNotMatchRegularExpression( '/^TabLabels=/m', $ini, 'never set in the shipped file' );
    }

    public function testTheStateHasNoChoiceByDefault()
    {
        $settings = expwtSettings::fromArray( array( 'tab_labels' => 'text' ) );
        $this->assertSame( '', expwtState::defaults( $settings )['labels'], 'no choice: the installation\'s default, also when it changes later' );
        $this->assertSame( '', expwtState::normalize( array(), $settings )['labels'] );
        $this->assertSame( '', expwtState::normalize( array( 'theme' => 'dark' ), $settings )['labels'], 'a state saved before the choice existed' );
    }

    public function testTheStateKeepsTheUsersChoice()
    {
        $settings = expwtSettings::fromArray( array( 'tab_labels' => 'text' ) );
        foreach ( array( 'icons', 'both', 'text' ) as $v )
            $this->assertSame( $v, expwtState::normalize( array( 'labels' => $v ), $settings )['labels'] );
        $this->assertSame( array( 'icons', 'both', 'text' ), expwtState::LABELS );
    }

    public function testTheStateDropsAWrongChoice()
    {
        $settings = expwtSettings::fromArray( array() );
        foreach ( array( 'icon', 'Icons', 7, true, array( 'text' ), null ) as $v )
            $this->assertSame( '', expwtState::normalize( array( 'labels' => $v ), $settings )['labels'], var_export( $v, true ) );
    }

    public function testTheIconsAreTheKernels()
    {
        $root = $this->root( true );
        $this->assertSame( '/share/icons/bootstrap-icons/svg/', expwtHook::iconBase( $root, '' ) );
        $this->assertSame( '/sub/share/icons/bootstrap-icons/svg/', expwtHook::iconBase( $root, '/sub' ), 'a site in a subdirectory' );
        $this->assertSame( '/sub/share/icons/bootstrap-icons/svg/', expwtHook::iconBase( $root, '/sub/' ) );
    }

    public function testAKernelWithoutTheIconsHasNone()
    {
        $this->assertSame( '', expwtHook::iconBase( $this->root( false ), '' ) );
    }
}
