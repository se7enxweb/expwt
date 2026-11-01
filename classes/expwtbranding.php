<?php
/**
 * The branding of the sidebar per siteaccess (E10, expwt.ini [BrandingSettings]): a name in place of "Website sidebar",
 * a logo beside it and an accent color, light and dark. The Exponential credit stays in Help whatever is set here.
 *
 * The accent color is checked before it is used (WCAG 2.2 AA, both modes): the fill of the selected tab and the
 * primary button needs a text color at least 4.5:1 on it (white or near black, whichever reads better) and has to
 * stand at least 3:1 against the panel's surface. A color that fails is not used (the admin4 accent stays) and Setup >
 * Website sidebar panels says why. Links, the focus ring and the marks take a shade of the same hue that reaches 4.5:1
 * against the surface and the cards. The dark mode takes AccentColorDark, or, when that is empty, AccentColor lightened
 * in steps until it passes.
 *
 * Pure (plain arrays, tested without a database) except fromIni().
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

class expwtBranding
{
    /** The surfaces the accent stands on (the admin4 tokens copied into expwt.css): surface and card, per mode */
    const SURFACES = array( 'light' => array( '#ffffff', '#ffffff' ), 'dark' => array( '#1a1f29', '#1e242f' ) );
    /** The text colors on an accent fill: white, or the near black of the admin4 dark tokens */
    const ON = array( '#ffffff', '#0e1218' );
    /** WCAG 2.2: text (1.4.3) and parts of controls (1.4.11) */
    const TEXT_MIN = 4.5;
    const UI_MIN = 3.0;
    /** The longest name; a longer one is cut */
    const NAME_MAX = 60;
    /** The tokens a branding sets, the only ones the sidebar's script accepts */
    const TOKENS = array( '--a4-accent', '--a4-accent-hover', '--a4-accent-strong', '--a4-accent-press', '--a4-accent-text',
                          '--a4-accent-soft', '--a4-on-accent', '--a4-on-accent-hover', '--a4-ring' );

    /**
     * @param eZINI|null $ini expwt.ini of this request (or of another siteaccess)
     * @return array fromArray()
     */
    public static function fromIni( $ini = null )
    {
        $ini = $ini ?: eZINI::instance( 'expwt.ini' );
        $values = array();
        foreach ( array( 'Name' => 'name', 'Logo' => 'logo', 'AccentColor' => 'accent', 'AccentColorDark' => 'accent_dark' ) as $name => $key )
            if ( $ini->hasVariable( 'BrandingSettings', $name ) )
                $values[$key] = $ini->variable( 'BrandingSettings', $name );
        return self::fromArray( $values );
    }

    /**
     * @param array $v name, logo, accent, accent_dark (each optional; empty: the sidebar's own)
     * @return array name ('' for the sidebar's own), logo ('' for none), light and dark (token => value, empty for the
     *               admin4 accent), refused (setting, value, and for a color ratio and needed)
     */
    public static function fromArray( array $v )
    {
        $out = array( 'name' => '', 'logo' => '', 'light' => array(), 'dark' => array(), 'refused' => array() );
        $name = trim( preg_replace( '/\s+/u', ' ', (string)( isset( $v['name'] ) ? $v['name'] : '' ) ) );
        $out['name'] = mb_substr( $name, 0, self::NAME_MAX );
        $logo = trim( (string)( isset( $v['logo'] ) ? $v['logo'] : '' ) );
        if ( $logo !== '' )
        {
            if ( self::logoOk( $logo ) )
                $out['logo'] = $logo;
            else
                $out['refused'][] = array( 'setting' => 'Logo', 'value' => $logo );
        }
        $accent = trim( (string)( isset( $v['accent'] ) ? $v['accent'] : '' ) );
        $dark = trim( (string)( isset( $v['accent_dark'] ) ? $v['accent_dark'] : '' ) );
        if ( $accent !== '' )
        {
            $color = self::hex( $accent );
            $check = $color === null ? array( 'ratio' => 0.0, 'needed' => self::TEXT_MIN ) : self::check( $color, 'light' );
            if ( isset( $check['tokens'] ) )
                $out['light'] = $check['tokens'];
            else
                $out['refused'][] = array( 'setting' => 'AccentColor', 'value' => $accent, 'ratio' => $check['ratio'], 'needed' => $check['needed'] );
        }
        if ( $dark !== '' )
        {
            $color = self::hex( $dark );
            $check = $color === null ? array( 'ratio' => 0.0, 'needed' => self::TEXT_MIN ) : self::check( $color, 'dark' );
            if ( isset( $check['tokens'] ) )
                $out['dark'] = $check['tokens'];
            else
                $out['refused'][] = array( 'setting' => 'AccentColorDark', 'value' => $dark, 'ratio' => $check['ratio'], 'needed' => $check['needed'] );
        }
        else if ( $out['light'] )
        {
            // the light accent, lightened in steps of a tenth towards white until it passes on the dark surfaces
            $base = $out['light']['--a4-accent'];
            for ( $i = 0; $i <= 10 && !$out['dark']; $i++ )
            {
                $check = self::check( self::mix( $base, '#ffffff', $i / 10 ), 'dark' );
                if ( isset( $check['tokens'] ) )
                    $out['dark'] = $check['tokens'];
            }
        }
        return $out;
    }

    /**
     * What the sidebar's script gets (data-config brand): no refusals, and nothing at all for the sidebar's own look.
     *
     * @param array $b fromArray()
     * @param string $wwwDir the installation's web directory (eZSys::wwwDir()) for a logo path
     * @return array
     */
    public static function client( array $b, $wwwDir = '' )
    {
        $out = array();
        if ( $b['name'] !== '' )
            $out['name'] = $b['name'];
        if ( $b['logo'] !== '' )
            $out['logo'] = strpos( $b['logo'], 'https://' ) === 0 ? $b['logo'] : rtrim( (string)$wwwDir, '/' ) . $b['logo'];
        if ( $b['light'] )
            $out['light'] = $b['light'];
        if ( $b['dark'] )
            $out['dark'] = $b['dark'];
        return $out;
    }

    /**
     * A logo: a path from the site's root (/extension/..., /var/storage/..., /design/...) or an https address, with
     * none of the characters that could leave the attribute or the URL.
     *
     * @param string $logo
     * @return bool
     */
    public static function logoOk( $logo )
    {
        return (bool)preg_match( '#^(?:https://[A-Za-z0-9.-]+(?::\d+)?)?/(?!/)[A-Za-z0-9._~/%+-]*\.(?:svg|png|webp|jpe?g|gif|avif)(?:\?[A-Za-z0-9._~=&%-]*)?$#i', $logo )
               && strpos( $logo, '..' ) === false;
    }

    /**
     * The tokens of an accent color in a mode, or why it is not used.
     *
     * @param string $color #rrggbb
     * @param string $mode light or dark
     * @return array tokens; or ratio and needed of the check that failed
     */
    public static function check( $color, $mode )
    {
        list( $surface, $card ) = self::SURFACES[$mode === 'dark' ? 'dark' : 'light'];
        $on = self::contrast( $color, self::ON[0] ) >= self::contrast( $color, self::ON[1] ) ? self::ON[0] : self::ON[1];
        $text = self::contrast( $color, $on );
        if ( $text < self::TEXT_MIN )
            return array( 'ratio' => round( $text, 2 ), 'needed' => self::TEXT_MIN );
        $ui = min( self::contrast( $color, $surface ), self::contrast( $color, $card ) );
        if ( $ui < self::UI_MIN )
            return array( 'ratio' => round( $ui, 2 ), 'needed' => self::UI_MIN );
        // links, the ring and the marks: the same hue, darker on light and lighter on dark, until 4.5:1 on both surfaces
        $towards = $mode === 'dark' ? '#ffffff' : '#000000';
        $link = $color;
        for ( $i = 1; $i <= 10 && min( self::contrast( $link, $surface ), self::contrast( $link, $card ) ) < self::TEXT_MIN; $i++ )
            $link = self::mix( $color, $towards, $i / 10 );
        // hover and press: further from the text on the fill, so the text keeps (and gains) contrast
        $press = self::mix( $color, $on === self::ON[0] ? '#000000' : '#ffffff', 0.15 );
        list( $r, $g, $b ) = self::rgb( $color );
        return array( 'tokens' => array(
            '--a4-accent' => $color, '--a4-accent-hover' => $press, '--a4-accent-strong' => $color, '--a4-accent-press' => $press,
            '--a4-accent-text' => $link, '--a4-accent-soft' => sprintf( 'rgba(%d, %d, %d, %s)', $r, $g, $b, $mode === 'dark' ? '0.18' : '0.12' ),
            '--a4-on-accent' => $on, '--a4-on-accent-hover' => $on, '--a4-ring' => $link ) );
    }

    /**
     * @param string $value #rgb, #rrggbb, with or without #
     * @return string|null #rrggbb in lower case
     */
    public static function hex( $value )
    {
        $value = strtolower( ltrim( trim( (string)$value ), '#' ) );
        if ( preg_match( '/^[0-9a-f]{3}$/', $value ) )
            $value = $value[0] . $value[0] . $value[1] . $value[1] . $value[2] . $value[2];
        return preg_match( '/^[0-9a-f]{6}$/', $value ) ? '#' . $value : null;
    }

    /** @return int[] r, g, b of #rrggbb */
    public static function rgb( $hex )
    {
        return array( hexdec( substr( $hex, 1, 2 ) ), hexdec( substr( $hex, 3, 2 ) ), hexdec( substr( $hex, 5, 2 ) ) );
    }

    /** @return float the relative luminance of #rrggbb (WCAG 2.2) */
    public static function luminance( $hex )
    {
        $l = array();
        foreach ( self::rgb( $hex ) as $c )
        {
            $c /= 255;
            $l[] = $c <= 0.04045 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
        }
        return 0.2126 * $l[0] + 0.7152 * $l[1] + 0.0722 * $l[2];
    }

    /** @return float the contrast ratio of two #rrggbb colors (1 to 21) */
    public static function contrast( $a, $b )
    {
        $x = self::luminance( $a );
        $y = self::luminance( $b );
        return ( max( $x, $y ) + 0.05 ) / ( min( $x, $y ) + 0.05 );
    }

    /** @return string #rrggbb: $a moved $t (0 to 1) of the way to $b */
    public static function mix( $a, $b, $t )
    {
        $x = self::rgb( $a );
        $y = self::rgb( $b );
        $out = '#';
        for ( $i = 0; $i < 3; $i++ )
            $out .= sprintf( '%02x', (int)round( $x[$i] + ( $y[$i] - $x[$i] ) * $t ) );
        return $out;
    }
}
