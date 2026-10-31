<?php
/**
 * Two editors, one page (decision F11): a soft lock tells the second editor that the first is at work; if both still
 * save, nothing is lost. The second save is compared field by field with what that editor started from (base), what
 * they send (mine) and what is there now (theirs, the version published or saved in between):
 *
 *   mine === base                       the field was not touched here: theirs is kept
 *   theirs === base                     only this editor changed it: mine is taken
 *   mine === theirs                     both made the same change
 *   otherwise                           a conflict: the editor chooses per field (mine or theirs)
 *
 * Pure, tested without a database.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

class expwtMerge
{
    /**
     * @param array $base identifier => value the editor started from
     * @param array $mine identifier => value the editor sends
     * @param array $theirs identifier => value there now
     * @param array $choices identifier => "mine" or "theirs": the editor's answers to an earlier conflict
     * @return array merged (identifier => value to write), conflicts (identifier => base, mine, theirs)
     */
    public static function merge( array $base, array $mine, array $theirs, array $choices = array() )
    {
        $merged = array();
        $conflicts = array();
        foreach ( $mine as $id => $value )
        {
            $value = (string)$value;
            $was = array_key_exists( $id, $base ) ? (string)$base[$id] : null;
            $now = array_key_exists( $id, $theirs ) ? (string)$theirs[$id] : null;
            if ( $was !== null && $value === $was )
                continue;
            if ( $now === null || $was === null || $now === $was || $now === $value )
            {
                $merged[$id] = $value;
                continue;
            }
            $choice = isset( $choices[$id] ) ? $choices[$id] : null;
            if ( $choice === 'mine' )
                $merged[$id] = $value;
            else if ( $choice === 'theirs' )
                continue;
            else
                $conflicts[$id] = array( 'base' => $was, 'mine' => $value, 'theirs' => $now );
        }
        return array( 'merged' => $merged, 'conflicts' => $conflicts );
    }
}
