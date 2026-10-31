<?php
/**
 * Publishing later and taking a page down at a date (decision 13): an entry in the site data table per draft to
 * publish (expwt_publish_<object>_<version>) and per page to hide (expwt_unpublish_<node>), carried out by the
 * cronjob part expwt_scheduled (cronjobs/expwt_scheduled.php, every few minutes in the frequent part) as the user
 * who scheduled it, through the same kernel operations as "Publish now" (workflows included) and content/hide.
 *
 * parse(), due() and priorities() are pure.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

class expwtSchedule
{
    const PUBLISH_PREFIX = 'expwt_publish_';
    const UNPUBLISH_PREFIX = 'expwt_unpublish_';

    /**
     * @param string $value an ISO 8601 date and time (2026-11-02T09:30, with or without seconds and zone) or a Unix time
     * @return int|null
     */
    public static function parse( $value )
    {
        $value = trim( (string)$value );
        if ( $value === '' )
            return null;
        if ( ctype_digit( $value ) )
            return (int)$value;
        if ( !preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?(Z|[+-]\d{2}:?\d{2})?$/', $value ) )
            return null;
        $time = strtotime( $value );
        return $time === false ? null : $time;
    }

    /** @return array name, data */
    public static function publishEntry( $objectId, $version, $userId, $at, $until = null )
    {
        return array( 'name' => self::PUBLISH_PREFIX . (int)$objectId . '_' . (int)$version,
                      'data' => array( 'type' => 'publish', 'object' => (int)$objectId, 'version' => (int)$version, 'user' => (int)$userId,
                                       'at' => (int)$at, 'until' => $until !== null ? (int)$until : null ) );
    }

    /** @return array name, data */
    public static function unpublishEntry( $nodeId, $userId, $at )
    {
        return array( 'name' => self::UNPUBLISH_PREFIX . (int)$nodeId,
                      'data' => array( 'type' => 'unpublish', 'node' => (int)$nodeId, 'user' => (int)$userId, 'at' => (int)$at ) );
    }

    /**
     * @param array $entries name => data
     * @param int $now
     * @return array the entries due, the earliest first
     */
    public static function due( array $entries, $now )
    {
        $due = array();
        foreach ( $entries as $name => $data )
            if ( is_array( $data ) && isset( $data['at'] ) && (int)$data['at'] <= $now )
                $due[$name] = $data;
        uasort( $due, function ( $a, $b ) { return (int)$a['at'] - (int)$b['at']; } );
        return $due;
    }

    /**
     * @param int[] $order the node ids in their new order
     * @param int[] $children the node ids of the children there are
     * @return array node id => priority (10, 20, 30 ...); empty when the order names a node that is not a child
     *               or names one twice
     */
    public static function priorities( array $order, array $children )
    {
        if ( !$order || count( array_unique( $order ) ) !== count( $order ) || array_diff( $order, $children ) )
            return array();
        $out = array();
        foreach ( array_values( $order ) as $i => $nodeId )
            $out[(int)$nodeId] = ( $i + 1 ) * 10;
        return $out;
    }

    public static function store( array $entry )
    {
        $row = eZSiteData::fetchByName( $entry['name'] );
        if ( !$row instanceof eZSiteData )
            $row = eZSiteData::create( $entry['name'], '' );
        $row->setAttribute( 'value', json_encode( $entry['data'] ) );
        $row->store();
    }

    /** @return array name => data, every entry of the sidebar's schedule */
    public static function entries()
    {
        $out = array();
        foreach ( array( self::PUBLISH_PREFIX, self::UNPUBLISH_PREFIX ) as $prefix )
        {
            foreach ( (array)eZSiteData::fetchList( $prefix . '*', 0, 1000 ) as $row )
            {
                $data = json_decode( (string)$row->attribute( 'value' ), true );
                if ( is_array( $data ) )
                    $out[(string)$row->attribute( 'name' )] = $data;
            }
        }
        return $out;
    }

    /**
     * Carries out what is due (the cronjob). Each entry runs as the user who scheduled it, so their rights apply at
     * the time it runs; an entry that fails is logged and removed, never retried for ever.
     *
     * @param int $now
     * @param callable|null $say function( string ) for the cronjob's output
     * @return int entries carried out
     */
    public static function run( $now, $say = null )
    {
        $done = 0;
        $before = eZUser::currentUserID();
        foreach ( self::due( self::entries(), $now ) as $name => $data )
        {
            $user = eZUser::fetch( (int)$data['user'] );
            $row = eZSiteData::fetchByName( $name );
            if ( $row instanceof eZSiteData )
                $row->remove();
            if ( !$user instanceof eZUser )
                continue;
            eZUser::setCurrentlyLoggedInUser( $user, (int)$data['user'], eZUser::NO_SESSION_REGENERATE );
            try
            {
                if ( $data['type'] === 'publish' )
                {
                    $r = expwtWriter::forCurrentUser()->publishDraft( (int)$data['object'], (int)$data['version'] );
                    if ( !empty( $data['until'] ) && !empty( $r['nodeId'] ) )
                        self::store( self::unpublishEntry( (int)$r['nodeId'], (int)$data['user'], (int)$data['until'] ) );
                    $line = "published object {$data['object']} version {$data['version']}: {$r['status']}";
                }
                else
                {
                    $r = expwtWriter::forCurrentUser()->visibility( (int)$data['node'], true );
                    $line = "hid node {$data['node']}: {$r['status']}";
                }
                $done++;
            }
            catch ( Exception $e )
            {
                $line = "FAILED $name: " . $e->getMessage();
                eZDebug::writeError( $line, __METHOD__ );
            }
            if ( $say )
                call_user_func( $say, $line );
        }
        $back = eZUser::fetch( $before );
        if ( $back instanceof eZUser )
            eZUser::setCurrentlyLoggedInUser( $back, $before, eZUser::NO_SESSION_REGENERATE );
        return $done;
    }
}
