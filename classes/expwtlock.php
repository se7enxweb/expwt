<?php
/**
 * Soft locks (decision F11): who is editing an object right now. A lock never refuses a save; it is shown to the
 * other editors ("Anna is editing this page since 10:42"), and a save after someone else's goes through the field by
 * field merge (expwtMerge). A lock lasts expwt.ini [LockSettings] LockSeconds after the last heartbeat of the editor
 * who holds it; a second editor may take it over, and the first then sees the take-over notice.
 *
 * decide() is pure; the locks themselves are kept in the site data table (ezsite_data, key expwt_lock_<object id>),
 * which every database engine has.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

class expwtLock
{
    const KEY_PREFIX = 'expwt_lock_';

    /**
     * @param array|null $current the lock there now: user, name, since, until
     * @param int $userId who asks
     * @param string $name their name
     * @param int $now
     * @param int $seconds
     * @param bool $takeOver take another editor's lock
     * @return array lock (the lock after the call, or null), held_by_other (the other editor's lock, or null), changed
     */
    public static function decide( $current, $userId, $name, $now, $seconds, $takeOver = false )
    {
        $live = is_array( $current ) && isset( $current['user'], $current['until'] ) && (int)$current['until'] > $now;
        if ( $live && (int)$current['user'] !== (int)$userId && !$takeOver )
            return array( 'lock' => $current, 'held_by_other' => $current, 'changed' => false );
        $since = $live && (int)$current['user'] === (int)$userId ? (int)$current['since'] : $now;
        $lock = array( 'user' => (int)$userId, 'name' => (string)$name, 'since' => $since, 'until' => $now + $seconds,
                       'took_over_from' => $live && (int)$current['user'] !== (int)$userId ? (int)$current['user'] : null );
        return array( 'lock' => $lock, 'held_by_other' => null, 'changed' => true );
    }

    /**
     * @param array|null $current
     * @param int $userId
     * @param int $now
     * @return array|null the lock someone else holds and that still counts
     */
    public static function heldByOther( $current, $userId, $now )
    {
        if ( !is_array( $current ) || !isset( $current['user'], $current['until'] ) )
            return null;
        return (int)$current['until'] > $now && (int)$current['user'] !== (int)$userId ? $current : null;
    }

    /** @return array|null */
    public static function load( $objectId )
    {
        $row = eZSiteData::fetchByName( self::KEY_PREFIX . (int)$objectId );
        if ( !$row instanceof eZSiteData )
            return null;
        $data = json_decode( (string)$row->attribute( 'value' ), true );
        return is_array( $data ) ? $data : null;
    }

    public static function store( $objectId, array $lock )
    {
        $name = self::KEY_PREFIX . (int)$objectId;
        $row = eZSiteData::fetchByName( $name );
        if ( !$row instanceof eZSiteData )
            $row = eZSiteData::create( $name, '' );
        $row->setAttribute( 'value', json_encode( $lock ) );
        $row->store();
    }

    public static function release( $objectId, $userId )
    {
        $row = eZSiteData::fetchByName( self::KEY_PREFIX . (int)$objectId );
        if ( !$row instanceof eZSiteData )
            return false;
        $data = json_decode( (string)$row->attribute( 'value' ), true );
        if ( is_array( $data ) && isset( $data['user'] ) && (int)$data['user'] !== (int)$userId )
            return false;
        $row->remove();
        return true;
    }
}
