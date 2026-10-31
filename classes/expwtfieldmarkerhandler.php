<?php
/**
 * Editing on the page (decision 11, F20): the kernel's markers around every rendered attribute (expContentFieldMarker,
 * <!--exp-field data-exp-field data-exp-object data-exp-version ...-->), only for a user who may edit with the
 * sidebar. Registered in site.ini [ContentFieldMarkerSettings] Handlers[].
 *
 * Yes when the sidebar is switched on for this siteaccess and the user is signed in with expwt/use and expwt/edit.
 * Never for a visitor who is not signed in: their pages, and the caches of them, stay as they are. What the answer
 * depends on (the siteaccess and the user's roles) is what the view cache and the role-aware page cache key on.
 *
 * decide() is pure.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

class expwtFieldMarkerHandler
{
    /** @return bool */
    public static function active()
    {
        try
        {
            $settings = expwtSettings::fromIni();
            // not on this siteaccess (an administration without ShowInAdmin=enabled, SiteAccessList[], SiteAccessExcludeList[]): off
            $settings['enabled'] = expwtSettings::onHere( $settings );
            $user = eZUser::currentUser();
            if ( !$settings['enabled'] || !$user->isRegistered() )
                return false;
            $use = $user->hasAccessTo( 'expwt', 'use' );
            $edit = $user->hasAccessTo( 'expwt', 'edit' );
            return self::decide( $settings['enabled'], true, isset( $use['accessWord'] ) ? $use['accessWord'] : 'no',
                                 isset( $edit['accessWord'] ) ? $edit['accessWord'] : 'no' );
        }
        catch ( Exception $e )
        {
            eZDebug::writeError( $e->getMessage(), __METHOD__ );
            return false;
        }
    }

    /**
     * @param bool $enabled expwt.ini Enabled
     * @param bool $registered the user is signed in
     * @param string $use the access word of expwt/use (yes, limited, no)
     * @param string $edit the access word of expwt/edit
     * @return bool
     */
    public static function decide( $enabled, $registered, $use, $edit )
    {
        return $enabled && $registered && $use !== 'no' && $edit !== 'no';
    }
}
