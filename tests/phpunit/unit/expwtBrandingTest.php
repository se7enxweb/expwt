<?php
/**
 * The branding per siteaccess (E10, expwt.ini [BrandingSettings]): the name, the logo and the accent color with its
 * contrast check in both modes, what the sidebar's script gets, and the Help credit that stays.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

require_once dirname( __DIR__ ) . '/bootstrap.php';

class expwtBrandingTest extends PHPUnit\Framework\TestCase
{
    public function testNothingSetIsTheSidebarsOwnLook()
    {
        $b = expwtBranding::fromArray( array() );
        $this->assertSame( array( 'name' => '', 'logo' => '', 'light' => array(), 'dark' => array(), 'refused' => array() ), $b );
        $this->assertSame( array(), expwtBranding::client( $b ), 'not one byte more in the page' );
    }

    public function testContrastIsWcag()
    {
        $this->assertEqualsWithDelta( 21.0, expwtBranding::contrast( '#000000', '#ffffff' ), 0.001 );
        $this->assertEqualsWithDelta( 1.0, expwtBranding::contrast( '#777777', '#777777' ), 0.001 );
        $this->assertEqualsWithDelta( 4.48, expwtBranding::contrast( '#777777', '#ffffff' ), 0.01 );
        $this->assertSame( '#aabbcc', expwtBranding::hex( 'ABC' ) );
        $this->assertSame( '#1d3557', expwtBranding::hex( ' #1D3557 ' ) );
        $this->assertNull( expwtBranding::hex( 'navy' ) );
        $this->assertSame( '#808080', expwtBranding::mix( '#000000', '#ffffff', 0.5 ) );
    }

    public function testAnAccentThatPassesGivesEveryTokenAtTheirContrasts()
    {
        $b = expwtBranding::fromArray( array( 'accent' => '#1d3557' ) );
        $this->assertSame( array(), $b['refused'] );
        $this->assertSame( expwtBranding::TOKENS, array_keys( $b['light'] ) );
        $this->assertSame( '#1d3557', $b['light']['--a4-accent-strong'] );
        $this->assertSame( '#ffffff', $b['light']['--a4-on-accent'], 'white reads better on navy' );
        foreach ( array( 'light', 'dark' ) as $mode )
        {
            $t = $b[$mode];
            $this->assertNotEmpty( $t, "$mode derived" );
            list( $surface, $card ) = expwtBranding::SURFACES[$mode];
            $this->assertGreaterThanOrEqual( 4.5, expwtBranding::contrast( $t['--a4-accent'], $t['--a4-on-accent'] ), "$mode fill text" );
            $this->assertGreaterThanOrEqual( 4.5, expwtBranding::contrast( $t['--a4-accent-press'], $t['--a4-on-accent-hover'] ), "$mode pressed text" );
            $this->assertGreaterThanOrEqual( 3.0, expwtBranding::contrast( $t['--a4-accent'], $surface ), "$mode fill on the panel" );
            $this->assertGreaterThanOrEqual( 4.5, expwtBranding::contrast( $t['--a4-accent-text'], $surface ), "$mode links" );
            $this->assertGreaterThanOrEqual( 4.5, expwtBranding::contrast( $t['--a4-accent-text'], $card ), "$mode links on cards" );
        }
        $this->assertNotSame( '#1d3557', $b['dark']['--a4-accent'], 'navy is too dark on the dark panel: lightened' );
    }

    public function testAColorThatFailsIsRefusedWithItsRatio()
    {
        // light yellow: no text color reaches 4.5:1 on it... it does with near black, but it stands under 3:1 on white
        $b = expwtBranding::fromArray( array( 'accent' => '#ffe066', 'accent_dark' => '#20242c' ) );
        $this->assertSame( array(), $b['light'] );
        $this->assertSame( array(), $b['dark'] );
        $this->assertSame( array( 'AccentColor', 'AccentColorDark' ), array_column( $b['refused'], 'setting' ) );
        $this->assertSame( 3.0, $b['refused'][0]['needed'] );
        $this->assertLessThan( 3.0, $b['refused'][0]['ratio'] );
        $this->assertLessThan( 3.0, $b['refused'][1]['ratio'], 'near the dark panel itself' );
        // mid grey: neither white nor near black reaches 4.5:1 on it
        $grey = expwtBranding::fromArray( array( 'accent' => '#767676' ) );
        $this->assertSame( array(), $grey['refused'], '#767676 just passes with white (4.54:1)' );
        $grey = expwtBranding::fromArray( array( 'accent' => '#7a7a7a' ) );
        $this->assertSame( 4.5, $grey['refused'][0]['needed'], 'text on the fill' );
        $bad = expwtBranding::fromArray( array( 'accent' => 'red;}', 'logo' => 'javascript:alert(1)' ) );
        $this->assertSame( array( 'Logo', 'AccentColor' ), array_column( $bad['refused'], 'setting' ) );
        $this->assertSame( 0.0, $bad['refused'][1]['ratio'], 'not a color: 0' );
    }

    public function testTheLogoAndTheName()
    {
        foreach ( array( '/extension/site/design/site/images/logo.svg', '/var/storage/images/logo.png?v=2',
                         'https://cdn.example.com/brand/logo.webp' ) as $ok )
            $this->assertTrue( expwtBranding::logoOk( $ok ), $ok );
        foreach ( array( '//evil.example/logo.svg', 'http://example.com/logo.svg', 'logo.svg', '/x/../../etc/logo.svg',
                         '/logo.svg" onerror="x', '/logo.php', 'data:image/svg+xml,<svg/>' ) as $bad )
            $this->assertFalse( expwtBranding::logoOk( $bad ), $bad );
        $b = expwtBranding::fromArray( array( 'name' => "  Acme   Editor\n", 'logo' => '/extension/acme/logo.svg' ) );
        $this->assertSame( 'Acme Editor', $b['name'] );
        $this->assertSame( array( 'name' => 'Acme Editor', 'logo' => '/site/extension/acme/logo.svg' ), expwtBranding::client( $b, '/site/' ) );
        $this->assertSame( 60, mb_strlen( expwtBranding::fromArray( array( 'name' => str_repeat( 'ä', 80 ) ) )['name'] ) );
    }

    public function testTheCreditStaysInHelpAndTheStringsAreTranslated()
    {
        $js = (string)file_get_contents( dirname( __DIR__, 3 ) . '/design/standard/javascript/expwt-panels.js' );
        $this->assertStringContainsString( "t('Website sidebar of Exponential')", $js );
        $this->assertContains( 'Website sidebar of Exponential', expwtStrings::SOURCES );
    }
}
