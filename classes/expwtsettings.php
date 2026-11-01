<?php
/**
 * expwt.ini, read once per request into a plain array (fromIni()), or built from an array in a test (fromArray()).
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

class expwtSettings
{
    /** The values when expwt.ini has none: the shipped expwt.ini documents them commented out and sets nothing, so a
     *  siteaccess can set every one (the kernel reads an extension in ActiveExtensions[] after settings/siteaccess/) */
    const DEFAULTS = array(
        'enabled' => false,
        'show_to_anonymous' => 'disabled',
        'data_layer' => 'rest',
        'inject_without_hook' => true,
        'default_side' => 'right',
        'default_edge' => '',
        'default_align' => 'end',
        'default_width' => 380,
        'min_width' => 300,
        'max_width' => 720,
        'default_height' => 280,
        'min_height' => 160,
        'max_height' => 640,
        'overlay_below_width' => 768,
        'shortcut_key' => 'E',
        'header_selector' => 'header, [role=banner], #header, .header',
        'footer_selector' => 'footer, [role=contentinfo], #cookie-bar, .cookie-bar, #cookie-banner, .cookie-banner, #cookie-consent, .cookie-consent, .cc-window, #CybotCookiebotDialog, #onetrust-banner-sdk',
        'show_in_admin' => false,
        'siteaccess_list' => array(),
        'siteaccess_exclude' => array(),
        'hidden_classes' => array( 'common_ini_settings' ),
        'lock_seconds' => 300,
        // the frames of the device preview (E8): id;name;width;height in CSS pixels, and the ids shown first (empty: all)
        'devices' => array( 'iphone13;iPhone 13;390;844', 'pixel7;Pixel 7;412;915', 'tablet;Tablet;820;1180',
                            'zoom200;1920 wide at 200% zoom;960;900', 'desktop;Desktop;1440;900' ),
        'devices_shown' => array(),
    );

    /** The widths and heights a device frame may have, in CSS pixels (a custom width as well) */
    const DEVICE_MIN = 240;
    const DEVICE_MAX_WIDTH = 2560;
    const DEVICE_MAX_HEIGHT = 2400;

    /** The data layers a setting may name */
    const DATA_LAYERS = array( 'rest', 'graphql' );

    /**
     * @param array $values keys of DEFAULTS; unknown keys are dropped, wrong values fall back to the default
     * @return array
     */
    public static function fromArray( array $values )
    {
        $s = array_intersect_key( $values, self::DEFAULTS ) + self::DEFAULTS;
        foreach ( array( 'siteaccess_list', 'siteaccess_exclude' ) as $list )
            $s[$list] = array_values( array_filter( array_map( 'trim', array_map( 'strval', (array)$s[$list] ) ), 'strlen' ) );
        $s['show_to_anonymous'] = self::anonymousMode( $s['show_to_anonymous'] );
        foreach ( array( 'enabled', 'inject_without_hook', 'show_in_admin' ) as $flag )
            $s[$flag] = self::isOn( $s[$flag] );
        $s['data_layer'] = in_array( $s['data_layer'], self::DATA_LAYERS, true ) ? $s['data_layer'] : 'rest';
        $s['default_side'] = $s['default_side'] === 'left' ? 'left' : 'right';
        // DefaultEdge (F17) wins; an installation that only has DefaultSide keeps its side
        $s['default_edge'] = in_array( $s['default_edge'], array( 'left', 'right', 'top', 'bottom' ), true ) ? $s['default_edge'] : $s['default_side'];
        $s['default_align'] = $s['default_align'] === 'start' ? 'start' : 'end';
        foreach ( array( 'default_width', 'min_width', 'max_width', 'default_height', 'min_height', 'max_height', 'overlay_below_width', 'lock_seconds' ) as $number )
            $s[$number] = max( 0, (int)$s[$number] );
        if ( $s['min_width'] < 200 )
            $s['min_width'] = 200;
        if ( $s['max_width'] < $s['min_width'] )
            $s['max_width'] = $s['min_width'];
        $s['default_width'] = min( $s['max_width'], max( $s['min_width'], $s['default_width'] ) );
        if ( $s['min_height'] < 120 )
            $s['min_height'] = 120;
        if ( $s['max_height'] < $s['min_height'] )
            $s['max_height'] = $s['min_height'];
        $s['default_height'] = min( $s['max_height'], max( $s['min_height'], $s['default_height'] ) );
        $s['lock_seconds'] = max( 30, $s['lock_seconds'] );
        $key = strtoupper( substr( trim( (string)$s['shortcut_key'] ), 0, 1 ) );
        $s['shortcut_key'] = preg_match( '/^[A-Z0-9]$/', $key ) ? $key : 'E';
        $s['header_selector'] = trim( (string)$s['header_selector'] );
        $s['footer_selector'] = trim( (string)$s['footer_selector'] );
        $s['hidden_classes'] = array_values( array_filter( array_map( 'strval', (array)$s['hidden_classes'] ), 'strlen' ) );
        $s['devices'] = self::devices( (array)$s['devices'] );
        $ids = array_map( function ( $d ) { return $d['id']; }, $s['devices'] );
        $s['devices_shown'] = array_values( array_unique( array_intersect( array_map( 'strval', (array)$s['devices_shown'] ), $ids ) ) );
        return $s;
    }

    /**
     * The frames of the device preview (E8) from [DevicePreviewSettings] DeviceList[]: each line id;name;width;height
     * (CSS pixels). A line that is not one, an id given twice or a size outside DEVICE_MIN ... DEVICE_MAX_* is left
     * out. Pure.
     *
     * @param string[] $lines
     * @return array[] id, name, width, height
     */
    public static function devices( array $lines )
    {
        $out = array();
        $seen = array();
        foreach ( $lines as $line )
        {
            $parts = array_map( 'trim', explode( ';', (string)$line ) );
            if ( count( $parts ) !== 4 || !preg_match( '/^[a-z0-9_-]{1,32}$/', $parts[0] ) || $parts[1] === '' || isset( $seen[$parts[0]] )
                 || !ctype_digit( $parts[2] ) || !ctype_digit( $parts[3] ) )
                continue;
            $width = (int)$parts[2];
            $height = (int)$parts[3];
            if ( $width < self::DEVICE_MIN || $width > self::DEVICE_MAX_WIDTH || $height < self::DEVICE_MIN || $height > self::DEVICE_MAX_HEIGHT )
                continue;
            $seen[$parts[0]] = true;
            $out[] = array( 'id' => $parts[0], 'name' => mb_substr( $parts[1], 0, 60 ), 'width' => $width, 'height' => $height );
        }
        return $out;
    }

    /**
     * @param eZINI|null $ini expwt.ini of this request (or of another siteaccess)
     * @return array
     */
    public static function fromIni( $ini = null )
    {
        $ini = $ini ?: eZINI::instance( 'expwt.ini' );
        $read = function ( $group, $name, $key ) use ( $ini, &$values ) {
            if ( $ini->hasVariable( $group, $name ) )
                $values[$key] = $ini->variable( $group, $name );
        };
        $values = array();
        $read( 'ExpwtSettings', 'Enabled', 'enabled' );
        $read( 'ExpwtSettings', 'ShowToAnonymous', 'show_to_anonymous' );
        $read( 'ExpwtSettings', 'DataLayer', 'data_layer' );
        $read( 'ExpwtSettings', 'InjectWithoutHook', 'inject_without_hook' );
        $read( 'ExpwtSettings', 'DefaultSide', 'default_side' );
        $read( 'ExpwtSettings', 'DefaultEdge', 'default_edge' );
        $read( 'ExpwtSettings', 'DefaultAlign', 'default_align' );
        $read( 'ExpwtSettings', 'DefaultWidth', 'default_width' );
        $read( 'ExpwtSettings', 'MinWidth', 'min_width' );
        $read( 'ExpwtSettings', 'MaxWidth', 'max_width' );
        $read( 'ExpwtSettings', 'DefaultHeight', 'default_height' );
        $read( 'ExpwtSettings', 'MinHeight', 'min_height' );
        $read( 'ExpwtSettings', 'MaxHeight', 'max_height' );
        $read( 'ExpwtSettings', 'OverlayBelowWidth', 'overlay_below_width' );
        $read( 'ExpwtSettings', 'ShortcutKey', 'shortcut_key' );
        $read( 'ExpwtSettings', 'HeaderSelector', 'header_selector' );
        $read( 'ExpwtSettings', 'FooterSelector', 'footer_selector' );
        $read( 'ExpwtSettings', 'ShowInAdmin', 'show_in_admin' );
        $read( 'ExpwtSettings', 'SiteAccessList', 'siteaccess_list' );
        $read( 'ExpwtSettings', 'SiteAccessExcludeList', 'siteaccess_exclude' );
        $read( 'CreateSettings', 'HiddenClassList', 'hidden_classes' );
        $read( 'LockSettings', 'LockSeconds', 'lock_seconds' );
        $read( 'DevicePreviewSettings', 'DeviceList', 'devices' );
        $read( 'DevicePreviewSettings', 'ShownDeviceList', 'devices_shown' );
        return self::fromArray( $values );
    }

    /**
     * ShowToAnonymous (decision 8, owner 2026-10-31): disabled (the default, also for any value it does not know),
     * enabled, or policy (only when the Anonymous user's roles grant expwt/use). Pure.
     *
     * @param mixed $value
     * @return string enabled, disabled or policy
     */
    public static function anonymousMode( $value )
    {
        if ( is_string( $value ) && strtolower( trim( $value ) ) === 'policy' )
            return 'policy';
        return self::isOn( $value ) ? 'enabled' : 'disabled';
    }

    /**
     * Whether a visitor who is not signed in gets the sidebar. Pure.
     *
     * @param string $mode anonymousMode()
     * @param string $accessWord the Anonymous user's access word for expwt/use (yes, limited, no)
     * @return bool
     */
    public static function anonymousMayUse( $mode, $accessWord )
    {
        return $mode === 'enabled' || ( $mode === 'policy' && in_array( $accessWord, array( 'yes', 'limited' ), true ) );
    }

    /**
     * Whether the sidebar is on a siteaccess (owner 2026-10-31, revising decision 33): only those of SiteAccessList[]
     * when it names any, never those of SiteAccessExcludeList[], and an administration only with ShowInAdmin=enabled.
     * Pure.
     *
     * @param array $s fromArray()
     * @param string $siteaccess
     * @param bool $administration the siteaccess is an administration (currentIsAdministration())
     * @return bool
     */
    public static function showHere( array $s, $siteaccess, $administration )
    {
        if ( !$s['enabled'] )
            return false;
        if ( $s['siteaccess_list'] && !in_array( (string)$siteaccess, $s['siteaccess_list'], true ) )
            return false;
        if ( in_array( (string)$siteaccess, $s['siteaccess_exclude'], true ) )
            return false;
        return !$administration || $s['show_in_admin'];
    }

    /**
     * Whether the siteaccess of this request is an administration, as the kernel decides it for its siteaccess menu
     * (ezpSiteAccessMenu::isAdministration(): [AdminSiteAccessMenu] AdminSiteAccessList[] when it names any, else an
     * administration design - admin, admin2, admin3, admin4, admin4l, adminui, the editor's - or a FrontSiteAccess).
     *
     * @return bool
     */
    public static function currentIsAdministration()
    {
        $access = eZSiteAccess::current();
        $name = is_array( $access ) && isset( $access['name'] ) ? (string)$access['name'] : '';
        $ini = eZINI::instance();
        $design = $ini->hasVariable( 'DesignSettings', 'SiteDesign' ) ? (string)$ini->variable( 'DesignSettings', 'SiteDesign' ) : '';
        $info = array( 'site_design' => $design, 'site_designs' => array( $design ),
                       'front_site_access' => $ini->hasVariable( 'SiteSettings', 'FrontSiteAccess' ) ? (string)$ini->variable( 'SiteSettings', 'FrontSiteAccess' ) : '' );
        if ( class_exists( 'ezpSiteAccessMenu' ) && method_exists( 'ezpSiteAccessMenu', 'isAdministration' ) )
        {
            $menu = ezpSiteAccessMenu::settings();
            return ezpSiteAccessMenu::isAdministration( $name, $menu['admin'], $info );
        }
        return in_array( strtolower( $design ), array( 'admin', 'admin2', 'admin3', 'admin4', 'admin4l', 'adminui', 'editor' ), true )
            || $info['front_site_access'] !== '';
    }

    /** @return bool the sidebar is on for the siteaccess of this request */
    public static function onHere( array $s )
    {
        $access = eZSiteAccess::current();
        $name = is_array( $access ) && isset( $access['name'] ) ? (string)$access['name'] : '';
        return self::showHere( $s, $name, self::currentIsAdministration() );
    }

    /**
     * @param mixed $value enabled, true, 1, yes, on
     * @return bool
     */
    public static function isOn( $value )
    {
        if ( is_bool( $value ) )
            return $value;
        return in_array( strtolower( trim( (string)$value ) ), array( 'enabled', 'true', '1', 'yes', 'on' ), true );
    }
}
