<?php
/**
 * The includes a site design uses in place of ezwt (design:parts/expwt_edit_actions.tpl, expwt_versionview_actions.tpl,
 * expwt_page_toolbar.tpl) and the compatibility names of ezwt (website_toolbar*.tpl): the kernel's action names, no
 * inline CSS or script, every string in the expwt context, the shims including expwt's own templates, and the
 * stylesheet drawn from its tokens only, each token with a dark value.
 *
 * Reads files only.
 *
 * Run: php vendor/bin/phpunit extension/expwt/tests/phpunit/unit/expwtEditActionsTest.php
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */
require_once dirname( __DIR__ ) . '/bootstrap.php';

class expwtEditActionsTest extends PHPUnit\Framework\TestCase
{
    private static function parts()
    {
        return dirname( __DIR__, 3 ) . '/design/standard/templates/parts';
    }

    private static function tpl( $name )
    {
        return file_get_contents( self::parts() . '/' . $name );
    }

    public function testEditActionsPostTheKernelActions()
    {
        $tpl = self::tpl( 'expwt_edit_actions.tpl' );
        foreach ( array( 'PublishButton', 'StoreButton', 'StoreExitButton', 'DiscardButton', 'VersionsButton', 'PreviewButton', 'FromLanguageButton' ) as $name )
            $this->assertMatchesRegularExpression( '/<button[^>]+type="submit" name="' . $name . '"/', $tpl, $name );
        $this->assertStringContainsString( 'data-expwt-confirm=', $tpl );
        $this->assertStringContainsString( 'name="FromLanguage"', $tpl );
        $this->assertStringContainsString( 'role="group"', $tpl );
    }

    public function testVersionviewActionsPostTheKernelActions()
    {
        $tpl = self::tpl( 'expwt_versionview_actions.tpl' );
        foreach ( array( 'EditButton', 'PreviewPublishButton', 'VersionsButton' ) as $name )
            $this->assertStringContainsString( 'name="' . $name . '"', $tpl, $name );
        $this->assertStringContainsString( "concat( 'content/versionview/'", $tpl );
    }

    public function testPageToolbarDrawsNothingWhereTheSidebarIsOn()
    {
        $tpl = self::tpl( 'expwt_page_toolbar.tpl' );
        $this->assertStringContainsString( "ezini( 'ExpwtSettings', 'Enabled', 'expwt.ini' )|ne( 'enabled' )", $tpl );
        $this->assertStringContainsString( 'can_edit', $tpl );
    }

    public function testShimsIncludeExpwtsTemplates()
    {
        $map = array( 'website_toolbar.tpl' => 'expwt_page_toolbar.tpl', 'website_toolbar_edit.tpl' => 'expwt_edit_actions.tpl',
                      'website_toolbar_versionview.tpl' => 'expwt_versionview_actions.tpl' );
        foreach ( $map as $shim => $target )
        {
            $this->assertStringContainsString( "{include uri='design:parts/$target'}", self::tpl( $shim ), $shim );
            $this->assertStringNotContainsString( 'ezwt', strtolower( preg_replace( '/\{\*.*?\*\}/s', '', self::tpl( $shim ) ) ), $shim );
        }
    }

    public function testNoInlineCssOrScriptAndOnlyExpwtStrings()
    {
        foreach ( glob( self::parts() . '/*.tpl' ) as $file )
        {
            $text = file_get_contents( $file );
            $this->assertDoesNotMatchRegularExpression( '/<style\b/i', $text, $file );
            $this->assertDoesNotMatchRegularExpression( '/\sstyle\s*=/i', $text, $file );
            $this->assertDoesNotMatchRegularExpression( '/<script\b|\son[a-z]+\s*=/i', $text, $file );
            preg_match_all( "/\\|i18n\\(\\s*'([^']+)'/", $text, $m );
            foreach ( $m[1] as $context )
                $this->assertSame( expwtStrings::CONTEXT, $context, $file );
            preg_match_all( "/'((?:[^'\\\\]|\\\\.)*)'\\|i18n\\(\\s*'extension\\/expwt'/", $text, $s );
            foreach ( $s[1] as $source )
                $this->assertContains( stripslashes( $source ), expwtStrings::SOURCES, basename( $file ) . ": $source" );
        }
    }

    public function testStylesheetUsesItsTokensWithADarkValueEach()
    {
        $css = file_get_contents( dirname( __DIR__, 3 ) . '/design/standard/stylesheets/expwt-actions.css' );
        $this->assertSame( 1, preg_match( '#/\* tokens:start \*/(.*?)/\* tokens:end \*/#s', $css, $tokens ) );
        $rules = preg_replace( '#/\*.*?\*/#s', '', str_replace( $tokens[0], '', $css ) );
        $this->assertDoesNotMatchRegularExpression( '/#[0-9a-fA-F]{3,8}\b/', $rules, 'no hex colour outside the tokens' );
        $this->assertDoesNotMatchRegularExpression( '/\b(rgb|rgba|hsl|hsla)\(/', $rules );
        $this->assertDoesNotMatchRegularExpression( '/--[a-z][a-z0-9-]*\s*:/', $rules, 'no token defined outside the block' );
        $this->assertDoesNotMatchRegularExpression( '/\d+px/', preg_replace( '/(outline(-offset)?|border(-color)?):[^;]+;/', '', $rules ), 'spaces and radii from the tokens' );
        $this->assertStringNotContainsString( '!important', $rules );
        $this->assertSame( 1, preg_match( '#\.expwt-actions \{(.*?)\}#s', $tokens[1], $light ) );
        $this->assertSame( 1, preg_match( '#\[data-theme="dark"\] \.expwt-actions,.*?\{(.*?)\}#s', $tokens[1], $dark ) );
        preg_match_all( '/(--expwt-act-[a-z0-9-]+):\s*#/', $light[1], $colours );
        $this->assertNotEmpty( $colours[1] );
        foreach ( $colours[1] as $token )
            $this->assertStringContainsString( $token . ':', $dark[1], "$token has a dark value" );
        preg_match_all( '/var\((--[a-z0-9-]+)\)/', $rules, $used );
        foreach ( array_unique( $used[1] ) as $token )
            $this->assertStringContainsString( $token . ':', $light[1], "$token is defined" );
    }
}
