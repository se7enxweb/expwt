<?php
/**
 * The sidebar's actions, the expwt policy each needs, and the administration view each stands for.
 *
 * Two checks guard every action (decision F6): the expwt function of the action (expwt/use, expwt/edit, ...), which
 * only decides whether the sidebar offers it, and the kernel's own policy of what the action changes (content/edit of
 * the object, content/create below the parent, content/hide, content/remove ...), checked by the operation that does
 * the change (expwtGateway, through expGraphQLWriter and the kernel's operations). The sidebar never grants anything
 * the administration would refuse.
 *
 * The kernel view of each action (kernelView()) is what "Verify it's you" matches against the trigger groups of
 * site.ini [ReauthSettings] (expwtReauthTriggerHandler), so the sidebar asks exactly when the administration asks.
 *
 * Pure: plain arrays, tested without a database.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

class expwtAccess
{
    /**
     * The policy functions of the module expwt (modules/expwt/module.php), one per area. Those of later phases are
     * defined now, so roles can be prepared before the panels arrive.
     */
    const FUNCTIONS = array( 'use', 'edit', 'create', 'structure', 'layouts', 'templates', 'shop', 'admin', 'insights' );

    /**
     * action => array( expwt function, writes, kernel module, kernel view, kernel POST button )
     */
    const ACTIONS = array(
        'boot'            => array( 'use', false, '', '', '' ),
        'state.save'      => array( 'use', true, '', '', '' ),
        'me'              => array( 'use', false, '', '', '' ),
        'page.load'       => array( 'use', false, '', '', '' ),
        'draft.save'      => array( 'edit', true, 'content', 'edit', 'StoreButton' ),
        'draft.publish'   => array( 'edit', true, 'content', 'edit', 'PublishButton' ),
        'draft.discard'   => array( 'edit', true, 'content', 'edit', 'DiscardButton' ),
        'lock.acquire'    => array( 'edit', true, '', '', '' ),
        'lock.release'    => array( 'edit', true, '', '', '' ),
        'create.classes'  => array( 'create', false, '', '', '' ),
        'create.new'      => array( 'create', true, 'content', 'edit', 'StoreButton' ),
        'tree.children'   => array( 'structure', false, '', '', '' ),
        'tree.move'       => array( 'structure', true, 'content', 'action', 'MoveNodeButton' ),
        'tree.addlocation' => array( 'structure', true, 'content', 'action', 'AddAssignmentButton' ),
        'tree.copy'       => array( 'structure', true, 'content', 'copy', '' ),
        'tree.hide'       => array( 'structure', true, 'content', 'hide', '' ),
        'tree.reveal'     => array( 'structure', true, 'content', 'hide', '' ),
        'tree.trash'      => array( 'structure', true, 'content', 'removeobject', 'ConfirmButton' ),
        'tree.sort'       => array( 'structure', true, 'content', 'action', 'UpdatePriorityButton' ),
        // the approvals of the kernel's approve workflow (F29): the kernel's collaboration policy decides who has them
        'inbox.load'      => array( 'use', false, '', '', '' ),
        'inbox.decide'    => array( 'use', true, 'collaboration', 'action', 'CollaborationActionCustom' ),
        // the page's insights from the kernel's own statistics (F25)
        'insights.load'   => array( 'insights', false, '', '', '' ),
        // the SEO check of the page (F26): read-only, for those who edit with the sidebar
        'seo.check'       => array( 'edit', false, '', '', '' ),
    );

    /** @return bool */
    public static function exists( $action )
    {
        return is_string( $action ) && isset( self::ACTIONS[$action] );
    }

    /** @return string|null the expwt function the action needs */
    public static function functionFor( $action )
    {
        return self::exists( $action ) ? self::ACTIONS[$action][0] : null;
    }

    /** @return bool the action changes something (POST only, CSRF token, never cached) */
    public static function writes( $action )
    {
        return self::exists( $action ) && self::ACTIONS[$action][1];
    }

    /**
     * @param string $action
     * @return array|null module, view, post (the POST fields the administration's form sends for it)
     */
    public static function kernelView( $action )
    {
        if ( !self::exists( $action ) || self::ACTIONS[$action][2] === '' )
            return null;
        $a = self::ACTIONS[$action];
        return array( 'module' => $a[2], 'view' => $a[3], 'post' => $a[4] !== '' ? array( $a[4] => '1' ) : array() );
    }

    /**
     * What a user may do in the sidebar, as the boot answer tells the panels.
     *
     * @param callable $has function( string $function ): bool, whether the user has expwt/<function>
     * @return array function => bool
     */
    public static function capabilities( callable $has )
    {
        $out = array();
        foreach ( self::FUNCTIONS as $function )
            $out[$function] = (bool)call_user_func( $has, $function );
        // every other area lives inside the sidebar: without expwt/use there is no sidebar at all
        if ( !$out['use'] )
            $out = array_fill_keys( self::FUNCTIONS, false );
        return $out;
    }

    /**
     * Whether a user with these capabilities may call the action.
     *
     * @param string $action
     * @param array $capabilities of capabilities()
     * @return bool
     */
    public static function allows( $action, array $capabilities )
    {
        $function = self::functionFor( $action );
        return $function !== null && !empty( $capabilities['use'] ) && !empty( $capabilities[$function] );
    }
}
