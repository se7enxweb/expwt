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
    /** The values when expwt.ini has none (the shipped expwt.ini has the same) */
    const DEFAULTS = array(
        'enabled' => false,
        'show_to_anonymous' => true,
        'data_layer' => 'rest',
        'inject_without_hook' => true,
        'default_side' => 'right',
        'default_width' => 380,
        'min_width' => 300,
        'max_width' => 720,
        'overlay_below_width' => 768,
        'shortcut_key' => 'E',
        'header_selector' => 'header, [role=banner], #header, .header',
        'hidden_classes' => array( 'common_ini_settings' ),
        'lock_seconds' => 300,
    );

    /** The data layers a setting may name */
    const DATA_LAYERS = array( 'rest', 'graphql' );

    /**
     * @param array $values keys of DEFAULTS; unknown keys are dropped, wrong values fall back to the default
     * @return array
     */
    public static function fromArray( array $values )
    {
        $s = array_intersect_key( $values, self::DEFAULTS ) + self::DEFAULTS;
        foreach ( array( 'enabled', 'show_to_anonymous', 'inject_without_hook' ) as $flag )
            $s[$flag] = self::isOn( $s[$flag] );
        $s['data_layer'] = in_array( $s['data_layer'], self::DATA_LAYERS, true ) ? $s['data_layer'] : 'rest';
        $s['default_side'] = $s['default_side'] === 'left' ? 'left' : 'right';
        foreach ( array( 'default_width', 'min_width', 'max_width', 'overlay_below_width', 'lock_seconds' ) as $number )
            $s[$number] = max( 0, (int)$s[$number] );
        if ( $s['min_width'] < 200 )
            $s['min_width'] = 200;
        if ( $s['max_width'] < $s['min_width'] )
            $s['max_width'] = $s['min_width'];
        $s['default_width'] = min( $s['max_width'], max( $s['min_width'], $s['default_width'] ) );
        $s['lock_seconds'] = max( 30, $s['lock_seconds'] );
        $key = strtoupper( substr( trim( (string)$s['shortcut_key'] ), 0, 1 ) );
        $s['shortcut_key'] = preg_match( '/^[A-Z0-9]$/', $key ) ? $key : 'E';
        $s['header_selector'] = trim( (string)$s['header_selector'] );
        $s['hidden_classes'] = array_values( array_filter( array_map( 'strval', (array)$s['hidden_classes'] ), 'strlen' ) );
        return $s;
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
        $read( 'ExpwtSettings', 'DefaultWidth', 'default_width' );
        $read( 'ExpwtSettings', 'MinWidth', 'min_width' );
        $read( 'ExpwtSettings', 'MaxWidth', 'max_width' );
        $read( 'ExpwtSettings', 'OverlayBelowWidth', 'overlay_below_width' );
        $read( 'ExpwtSettings', 'ShortcutKey', 'shortcut_key' );
        $read( 'ExpwtSettings', 'HeaderSelector', 'header_selector' );
        $read( 'CreateSettings', 'HiddenClassList', 'hidden_classes' );
        $read( 'LockSettings', 'LockSeconds', 'lock_seconds' );
        return self::fromArray( $values );
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
