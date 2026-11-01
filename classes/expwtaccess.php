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
        'inbox.read'      => array( 'use', true, '', '', '' ),
        'inbox.decide'    => array( 'use', true, 'collaboration', 'action', 'CollaborationActionCustom' ),
        // the page's insights from the kernel's own statistics (F25)
        'insights.load'   => array( 'insights', false, '', '', '' ),
        // the SEO check of the page (F26): read-only, for those who edit with the sidebar
        'seo.check'       => array( 'edit', false, '', '', '' ),
        // the page on the other sites and in its other languages (F27)
        'page.elsewhere'  => array( 'use', false, '', '', '' ),
        // Exponential Intelligence on a field of the user's draft (E1): asking costs and is logged (a write, by POST);
        // taking a suggestion into a field of its own editor writes the draft (content/edit, expai/write)
        'ai.field'        => array( 'edit', true, '', '', '' ),
        'ai.apply'        => array( 'edit', true, 'content', 'edit', 'StoreButton' ),
        // the page's forms and their submissions (E2): read-only; the submissions only with infocollector/read
        'forms.load'      => array( 'edit', false, '', '', '' ),
        'forms.view'      => array( 'edit', false, '', '', '' ),
        // the newsletter of the page (E3, cjw_newsletter): an edition made from the page (content/create below the
        // list, newsletter/editorial), a test send and the real send to the subscribers (newsletter/send); the real
        // send always asks "Verify it's you" first (REAUTH_GROUPS)
        'newsletter.load'   => array( 'edit', false, '', '', '' ),
        'newsletter.create' => array( 'edit', true, 'content', 'edit', 'PublishButton' ),
        'newsletter.test'   => array( 'edit', true, 'newsletter', 'send', 'SendNewsletterTestButton' ),
        'newsletter.send'   => array( 'edit', true, 'newsletter', 'send', 'SendNewsletterButton' ),
        // the page's comments, moderated in place (E4): the comments are content below the page, so the kernel's content
        // policies decide (approve and hide: content/hide of the comment; spam: content/remove, to the trash; a reply:
        // content/create of a comment below the page, published through the kernel's workflows)
        'comments.load'     => array( 'edit', false, '', '', '' ),
        'comments.approve'  => array( 'edit', true, 'content', 'hide', '' ),
        'comments.hide'     => array( 'edit', true, 'content', 'hide', '' ),
        'comments.spam'     => array( 'edit', true, 'content', 'removeobject', 'ConfirmButton' ),
        'comments.reply'    => array( 'edit', true, 'content', 'edit', 'PublishButton' ),
        // the page's addresses (E5): its addresses, old addresses, aliases, the wildcards that lead to it and the
        // addresses visitors did not find; an alias as the administration's URL aliases of a page add it
        // (content/edit of the page), a wildcard as its URL wildcards (content/urltranslator)
        'urls.load'         => array( 'edit', false, '', '', '' ),
        'urls.alias'        => array( 'edit', true, 'content', 'urlalias', 'NewAliasButton' ),
        'urls.wildcard'     => array( 'edit', true, 'content', 'urlwildcards', 'NewWildcardButton' ),
    );

    /**
     * Actions that ask "Verify it's you" in a group of their own, whatever the administration's view asks: the real
     * send of a newsletter (E3). action => the group of site.ini [ReauthSettings] Groups[] (settings/site.ini.append.php).
     */
    const REAUTH_GROUPS = array( 'newsletter.send' => 'newsletter' );

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
