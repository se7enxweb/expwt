<?php
/**
 * Where the sidebar is and how it looks for one user (decisions 6 and F17): the edge it is docked to (left, right,
 * top or bottom), on the top and bottom edges the end of the bar its tab is anchored to (start: left, end: right),
 * the place along the edge, the width (left and right) and the height (top and bottom), open or closed, push or
 * overlay, light or dark, the open panel. Kept per user on the server (the preference expwt_state, so it follows the
 * user to every device); a device can keep its own copy that wins there (the browser's storage, the panel's "Only on
 * this device"); a visitor who is not signed in has the browser's storage only.
 *
 * A state saved before F17 has side (left or right) instead of edge; it is read as that edge (migrated on read), and
 * the next save writes edge.
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

    const PANELS = array( 'page', 'create', 'structure', 'seo', 'sites', 'forms', 'newsletter', 'urls', 'insights', 'inbox', 'me' );
    const MODES = array( 'push', 'overlay' );
    const THEMES = array( 'auto', 'light', 'dark' );
    /** The edges the sidebar docks to: a vertical panel on left and right, a horizontal bar on top and bottom */
    const EDGES = array( 'left', 'right', 'top', 'bottom' );
    /** Where the tab of a top or bottom bar is anchored: start (the left end), end (the right end) */
    const ALIGNS = array( 'start', 'end' );
    /**
     * The sidebar's shortcuts (F30), each Alt+Shift+<letter> (Ctrl or Cmd with a letter belong to the browser, Alt with a
     * letter opens its menus, Ctrl+Alt is AltGr); Alt+Shift+1 to 9 open the panels in their order and are not remapped.
     * KEYS: what a user may remap, with its default (open: expwt.ini ShortcutKey).
     */
    const KEYS = array( 'open' => 'E', 'save' => 'D', 'inline' => 'K', 'help' => 'H' );
    /**
     * Alt+Shift+<letter> the browser or the system uses already (Chrome and Edge: I the feedback form, T the toolbar,
     * A inactive dialogs, B the bookmarks bar; ChromeOS: L the launcher, M the files, N the notifications, S the status
     * area): never a shortcut of the sidebar. Screen readers use Insert or Caps Lock (NVDA, JAWS, Narrator, Orca) and
     * Control+Option (VoiceOver), not Alt+Shift with a letter.
     */
    const RESERVED_KEYS = 'ABILMNST';

    /**
     * @param array $settings expwtSettings
     * @return array the state of a first visit
     */
    public static function defaults( array $settings )
    {
        return array( 'edge' => $settings['default_edge'], 'align' => $settings['default_align'], 'offset' => 0,
                      'width' => $settings['default_width'], 'height' => $settings['default_height'],
                      'open' => false, 'mode' => 'push', 'theme' => 'auto', 'panel' => 'page', 'keys' => array() );
    }

    /**
     * The user's own shortcuts (F30): only those that differ from the defaults are kept; a key that is no letter, is
     * reserved, or another shortcut has already is dropped (the default stays).
     *
     * @param mixed $keys action => letter
     * @param string $openDefault expwt.ini ShortcutKey
     * @return array action => letter
     */
    public static function keys( $keys, $openDefault = 'E' )
    {
        $defaults = array( 'open' => $openDefault ) + self::KEYS;
        if ( !is_array( $keys ) )
            return array();
        // in their order, each against the keys in effect so far (the defaults, the own keys kept before it), as the
        // browser does (expwt.js ExpwtKeys.map): each key once
        $out = array();
        $effective = $defaults;
        foreach ( array_keys( self::KEYS ) as $action )
        {
            if ( !isset( $keys[$action] ) || !is_string( $keys[$action] ) )
                continue;
            $key = strtoupper( trim( $keys[$action] ) );
            if ( !preg_match( '/^[A-Z]$/', $key ) || strpos( self::RESERVED_KEYS, $key ) !== false || $key === $defaults[$action] )
                continue;
            $others = $effective;
            unset( $others[$action] );
            if ( in_array( $key, $others, true ) )
                continue;
            $out[$action] = $key;
            $effective[$action] = $key;
        }
        return $out;
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
        if ( isset( $state['edge'] ) && in_array( $state['edge'], self::EDGES, true ) )
            $s['edge'] = $state['edge'];
        else if ( isset( $state['side'] ) && in_array( $state['side'], array( 'left', 'right' ), true ) )
            $s['edge'] = $state['side'];
        if ( isset( $state['align'] ) && in_array( $state['align'], self::ALIGNS, true ) )
            $s['align'] = $state['align'];
        if ( isset( $state['offset'] ) && is_numeric( $state['offset'] ) )
            $s['offset'] = max( 0, min( 4000, (int)$state['offset'] ) );
        if ( isset( $state['width'] ) && is_numeric( $state['width'] ) )
            $s['width'] = max( $settings['min_width'], min( $settings['max_width'], (int)$state['width'] ) );
        if ( isset( $state['height'] ) && is_numeric( $state['height'] ) )
            $s['height'] = max( $settings['min_height'], min( $settings['max_height'], (int)$state['height'] ) );
        if ( isset( $state['open'] ) )
            $s['open'] = $state['open'] === true || $state['open'] === 1 || $state['open'] === '1' || $state['open'] === 'true';
        if ( isset( $state['mode'] ) && in_array( $state['mode'], self::MODES, true ) )
            $s['mode'] = $state['mode'];
        if ( isset( $state['theme'] ) && in_array( $state['theme'], self::THEMES, true ) )
            $s['theme'] = $state['theme'];
        // a panel of the sidebar, or of an extension (decision 32: its id; the sidebar opens Page when it is not there)
        if ( isset( $state['panel'] ) && is_string( $state['panel'] ) && ( in_array( $state['panel'], self::PANELS, true ) || preg_match( '/^[a-z][a-z0-9_]{0,39}$/', $state['panel'] ) ) )
            $s['panel'] = $state['panel'];
        if ( isset( $state['keys'] ) )
            $s['keys'] = self::keys( $state['keys'], isset( $settings['shortcut_key'] ) ? $settings['shortcut_key'] : 'E' );
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
