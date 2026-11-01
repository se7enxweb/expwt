<?php
/**
 * Loads expwt's classes for its DB-less tests, run with the kernel's PHPUnit from the installation's root:
 *
 *   php vendor/bin/phpunit extension/expwt/tests/phpunit/unit
 *
 * A class the installation's autoload knows already (expwt active) is taken from there.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

$expwtClasses = array(
    'expwtSettings' => 'expwtsettings.php', 'expwtAccess' => 'expwtaccess.php', 'expwtState' => 'expwtstate.php',
    'expwtMerge' => 'expwtmerge.php', 'expwtLock' => 'expwtlock.php', 'expwtFields' => 'expwtfields.php',
    'expwtApiError' => 'expwtapierror.php', 'expwtApi' => 'expwtapi.php', 'expwtStrings' => 'expwtstrings.php',
    'expwtSchedule' => 'expwtschedule.php', 'expwtHook' => 'expwthook.php', 'expwtGateway' => 'expwtgateway.php',
    'expwtFieldMarkerHandler' => 'expwtfieldmarkerhandler.php',
    'expwtRegistry' => 'expwtregistry.php', 'expwtCalendar' => 'expwtcalendar.php',
    'expwtCommentModeration' => 'expwtcommentmoderation.php', 'expwtImages' => 'expwtimages.php',
    'expwtRolePanels' => 'expwtrolepanels.php',
);
foreach ( $expwtClasses as $expwtClass => $expwtFile )
{
    if ( !class_exists( $expwtClass ) )
        require_once dirname( __DIR__, 2 ) . '/classes/' . $expwtFile;
}
if ( !interface_exists( 'expwtActionHandler' ) )
    require_once dirname( __DIR__, 2 ) . '/classes/expwtactionhandler.php';
if ( interface_exists( 'expReauthTriggerHandler' ) && !class_exists( 'expwtReauthTriggerHandler' ) )
    require_once dirname( __DIR__, 2 ) . '/classes/expwtreauthtriggerhandler.php';
unset( $expwtClasses, $expwtClass, $expwtFile );
