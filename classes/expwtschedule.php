<?php
/**
 * Publishing later and taking a page down at a date (decisions 13 and F21) through the kernel's own mechanism
 * (expContentSchedule): the dates go into the draft's date fields, a "Wait until date" event of the content/publish
 * workflow holds the draft until its date (approvals apply) and the hide cronjob takes the page down. The same
 * fields the administration edits; the sidebar keeps no schedule of its own and has no cronjob.
 *
 * A class without the date fields the kernel reads cannot be scheduled: the sidebar shows why in place of the input
 * (view()), and refuses a date for it (expwtGateway::publish()).
 *
 * The schedule of earlier versions (site data rows expwt_publish_* and expwt_unpublish_*, the cronjob
 * expwt_scheduled) is moved to the kernel's mechanism by exp:wt:migrate-from-ezwt.
 *
 * Everything here is pure.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

class expwtSchedule
{
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

    /**
     * The dates of draft.publish as expContentSchedule::plan() takes them.
     *
     *   publishAt     absent or empty: publish now; else the time to publish at
     *   unpublishAt   absent: the take-down date stays as the draft has it; empty: cleared; else the time
     *
     * @param array $in
     * @return array at (int|null), until (int|null, 0: clear), error (null or bad_value)
     */
    public static function request( array $in )
    {
        $out = array( 'at' => null, 'until' => null, 'error' => null );
        if ( isset( $in['publishAt'] ) && trim( (string)$in['publishAt'] ) !== '' )
        {
            $out['at'] = self::parse( $in['publishAt'] );
            if ( $out['at'] === null )
                $out['error'] = 'bad_value';
        }
        if ( array_key_exists( 'unpublishAt', $in ) )
        {
            if ( $in['unpublishAt'] === null || trim( (string)$in['unpublishAt'] ) === '' )
                $out['until'] = 0;
            else
            {
                $out['until'] = self::parse( $in['unpublishAt'] );
                if ( $out['until'] === null )
                    $out['error'] = 'bad_value';
            }
        }
        return $out;
    }

    /**
     * What the Page panel shows: for each of publish and takeDown either the field (identifier, precision, value: the
     * date the draft carries, only when it is still to come) or null (the class has no such field, the panel says so).
     *
     * @param array $can expContentSchedule::forObject(): publish, takeDown
     * @param int $publishValue the publish date the version carries (0: none)
     * @param int $takeDownValue the take-down date the version carries (0: none)
     * @param int $now
     * @return array publish, takeDown
     */
    public static function view( array $can, $publishValue, $takeDownValue, $now )
    {
        $out = array();
        foreach ( array( 'publish' => (int)$publishValue, 'takeDown' => (int)$takeDownValue ) as $key => $value )
        {
            $field = isset( $can[$key] ) ? $can[$key] : null;
            $out[$key] = $field ? array( 'identifier' => (string)$field['identifier'], 'precision' => (string)$field['precision'],
                                         'value' => $value > $now ? $value : null ) : null;
        }
        return $out;
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
}
