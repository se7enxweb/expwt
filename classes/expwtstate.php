<?php
/**
 * Where the sidebar is and how it looks for one user (decision 6): side, place along the edge, width, open or
 * closed, push or overlay, light or dark, the open panel. Kept per user on the server (the preference expwt_state,
 * so it follows the user to every device); a device can keep its own copy that wins there (the browser's storage,
 * the panel's "Only on this device"); a visitor who is not signed in has the browser's storage only.
 *
 * normalize() is pure: whatever a client sends, only these keys with valid values are kept.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

class expwtState
{
    const PREFERENCE = 'expwt_state';

    const PANELS = array( 'page', 'create', 'structure', 'me' );
    const MODES = array( 'push', 'overlay' );
    const THEMES = array( 'auto', 'light', 'dark' );

    /**
     * @param array $settings expwtSettings
     * @return array the state of a first visit
     */
    public static function defaults( array $settings )
    {
        return array( 'side' => $settings['default_side'], 'offset' => 0, 'width' => $settings['default_width'],
                      'open' => false, 'mode' => 'push', 'theme' => 'auto', 'panel' => 'page' );
    }

    /**
     * @param mixed $state what a client sent or the preference held
     * @param array $settings expwtSettings
     * @return array a complete, valid state
     */
    public static function normalize( $state, array $settings )
    {
        $s = self::defaults( $settings );
        if ( !is_array( $state ) )
            return $s;
        if ( isset( $state['side'] ) && in_array( $state['side'], array( 'left', 'right' ), true ) )
            $s['side'] = $state['side'];
        if ( isset( $state['offset'] ) && is_numeric( $state['offset'] ) )
            $s['offset'] = max( 0, min( 4000, (int)$state['offset'] ) );
        if ( isset( $state['width'] ) && is_numeric( $state['width'] ) )
            $s['width'] = max( $settings['min_width'], min( $settings['max_width'], (int)$state['width'] ) );
        if ( isset( $state['open'] ) )
            $s['open'] = $state['open'] === true || $state['open'] === 1 || $state['open'] === '1' || $state['open'] === 'true';
        if ( isset( $state['mode'] ) && in_array( $state['mode'], self::MODES, true ) )
            $s['mode'] = $state['mode'];
        if ( isset( $state['theme'] ) && in_array( $state['theme'], self::THEMES, true ) )
            $s['theme'] = $state['theme'];
        if ( isset( $state['panel'] ) && in_array( $state['panel'], self::PANELS, true ) )
            $s['panel'] = $state['panel'];
        return $s;
    }

    /**
     * @param string|null $json the preference's value
     * @param array $settings
     * @return array
     */
    public static function decode( $json, array $settings )
    {
        $data = is_string( $json ) && $json !== '' ? json_decode( $json, true ) : null;
        return self::normalize( $data, $settings );
    }

    /**
     * The light or dark mode of the sidebar: its own choice, else the user's choice for the administration
     * (the preference admin_color_mode: light, dark or system), else the system's.
     *
     * @param array $state
     * @param string|null $adminMode
     * @return string light, dark or system
     */
    public static function theme( array $state, $adminMode )
    {
        if ( $state['theme'] === 'light' || $state['theme'] === 'dark' )
            return $state['theme'];
        return in_array( $adminMode, array( 'light', 'dark' ), true ) ? $adminMode : 'system';
    }
}
