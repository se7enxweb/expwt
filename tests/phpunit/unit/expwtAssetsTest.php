<?php
/**
 * The files of the sidebar: no inline CSS in the templates, only admin4 tokens for colours, radius, spacing and fonts
 * (the generated copy of the tokens current with design/admin4/stylesheets/components.css when the kernel is there),
 * the closed tab within its budget (decision F12: expwt.js and expwt.css at most 50 KB gzip), every string of the code
 * in expwtStrings::SOURCES and in every translation catalogue.
 *
 * Reads files only.
 *
 * Run: php vendor/bin/phpunit extension/expwt/tests/phpunit/unit/expwtAssetsTest.php
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

require_once dirname( __DIR__ ) . '/bootstrap.php';

class expwtAssetsTest extends PHPUnit\Framework\TestCase
{
    private static function ext()
    {
        return dirname( __DIR__, 3 );
    }

    private static function css()
    {
        return file_get_contents( self::ext() . '/design/standard/stylesheets/expwt.css' );
    }

    public function testTemplatesHaveNoInlineCss()
    {
        foreach ( glob( self::ext() . '/design/*/templates/expwt/*.tpl' ) as $file )
        {
            $text = file_get_contents( $file );
            $this->assertDoesNotMatchRegularExpression( '/<style\b/i', $text, $file );
            $this->assertDoesNotMatchRegularExpression( '/\sstyle\s*=/i', $text, $file );
        }
    }

    public function testStylesheetUsesTokensOnly()
    {
        $rules = preg_replace( '#/\* tokens:start.*?/\* tokens:end \*/#s', '', self::css() );
        $rules = preg_replace( '#/\*.*?\*/#s', '', $rules );
        $this->assertDoesNotMatchRegularExpression( '/#[0-9a-fA-F]{3,8}\b/', $rules, 'no hex colour outside the tokens' );
        $this->assertDoesNotMatchRegularExpression( '/\b(rgb|rgba|hsl|hsla)\(/', $rules, 'no colour function outside the tokens' );
        $this->assertStringNotContainsString( '!important', $rules );
        $this->assertDoesNotMatchRegularExpression( '/--(?!a4-|expwt-)[a-z][a-z0-9-]*\s*:/', $rules, 'no local token aliases' );
        $this->assertDoesNotMatchRegularExpression( '/font-weight:\s*\d/', $rules );
        $this->assertDoesNotMatchRegularExpression( '/border-radius:\s*\d+px/', $rules );
    }

    public function testEveryTokenHasALightAndADarkBlock()
    {
        $css = self::css();
        $this->assertSame( 1, preg_match( '#:host \{(.*?)\}#s', $css, $light ) );
        $this->assertSame( 1, preg_match( '#:host\(\[data-theme="dark"\]\) \{(.*?)\}#s', $css, $dark ) );
        $this->assertGreaterThan( 50, substr_count( $light[1], '--a4-' ) );
        $this->assertGreaterThan( 30, substr_count( $dark[1], '--a4-' ) );
        preg_match_all( '/var\((--a4-[a-z0-9-]+)/', preg_replace( '#/\* tokens:start.*?/\* tokens:end \*/#s', '', $css ), $used );
        foreach ( array_unique( $used[1] ) as $token )
            $this->assertStringContainsString( $token . ':', $light[1], "$token is defined" );
    }

    public function testTokensAreTheAdministrationsOwn()
    {
        $components = dirname( self::ext(), 2 ) . '/design/admin4/stylesheets/components.css';
        if ( !is_file( $components ) )
            $this->markTestSkipped( 'not inside an installation' );
        $source = file_get_contents( $components );
        $this->assertSame( 1, preg_match( '/^:root\s*\{(.*?)^\}/ms', $source, $root ) );
        preg_match_all( '/(--a4-[a-z0-9-]+)\s*:\s*([^;]+);/', preg_replace( '#/\*.*?\*/#s', '', $root[1] ), $theirs, PREG_SET_ORDER );
        $this->assertSame( 1, preg_match( '#:host \{(.*?)\}#s', self::css(), $mine ) );
        foreach ( $theirs as $t )
            $this->assertStringContainsString( $t[1] . ': ' . preg_replace( '/\s+/', ' ', trim( $t[2] ) ) . ';', $mine[1],
                                               "{$t[1]} as admin4 has it (regenerate the tokens of expwt.css)" );
    }

    public function testEveryEdgeIsStyledAndTheOldSideIsReadAsAnEdge()
    {
        $css = self::css();
        foreach ( expwtState::EDGES as $edge )
        {
            $this->assertStringContainsString( ':host([data-edge="' . $edge . '"]) .wt-tab', $css, "F17: the tab on the $edge edge" );
            $this->assertStringContainsString( '[data-edge="' . $edge . '"]) .wt-panel', $css, "F17: the panel on the $edge edge" );
            $this->assertStringContainsString( '[data-edge="' . $edge . '"]) .wt-resize', $css, "F17: the panel's $edge resize handle" );
        }
        foreach ( expwtState::ALIGNS as $align )
            $this->assertStringContainsString( '[data-align="' . $align . '"]) .wt-tab', $css );
        $this->assertStringNotContainsString( 'data-side', $css, 'the attribute is data-edge now' );
        $js = file_get_contents( self::ext() . '/design/standard/javascript/expwt.js' );
        $this->assertStringContainsString( "s.side === 'left' || s.side === 'right' ? s.side", $js, 'a state saved with side is read as its edge' );
        foreach ( array( 'top', 'bottom' ) as $edge )
            $this->assertStringContainsString( "spacer('$edge'", $js, "push on the $edge edge" );
    }

    public function testTheBarOnTopAndBottomIsLaidOutAcrossTheWidth()
    {
        $css = self::css();
        // one row next to the edge: tabs, the open panel's main actions, the status, close
        $this->assertStringContainsString( '"tabs actions status close" "body body body body"', $css );
        $this->assertStringContainsString( '"body body body body" "tabs actions status close"', $css );
        // each panel across the width, a sheet on a phone
        foreach ( array( '.wt-fields', '.wt-create', '.wt-strip', '.wt-body[data-panel="me"]', '.wt-body[data-panel="welcome"]', '.wt-choice' ) as $part )
            $this->assertStringContainsString( ':host([data-orient="horizontal"]) ' . $part, $css, $part );
        $this->assertStringContainsString( '@media (max-width: 639.98px)', $css );
        $panels = file_get_contents( self::ext() . '/design/standard/javascript/expwt-panels.js' );
        $this->assertStringContainsString( 'ctx.primary(mainActions)', $panels, 'Save draft and Publish in the bar row' );
        $this->assertStringContainsString( "moreFields.insertBefore(inGrid.pop()", $panels, 'fields that do not fit go behind More' );
        $this->assertSame( 280, expwtSettings::fromArray( array() )['default_height'] );
    }

    public function testClosedTabWithinItsBudget()
    {
        $bytes = strlen( gzencode( file_get_contents( self::ext() . '/design/standard/javascript/expwt.js' ), 9 ) )
               + strlen( gzencode( self::css(), 9 ) );
        $this->assertLessThanOrEqual( 50 * 1024, $bytes, 'F12: the closed tab is at most 50 KB gzip' );
    }

    /** @return string[] the strings the JavaScript looks up with t('...') */
    private static function scriptStrings()
    {
        $out = array();
        foreach ( glob( self::ext() . '/design/standard/javascript/*.js' ) as $file )
        {
            preg_match_all( "/\\bt\\('((?:[^'\\\\]|\\\\.)*)'\\)/", file_get_contents( $file ), $m );
            foreach ( $m[1] as $s )
                $out[] = stripslashes( $s );
        }
        return array_values( array_unique( $out ) );
    }

    public function testEveryStringIsInTheSources()
    {
        foreach ( self::scriptStrings() as $s )
            $this->assertContains( $s, expwtStrings::SOURCES, "t('$s') is in expwtStrings::SOURCES (regenerate it)" );
        foreach ( expwtStrings::TAB as $s )
            $this->assertContains( $s, expwtStrings::SOURCES );
        $this->assertSame( count( expwtStrings::SOURCES ), count( array_unique( expwtStrings::SOURCES ) ) );
    }

    public function testEveryCatalogueHasEverySource()
    {
        $dirs = glob( self::ext() . '/translations/*', GLOB_ONLYDIR );
        if ( !$dirs )
            $this->markTestSkipped( 'no translations yet' );
        foreach ( $dirs as $dir )
        {
            $file = $dir . '/translation.ts';
            $this->assertFileExists( $file );
            $xml = simplexml_load_file( $file );
            $this->assertNotFalse( $xml, "$file is XML" );
            $have = array();
            foreach ( $xml->context as $context )
                if ( (string)$context->name === expwtStrings::CONTEXT )
                    foreach ( $context->message as $message )
                        $have[(string)$message->source] = (string)$message->translation;
            foreach ( expwtStrings::SOURCES as $s )
            {
                $this->assertArrayHasKey( $s, $have, basename( $dir ) . ": $s" );
                if ( basename( $dir ) !== 'untranslated' )
                    $this->assertNotSame( '', $have[$s], basename( $dir ) . ": $s is translated" );
                foreach ( array( '%name', '%time', '%fields', '%n' ) as $placeholder )
                    if ( strpos( $s, $placeholder ) !== false && basename( $dir ) !== 'untranslated' )
                        $this->assertStringContainsString( $placeholder, $have[$s], basename( $dir ) . ": $s keeps $placeholder" );
            }
        }
    }
}
