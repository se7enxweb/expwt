<?php
/**
 * The Calendar panel (E6): everything scheduled for the page, its subtree or the whole site, on the kernel's own
 * mechanisms only (decision F21), and the layout variants of explayouts (decision F4):
 *
 *   publish    a draft held by the "Wait until date" event of the content/publish workflow (a pending version whose
 *              workflow process waits in that event); its date is the draft's date field the event reads
 *              (expContentSchedule)
 *   takedown   a published page whose date field content.ini [HideSettings] HideDateAttributeList names, below a node
 *              of RootNodeList[]: the hide cronjob hides it once the date has come
 *   variant    an explayouts rule with a time condition (from, to): its layout is the page's while the time is in range
 *
 * Moving an item ("drag to reschedule") changes exactly what the administration would: the pending draft's date field
 * (and when its workflow process wakes up), a new version of the page with the new take-down date published through
 * content/publish (approvals apply), the rule's time condition.
 *
 * Everything here is pure: plain arrays and timestamps, tested without a database.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

class expwtCalendar
{
    const SCOPES = array( 'page', 'subtree', 'site' );

    /** The longest stretch one load covers: a month grid of six weeks, and a little more */
    const MAX_DAYS = 45;

    /** The most items of each kind one load lists */
    const MAX_ITEMS = 200;

    /** @return string page, subtree or site; page for anything else */
    public static function scope( $scope )
    {
        return in_array( $scope, self::SCOPES, true ) ? $scope : 'page';
    }

    /**
     * The stretch of time to list: from and to as given (the first and last moment of the month grid the browser
     * shows), at most MAX_DAYS long; without them the 42 days from the start of today.
     *
     * @param int|null $from
     * @param int|null $to
     * @param int $now
     * @return array from, to
     */
    public static function window( $from, $to, $now )
    {
        $from = (int)$from;
        $to = (int)$to;
        if ( $from <= 0 )
            $from = (int)mktime( 0, 0, 0, (int)date( 'n', $now ), (int)date( 'j', $now ), (int)date( 'Y', $now ) );
        if ( $to <= $from )
            $to = $from + 42 * 86400 - 1;
        if ( $to - $from > self::MAX_DAYS * 86400 )
            $to = $from + self::MAX_DAYS * 86400;
        return array( 'from' => $from, 'to' => $to );
    }

    /**
     * Whether something placed at these nodes belongs to the scope around the page.
     *
     * @param string $scope page, subtree, site
     * @param int $nodeId the page
     * @param string[] $paths the path strings of the item's nodes (/1/2/59/128/)
     * @return bool
     */
    public static function inScope( $scope, $nodeId, array $paths )
    {
        if ( $scope === 'site' )
            return true;
        $needle = '/' . (int)$nodeId . '/';
        foreach ( $paths as $path )
        {
            $path = (string)$path;
            if ( $scope === 'page' && substr( $path, -strlen( $needle ) ) === $needle )
                return true;
            if ( $scope === 'subtree' && strpos( $path, $needle ) !== false )
                return true;
        }
        return false;
    }

    /**
     * Whether a layout rule's target concerns the scope: a node target names the page (or, for the subtree, a page
     * in it); a subtree target covers the page or lies in its subtree. Other kinds of target (paths, routes) are
     * decided by explayouts' resolver for the page's own address (null here).
     *
     * @param string $scope
     * @param int $nodeId the page
     * @param string $pagePath the page's path string
     * @param string $type the target's type
     * @param string $targetPath the path string of the target's node ('' when it has none)
     * @return bool|null
     */
    public static function targetInScope( $scope, $nodeId, $pagePath, $type, $targetPath )
    {
        if ( $scope === 'site' )
            return true;
        if ( $type !== 'node' && $type !== 'subtree' )
            return null;
        if ( (string)$targetPath === '' )
            return false;
        $parts = array_values( array_filter( explode( '/', trim( (string)$targetPath, '/' ) ), 'strlen' ) );
        $target = (int)end( $parts );
        // a subtree target whose root is the page or above it: the page gets the layout
        if ( $type === 'subtree' && strpos( (string)$pagePath, '/' . $target . '/' ) !== false )
            return true;
        if ( $scope === 'page' )
            return $target === (int)$nodeId;
        return strpos( (string)$targetPath, '/' . (int)$nodeId . '/' ) !== false;
    }

    /**
     * A time condition's value as explayouts' resolver reads it: JSON with from and to, each a date as text
     * (strtotime) or as parts (year, month, day, hour, minute, second).
     *
     * @param string $value
     * @return array|null from, to (timestamps or null), form (text, parts); null when the resolver cannot read it
     */
    public static function timeRange( $value )
    {
        $range = json_decode( (string)$value, true );
        if ( !is_array( $range ) )
            return null;
        $out = array( 'from' => null, 'to' => null, 'form' => 'text' );
        foreach ( array( 'from', 'to' ) as $end )
        {
            $v = isset( $range[$end] ) ? $range[$end] : null;
            if ( empty( $v ) )
                continue;
            if ( is_array( $v ) )
            {
                $out['form'] = 'parts';
                if ( empty( $v['year'] ) || empty( $v['month'] ) || empty( $v['day'] ) )
                    return null;
                $out[$end] = (int)mktime( isset( $v['hour'] ) ? (int)$v['hour'] : 0, isset( $v['minute'] ) ? (int)$v['minute'] : 0,
                                          isset( $v['second'] ) ? (int)$v['second'] : 0, (int)$v['month'], (int)$v['day'], (int)$v['year'] );
                continue;
            }
            $ts = strtotime( (string)$v );
            if ( $ts === false )
                return null;
            $out[$end] = $ts;
        }
        if ( $out['from'] === null && $out['to'] === null )
            return null;
        return $out;
    }

    /**
     * A time condition moved: its start to $at (the end moves with it, the length stays) or only its end to $at. The
     * value keeps the form it had and every other key of it.
     *
     * @param string $value the condition's value
     * @param string $which start or end
     * @param int $at
     * @return array value (the new JSON), from, to; or error (unreadable, no_end, end_before_start)
     */
    public static function moveRange( $value, $which, $at )
    {
        $range = self::timeRange( $value );
        if ( $range === null )
            return array( 'error' => 'unreadable' );
        $from = $range['from'];
        $to = $range['to'];
        if ( $which === 'end' )
        {
            if ( $to === null )
                return array( 'error' => 'no_end' );
            $to = (int)$at;
        }
        else if ( $from === null )
        {
            // a range with only an end: its "start" is its end
            $to = (int)$at;
        }
        else
        {
            if ( $to !== null )
                $to += (int)$at - $from;
            $from = (int)$at;
        }
        if ( $from !== null && $to !== null && $to <= $from )
            return array( 'error' => 'end_before_start' );
        $data = (array)json_decode( (string)$value, true );
        if ( $from !== null )
            $data['from'] = self::timeValue( $from, $range['form'] );
        if ( $to !== null )
            $data['to'] = self::timeValue( $to, $range['form'] );
        return array( 'value' => json_encode( $data ), 'from' => $from, 'to' => $to, 'error' => null );
    }

    /** @return string|array a time in the form a condition keeps it */
    protected static function timeValue( $ts, $form )
    {
        if ( $form === 'parts' )
            return array( 'year' => (int)date( 'Y', $ts ), 'month' => (int)date( 'n', $ts ), 'day' => (int)date( 'j', $ts ),
                          'hour' => (int)date( 'G', $ts ), 'minute' => (int)date( 'i', $ts ), 'second' => (int)date( 's', $ts ) );
        return date( 'Y-m-d H:i:s', $ts );
    }

    /**
     * Whether a range touches the window (one of its ends lies in it, or it spans it).
     *
     * @return bool
     */
    public static function touches( $from, $to, array $window )
    {
        foreach ( array( $from, $to ) as $ts )
            if ( $ts !== null && $ts >= $window['from'] && $ts <= $window['to'] )
                return true;
        return false;
    }

    /**
     * The items in the order the panel lists them: by time, then by kind (publish, variant, take-down), then title.
     *
     * @param array $items each: at, kind, title
     * @return array
     */
    public static function sorted( array $items )
    {
        $rank = array( 'publish' => 0, 'variant' => 1, 'variant-end' => 2, 'takedown' => 3 );
        usort( $items, function ( $a, $b ) use ( $rank ) {
            if ( (int)$a['at'] !== (int)$b['at'] )
                return (int)$a['at'] < (int)$b['at'] ? -1 : 1;
            $ka = isset( $rank[$a['kind']] ) ? $rank[$a['kind']] : 9;
            $kb = isset( $rank[$b['kind']] ) ? $rank[$b['kind']] : 9;
            return $ka - $kb ?: strcmp( (string)$a['title'], (string)$b['title'] );
        } );
        return $items;
    }

    /**
     * When the moved item happens: a date in the future. Pure check of the input of a move.
     *
     * @param int|null $at expwtSchedule::parse() of the input
     * @param int $now
     * @return string|null bad_value (no date), past (not in the future); null when fine
     */
    public static function moveError( $at, $now )
    {
        if ( $at === null )
            return 'bad_value';
        return $at <= $now ? 'past' : null;
    }
}
