<?php
/**
 * The Static panel of the website sidebar: the page in the kernel's static publishing (Exponential 6.0.15,
 * kernel/classes/staticpublish, Setup > Static publishing). For every target the user may read (staticpublish/read
 * with its Target limitation): the last publish, whether this page is stale there (content published after the page
 * was built), and the page's public address there. "Publish" starts the kernel's publish job for this page only: an
 * incremental build of the page's node into the last build, then the publish (commit, tag, push). It needs
 * staticpublish/publish for the target and asks "Verify it's you" as the administration's Publish does
 * (site.ini [ReauthGroup_system] staticpublish/preview PublishButton, expwtAccess::kernelView()).
 *
 * Every text is the kernel's own (design/admin/staticpublish), so the panel adds nothing to translate. On a kernel
 * without static publishing there is no panel: every call is behind class_exists, method_exists and defined; on a
 * kernel whose publish job cannot build single nodes yet (expStaticJob::NODES) the panel is read-only.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

class expwtStatic
{
    /** The kernel's translation context of Setup > Static publishing */
    const CONTEXT = 'design/admin/staticpublish';

    /** The help page the panel links when there is no target yet (the kernel's dashboard block links it too) */
    const TUTORIAL = 'exphelp/viewer/guides/host-static-sites-on-github-pages.md';

    /** The kernel's texts the panel shows: key => source text of CONTEXT */
    const LABELS = array(
        'title' => 'Static publishing', 'target' => 'Target', 'lastPublish' => 'Last publish', 'never' => 'never',
        'stale' => 'Stale pages', 'yes' => 'Yes', 'no' => 'No', 'missing' => 'Not in the build.',
        'address' => 'Public address', 'publish' => 'Publish', 'history' => 'History', 'job' => 'Job %id',
        'going' => 'A job is going on, started by %user', 'background' => 'It runs in the background as %user; you can close this page.',
        'off' => 'Static publishing is switched off', 'noTarget' => 'No target yet.', 'tutorial' => 'Read the tutorial',
        'make' => 'Make a target', 'failed' => 'failed', 'unchanged' => 'nothing changed', 'pushed' => 'pushed', 'notPushed' => 'not pushed',
    );

    /**
     * Whether the kernel has static publishing as the panel reads it.
     *
     * @return bool
     */
    public static function kernel()
    {
        return class_exists( 'expStaticTarget' ) && class_exists( 'expStaticAdmin' ) && class_exists( 'expStaticStatus' )
            && class_exists( 'expStaticJob' ) && method_exists( 'expStaticAdmin', 'canDo' ) && method_exists( 'expStaticAdmin', 'readableTargets' )
            && method_exists( 'expStaticStatus', 'pages' ) && method_exists( 'expStaticStatus', 'runs' );
    }

    /** @return bool the kernel's publish job builds single nodes first (expStaticJob::NODES) */
    public static function publishesNodes()
    {
        return self::kernel() && defined( 'expStaticJob::NODES' ) && method_exists( 'expStaticJob', 'start' );
    }

    /**
     * The panel in the boot answer: null on a kernel without static publishing or for a user without staticpublish/read
     * (for any target); else its tab's label, in the kernel's words.
     *
     * @return array|null label
     */
    public static function offer()
    {
        if ( !self::kernel() || !expStaticAdmin::canDo( 'read' ) )
            return null;
        return array( 'label' => ezpI18n::tr( self::CONTEXT, self::LABELS['title'] ) );
    }

    /** @return array the kernel's texts, translated (LABELS) */
    public static function labels()
    {
        $out = array();
        foreach ( self::LABELS as $key => $source )
            $out[$key] = ezpI18n::tr( self::CONTEXT, $source );
        return $out;
    }

    /**
     * Whether a node's page is stale on a target, from the target's page rows (expStaticStatus::pages()). Pure.
     *
     * @param array[] $rows path => row (node_id, stale)
     * @param int $nodeId
     * @param string[] $folders the folders of the target's siteaccesses in the copy (bold, bold_ger; '' at the root)
     * @param string|null $folder the folder of the siteaccess the page is viewed in: only its pages count (a whole
     *                            path segment: bold_ger/ is not in bold/); null for every page of the node
     * @return array state (stale, current, missing: not in the last publish), path (the page's file, '' when missing)
     */
    public static function pageState( array $rows, $nodeId, array $folders = array(), $folder = null )
    {
        $path = '';
        $stale = false;
        $queue = class_exists( 'expStaticPublishQueue' ) && defined( 'expStaticPublishQueue::QUEUE_PREFIX' ) ? expStaticPublishQueue::QUEUE_PREFIX : '.queue/';
        foreach ( $rows as $key => $row )
        {
            if ( !isset( $row['node_id'] ) || (int)$row['node_id'] !== (int)$nodeId )
                continue;
            $rowPath = isset( $row['path'] ) ? (string)$row['path'] : (string)$key;
            // a node changed before any page of it was published: queued, not a page yet
            if ( strpos( $rowPath, $queue ) === 0 )
                continue;
            if ( $folder !== null && self::folderOf( $rowPath, $folders ) !== trim( (string)$folder, '/' ) )
                continue;
            if ( $path === '' || strlen( $rowPath ) < strlen( $path ) )
                $path = $rowPath;
            if ( !empty( $row['stale'] ) )
                $stale = true;
        }
        return array( 'state' => $path === '' ? 'missing' : ( $stale ? 'stale' : 'current' ), 'path' => $path );
    }

    /**
     * The folder of the siteaccess a file of the copy belongs to: the longest of the folders that is a whole leading
     * path segment of it (bold_ger/team/index.html: bold_ger, not bold), '' at the root. Pure.
     *
     * @param string $path
     * @param string[] $folders
     * @return string
     */
    public static function folderOf( $path, array $folders )
    {
        $path = trim( str_replace( '\\', '/', (string)$path ), '/' );
        $best = '';
        foreach ( $folders as $candidate )
        {
            $candidate = trim( (string)$candidate, '/' );
            if ( $candidate !== '' && ( $path === $candidate || strpos( $path, $candidate . '/' ) === 0 ) && strlen( $candidate ) > strlen( $best ) )
                $best = $candidate;
        }
        return $best;
    }

    /**
     * Where the siteaccess the panel is viewed in is in a target's copy: the folders of all its siteaccesses and its
     * own (null when the target does not publish it, or the kernel cannot say: then every page of the node counts).
     *
     * @param expStaticTarget $target
     * @param string $siteaccess
     * @return array folders, folder
     */
    protected static function siteAccessFolder( $target, $siteaccess )
    {
        $out = array( 'folders' => array(), 'folder' => null );
        if ( !method_exists( $target, 'members' ) || !method_exists( 'expStaticTarget', 'siteAccessContext' ) )
            return $out;
        try
        {
            $members = $target->members( expStaticTarget::siteAccessContext() );
        }
        catch ( Throwable $e )
        {
            return $out;
        }
        if ( count( $members ) < 2 )
            return $out;
        foreach ( $members as $member )
        {
            $out['folders'][] = (string)$member['prefix'];
            if ( (string)$member['siteaccess'] === (string)$siteaccess )
                $out['folder'] = (string)$member['prefix'];
        }
        return $out;
    }

    /**
     * A page's public address on a target. Pure.
     *
     * @param string $publicUrl the target's public address ('' when unknown)
     * @param string $path the page's file in the build (news/index.html)
     * @return string '' when either is unknown
     */
    public static function pageUrl( $publicUrl, $path )
    {
        $publicUrl = trim( (string)$publicUrl );
        if ( $path === '' || !preg_match( '#^https?://[^/\s]+#i', $publicUrl ) )
            return '';
        $path = ltrim( str_replace( '\\', '/', (string)$path ), '/' );
        if ( preg_match( '#(^|/)index\.html$#', $path ) )
            $path = substr( $path, 0, -strlen( 'index.html' ) );
        return rtrim( $publicUrl, '/' ) . '/' . implode( '/', array_map( 'rawurlencode', explode( '/', $path ) ) );
    }

    /**
     * The last publish of a target as the panel shows it. Pure.
     *
     * @param array|null $run a row of exp_static_run (expStaticStatus::runs())
     * @return array|null at (unix time), state (failed, unchanged, pushed, not-pushed), tag
     */
    public static function runView( $run )
    {
        if ( !is_array( $run ) || empty( $run['run_id'] ) )
            return null;
        $state = isset( $run['state'] ) ? (string)$run['state'] : '';
        if ( $state === 'failed' )
            $view = 'failed';
        else if ( $state === 'unchanged' )
            $view = 'unchanged';
        else
            $view = isset( $run['push_state'] ) && $run['push_state'] === 'pushed' ? 'pushed' : 'not-pushed';
        return array( 'at' => (int)( !empty( $run['finished_at'] ) ? $run['finished_at'] : ( isset( $run['started_at'] ) ? $run['started_at'] : 0 ) ),
                      'state' => $view, 'tag' => isset( $run['tag_name'] ) ? (string)$run['tag_name'] : '' );
    }

    /**
     * The panel: the targets the user may read, each with the page's state there.
     *
     * @param eZContentObjectTreeNode $node the page
     * @param string|null $admin the administration's base address (expwtGateway::adminUrl()), for the links there
     * @return array enabled, targets (id, name, last, page (state, url), job (id, user, url), history, publish), labels,
     *               setup (links when there is no target: make, tutorial), publishes (the kernel can publish a page)
     * @throws expwtApiError 403 without the kernel's static publishing or staticpublish/read
     */
    public static function load( eZContentObjectTreeNode $node, $admin )
    {
        if ( self::offer() === null )
            throw new expwtApiError( 403, 'forbidden', 'You may not do this.' );
        $admin = $admin !== null ? rtrim( (string)$admin, '/' ) . '/' : null;
        $db = null;
        try
        {
            $db = eZDB::instance();
        }
        catch ( Throwable $e )
        {
        }
        $nodeId = (int)$node->attribute( 'node_id' );
        $enabled = (bool)expStaticTarget::enabled();
        $nodes = self::publishesNodes();
        $siteaccess = isset( $GLOBALS['eZCurrentAccess']['name'] ) ? (string)$GLOBALS['eZCurrentAccess']['name'] : '';
        $targets = array();
        foreach ( expStaticAdmin::readableTargets() as $target )
        {
            $id = $target->id();
            $runs = expStaticStatus::runs( $db, $id, 1 );
            // a target of several siteaccesses: the page in the siteaccess the panel is viewed in (bold_ger, not bold)
            $where = self::siteAccessFolder( $target, $siteaccess );
            $page = self::pageState( expStaticStatus::pages( $db, $id ), $nodeId, $where['folders'], $where['folder'] );
            $job = null;
            try
            {
                $running = expStaticJob::running( expStaticAdmin::publisher( $target )->workDirectory(), $id );
                if ( $running )
                    $job = array( 'id' => (string)$running['id'], 'user' => isset( $running['user'] ) ? (string)$running['user'] : '',
                                  'url' => $admin !== null ? $admin . 'staticpublish/log/' . $running['id'] : null );
            }
            catch ( Throwable $e )
            {
                eZDebug::writeError( get_class( $e ) . ': ' . $e->getMessage(), __METHOD__ );
            }
            $targets[] = array(
                'id' => $id,
                'name' => $target->name() !== '' ? $target->name() : $id,
                'last' => self::runView( $runs ? $runs[0] : null ),
                'page' => array( 'state' => $page['state'], 'url' => self::pageUrl( $target->publicUrl(), $page['path'] ) ),
                'job' => $job,
                'history' => $admin !== null ? $admin . 'staticpublish/history/' . $id : null,
                'publish' => $enabled && $nodes && expStaticAdmin::canDo( 'publish', $id ),
            );
        }
        $setup = null;
        if ( !$targets && method_exists( 'expStaticAdmin', 'canDoAll' ) && expStaticAdmin::canDoAll( 'setup' ) )
            $setup = array( 'make' => $admin !== null ? $admin . 'staticpublish/target/new' : null,
                            'tutorial' => $admin !== null ? $admin . self::TUTORIAL : null );
        return array( 'nodeId' => $nodeId, 'enabled' => $enabled, 'targets' => $targets, 'setup' => $setup, 'publishes' => $nodes,
                      'labels' => self::labels() );
    }

    /**
     * "Publish": the kernel's publish job for this page only (an incremental build of its node, then the publish), as
     * the signed-in user. The policy is checked here; "Verify it's you" before the action runs (expwtAccess).
     *
     * @param eZContentObjectTreeNode $node
     * @param string $targetId
     * @param string $login the signed-in user's login
     * @param string|null $admin the administration's base address
     * @return array id (the job), url (its page in the administration), message (the kernel's words)
     * @throws expwtApiError 403 without the policy or the kernel's support, 404 for a target that is not there, 409 when
     *                       static publishing is switched off or the job could not start
     */
    public static function publish( eZContentObjectTreeNode $node, $targetId, $login, $admin )
    {
        if ( !self::publishesNodes() || self::offer() === null )
            throw new expwtApiError( 403, 'forbidden', 'You may not do this.' );
        $target = is_string( $targetId ) && $targetId !== '' ? expStaticTarget::get( $targetId ) : null;
        if ( !$target instanceof expStaticTarget || !expStaticAdmin::canDo( 'read', $target->id() ) )
            throw new expwtApiError( 404, 'not_found', 'This page is not there, or you may not read it.' );
        if ( !expStaticAdmin::canDo( 'publish', $target->id() ) )
            throw new expwtApiError( 403, 'forbidden', 'You may not do this.' );
        if ( !expStaticTarget::enabled() )
            throw new expwtApiError( 409, 'static_off', ezpI18n::tr( self::CONTEXT, self::LABELS['off'] ) );
        $started = expStaticJob::start( 'publish', $target->id(), array( expStaticJob::NODES => array( (int)$node->attribute( 'node_id' ) ) ),
                                        (string)$login, array( 'work_dir' => expStaticAdmin::publisher( $target )->workDirectory() ) );
        if ( empty( $started['ok'] ) )
        {
            $page = 'Exponential\\View\\Kernel\\Staticpublish\\Page';
            $message = class_exists( $page ) && method_exists( $page, 'refusal' ) ? call_user_func( array( $page, 'refusal' ), $started )
                     : ezpI18n::tr( self::CONTEXT, 'The job could not be started.' );
            throw new expwtApiError( 409, 'static_refused', $message );
        }
        $admin = $admin !== null ? rtrim( (string)$admin, '/' ) . '/' : null;
        return array( 'id' => (string)$started['id'], 'target' => $target->id(),
                      'url' => $admin !== null ? $admin . 'staticpublish/log/' . $started['id'] : null,
                      'message' => ezpI18n::tr( self::CONTEXT, self::LABELS['background'], null, array( '%user' => (string)$login ) ) );
    }
}
