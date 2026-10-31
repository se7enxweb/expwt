<?php
/**
 * Cronjob part expwt_scheduled: publishes the drafts scheduled in the website sidebar and takes down the pages whose
 * unpublish date has come (expwtSchedule::run()), each as the user who scheduled it.
 *
 * Registered in the frequent part (settings/cronjob.ini.append.php).
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

$done = expwtSchedule::run( time(), function ( $line ) use ( $cli ) {
    if ( isset( $cli ) && is_object( $cli ) )
        $cli->output( 'expwt: ' . $line );
} );
if ( isset( $cli ) && is_object( $cli ) )
    $cli->output( "expwt: $done scheduled change(s) carried out" );
