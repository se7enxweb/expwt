<?php
/**
 * "Verify it's you" for the sidebar (decision 35): an action of expwt/api asks exactly when the administration's view
 * it stands for asks (expwtAccess::kernelView(): publishing is content/edit with PublishButton, moving to the trash
 * content/removeobject with ConfirmButton ...), matched against the same trigger groups of site.ini
 * [ReauthSettings] Groups[]. Registered in TriggerHandlers[].
 *
 * The browser's request is refused with 403 {"error":"reauth_required"} before the action runs; the sidebar opens
 * expwt/reauth/<action> in a small window, where the kernel's page asks, and sends the action again after the answer.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

class expwtReauthTriggerHandler implements expReauthTriggerHandler
{
    public function reauthGroup( $module, $view, $method, array $post, array $params, eZUser $user )
    {
        if ( $module !== 'expwt' || !in_array( $view, array( 'api', 'reauth' ), true ) )
            return null;
        $action = $view === 'reauth' ? ( isset( $params[0] ) ? (string)$params[0] : '' ) : ( isset( $post['action'] ) ? (string)$post['action'] : '' );
        if ( $view === 'api' && strtoupper( (string)$method ) !== 'POST' )
            return null;
        return self::groupOf( $action, expReauth::settings() );
    }

    /**
     * @param string $action
     * @param array $settings expReauth::settings()
     * @return string|null the trigger group the action's administration view is in
     */
    public static function groupOf( $action, array $settings )
    {
        $kernel = expwtAccess::kernelView( $action );
        // an action an extension registered (decision 32): the administration view its registration names
        if ( $kernel === null && expwtRegistry::isActionName( $action ) )
        {
            $registry = expwtRegistry::fromIni();
            $kernel = isset( $registry['actions'][$action] ) ? $registry['actions'][$action]['kernelView'] : null;
        }
        if ( $kernel !== null )
        {
            $match = expReauth::matchGroup( $settings, $kernel['module'], $kernel['view'], 'POST', $kernel['post'] );
            if ( $match !== null )
                return (string)$match['group'];
        }
        // an action that asks in a group of its own (the real send of a newsletter, E3)
        return isset( expwtAccess::REAUTH_GROUPS[$action] ) ? expwtAccess::REAUTH_GROUPS[$action] : null;
    }
}
