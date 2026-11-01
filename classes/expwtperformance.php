<?php
/**
 * The Performance panel's figures (E12): how this page was rendered, for a user with expwt/performance.
 *
 * The figures are the kernel's debug bar data of the render itself, not of the sidebar's own requests: the page time,
 * the SQL statements and their time, peak memory, the content view cache (hit, generated, off ...), the cache-blocks
 * (hits and misses, the slowest), the templates compiled now, and the time of every layout block (the template tree:
 * each design:explayouts/block/... template with the block template it used). They are taken at the end of the
 * render (response/preoutput) and travel in the page itself, as data-render on the sidebar's element, only for a
 * user with expwt/performance: no storage, no extra request, and not one byte for anybody else.
 *
 * The kernel collects them with debug output on for the user (site.ini [DebugSettings] DebugByIP, DebugByUser) and,
 * from Exponential 6.0.15 on, also without it for a user of [DebugSettings] CollectStatsPolicyList[] (expwt ships
 * expwt/performance there; expRenderStats::checkCollectByPolicy()). Without either the panel has the page time and
 * memory only, and says why.
 *
 * A page that comes from a cache (the role-aware page cache, a proxy, the browser's history) was not rendered for this
 * view: its figures are of the render that made it. The panel compares the render's time with the page's load and says so.
 *
 * view(), attach() and blocks() are pure.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

class expwtPerformance
{
    /** The layout blocks listed at most (a long list page) */
    const MAX_BLOCKS = 60;

    /** The slowest cache-blocks listed */
    const MAX_CACHE_BLOCKS = 8;

    /** The slowest templates listed */
    const MAX_TEMPLATES = 8;

    /** A template of a layout block: design:explayouts/block/<name>.tpl (also the .stpl and .twig ones) */
    const BLOCK_TEMPLATE = '#(^|[:/])explayouts/block/([A-Za-z0-9_]+)\.(tpl|stpl|twig)$#';

    /** The wrapper of a template block, which includes the block's own template */
    const TPL_BLOCK_WRAPPER = '#(^|[:/])explayouts/block/tpl_block\.(tpl|stpl|twig)$#';

    /**
     * response/preoutput, after the sidebar is in the page: its element gets data-render for a user with
     * expwt/performance. Never throws.
     *
     * @param string $html
     * @return string
     */
    public static function preOutput( $html )
    {
        try
        {
            if ( !is_string( $html ) || strpos( $html, '<' . expwtHook::TAG . ' ' ) === false || strpos( $html, '<' . expwtHook::TAG . ' data-render=' ) !== false )
                return $html;
            if ( !self::mayView() )
                return $html;
            return self::attach( $html, self::view( self::collect() ) );
        }
        catch ( Throwable $e )
        {
            eZDebug::writeError( get_class( $e ) . ': ' . $e->getMessage(), __METHOD__ );
            return $html;
        }
    }

    /** @return bool the current user is signed in and has expwt/use and expwt/performance */
    public static function mayView()
    {
        $user = eZUser::currentUser();
        if ( !$user->isRegistered() )
            return false;
        foreach ( array( 'use', 'performance' ) as $function )
        {
            $access = $user->hasAccessTo( 'expwt', $function );
            if ( !isset( $access['accessWord'] ) || $access['accessWord'] === 'no' )
                return false;
        }
        return true;
    }

    /**
     * How the kernel collects the figures for this user now.
     *
     * @return string debug (debug output on), policy (collected without output: CollectStatsPolicyList[]), none
     */
    public static function source()
    {
        if ( class_exists( 'eZDebug' ) && eZDebug::isDebugEnabled() )
            return 'debug';
        if ( class_exists( 'expRenderStats' ) && method_exists( 'expRenderStats', 'collecting' ) && expRenderStats::collecting() )
            return 'policy';
        return 'none';
    }

    /** @return bool the kernel can collect without debug output (Exponential 6.0.15: expRenderStats::checkCollectByPolicy()) */
    public static function kernelCollects()
    {
        return class_exists( 'expRenderStats' ) && method_exists( 'expRenderStats', 'checkCollectByPolicy' );
    }

    /**
     * What the kernel measured for this request, as it is (collected by view()).
     *
     * @return array
     */
    public static function collect()
    {
        $source = self::source();
        $raw = array( 'source' => $source, 'at' => microtime( true ), 'kernel' => self::kernelCollects() );
        $start = null;
        if ( class_exists( 'eZDebug' ) )
        {
            $debug = eZDebug::instance();
            $start = $debug->ScriptStart ? (float)$debug->ScriptStart : null;
        }
        if ( !$start && isset( $_SERVER['REQUEST_TIME_FLOAT'] ) )
            $start = (float)$_SERVER['REQUEST_TIME_FLOAT'];
        $raw['total_ms'] = $start ? max( 0, ( $raw['at'] - $start ) * 1000 ) : null;
        $raw['memory'] = memory_get_peak_usage( true );
        $raw['engine'] = class_exists( 'expDebugBarSummary' ) && method_exists( 'expDebugBarSummary', 'engine' ) ? expDebugBarSummary::engine() : PHP_SAPI;
        if ( $source === 'none' )
            return $raw;
        if ( class_exists( 'expDebugBarSummary' ) )
        {
            $summary = expDebugBarSummary::collect();
            $raw['sql'] = isset( $summary['sql'] ) ? $summary['sql'] : null;
            $raw['levels'] = array( 'time' => isset( $summary['time']['level'] ) ? $summary['time']['level'] : null,
                                    'memory' => isset( $summary['memory']['level'] ) ? $summary['memory']['level'] : null );
        }
        if ( class_exists( 'expRenderStats' ) )
        {
            $raw['view_cache'] = expRenderStats::viewCache();
            $raw['templates'] = expRenderStats::templates();
            if ( method_exists( 'expRenderStats', 'cacheBlocks' ) )
            {
                $raw['cache_blocks'] = expRenderStats::cacheBlocks();
                $raw['cache_block_totals'] = expRenderStats::cacheBlockTotals();
            }
            if ( method_exists( 'expRenderStats', 'tree' ) )
            {
                $raw['tree'] = expRenderStats::tree();
                $raw['tree_count'] = expRenderStats::treeCount();
            }
        }
        return $raw;
    }

    /**
     * What the panel shows of collect(). Pure. No file system paths beyond the design-relative template names, no
     * cache keys, nothing about other users.
     *
     * @param array $raw
     * @return array at (Unix time in ms), source, kernel, engine, total_ms, memory (bytes), sql (count, ms, level) or
     *               null, view_cache (state, age, reason) or null, cache_blocks (count, hits, slowest) or null,
     *               templates (compiled, executed, slowest) or null, blocks (list) or null, levels
     */
    public static function view( array $raw )
    {
        $round = function ( $ms ) { return $ms === null ? null : round( (float)$ms, 1 ); };
        $out = array(
            'at' => (int)round( ( isset( $raw['at'] ) ? (float)$raw['at'] : microtime( true ) ) * 1000 ),
            'source' => isset( $raw['source'] ) && in_array( $raw['source'], array( 'debug', 'policy', 'none' ), true ) ? $raw['source'] : 'none',
            'kernel' => !empty( $raw['kernel'] ),
            'engine' => isset( $raw['engine'] ) && $raw['engine'] === 'velocity' ? 'velocity' : 'php',
            'total_ms' => isset( $raw['total_ms'] ) ? $round( $raw['total_ms'] ) : null,
            'memory' => isset( $raw['memory'] ) ? (int)$raw['memory'] : null,
            'levels' => array( 'time' => null, 'memory' => null, 'sql' => null ),
            'sql' => null, 'view_cache' => null, 'cache_blocks' => null, 'templates' => null, 'blocks' => null,
        );
        $level = function ( $value ) { return in_array( $value, array( 'ok', 'warn', 'high' ), true ) ? $value : null; };
        if ( isset( $raw['levels'] ) && is_array( $raw['levels'] ) )
        {
            $out['levels']['time'] = $level( isset( $raw['levels']['time'] ) ? $raw['levels']['time'] : null );
            $out['levels']['memory'] = $level( isset( $raw['levels']['memory'] ) ? $raw['levels']['memory'] : null );
        }
        if ( $out['source'] === 'none' )
            return $out;
        if ( isset( $raw['sql'] ) && is_array( $raw['sql'] ) )
        {
            $out['sql'] = array( 'count' => (int)( isset( $raw['sql']['count'] ) ? $raw['sql']['count'] : 0 ),
                                 'ms' => $round( isset( $raw['sql']['time_ms'] ) ? $raw['sql']['time_ms'] : 0 ) );
            $out['levels']['sql'] = $level( isset( $raw['sql']['level'] ) ? $raw['sql']['level'] : null );
        }
        if ( isset( $raw['view_cache'] ) && is_array( $raw['view_cache'] ) )
        {
            $v = $raw['view_cache'];
            $state = isset( $v['state'] ) ? (string)$v['state'] : '';
            $out['view_cache'] = array(
                'state' => in_array( $state, array( 'hit', 'generated', 'uncached', 'expired', 'off', 'pending' ), true ) ? $state : 'pending',
                'age' => isset( $v['age'] ) && is_numeric( $v['age'] ) ? (int)$v['age'] : null,
                'reason' => isset( $v['reason'] ) && is_string( $v['reason'] ) ? substr( $v['reason'], 0, 200 ) : '',
                'ms' => $round( isset( $v['generate_ms'] ) && $v['generate_ms'] !== null ? $v['generate_ms'] : ( isset( $v['lookup_ms'] ) ? $v['lookup_ms'] : null ) ),
            );
        }
        if ( isset( $raw['cache_block_totals'] ) && is_array( $raw['cache_block_totals'] ) )
        {
            $t = $raw['cache_block_totals'];
            $list = isset( $raw['cache_blocks'] ) ? (array)$raw['cache_blocks'] : array();
            $ms = function ( $b ) { return (float)( isset( $b['generate_ms'] ) ? $b['generate_ms'] : 0 ) + (float)( isset( $b['lookup_ms'] ) ? $b['lookup_ms'] : 0 ); };
            usort( $list, function ( $a, $b ) use ( $ms ) { return $ms( $b ) <=> $ms( $a ); } );
            $slowest = array();
            foreach ( array_slice( $list, 0, self::MAX_CACHE_BLOCKS ) as $b )
                $slowest[] = array( 'template' => self::templateName( isset( $b['template'] ) ? $b['template'] : '' ),
                                    'line' => isset( $b['line'] ) && $b['line'] !== null ? (int)$b['line'] : null,
                                    'state' => isset( $b['state'] ) && in_array( $b['state'], array( 'hit', 'generated', 'pending' ), true ) ? $b['state'] : 'pending',
                                    'ms' => $round( $ms( $b ) ) );
            $count = (int)( isset( $t['count'] ) ? $t['count'] : 0 );
            $hits = (int)( isset( $t['hits'] ) ? $t['hits'] : 0 );
            $out['cache_blocks'] = array( 'count' => $count, 'hits' => $hits, 'misses' => max( 0, $count - $hits ), 'slowest' => $slowest );
        }
        $tree = isset( $raw['tree'] ) && is_array( $raw['tree'] ) ? $raw['tree'] : array();
        if ( isset( $raw['templates'] ) && is_array( $raw['templates'] ) )
        {
            $compiled = isset( $raw['templates']['compiled'] ) ? count( (array)$raw['templates']['compiled'] ) : 0;
            $slowest = array();
            $list = array_values( array_filter( $tree, function ( $e ) { return isset( $e['ms'] ) && $e['ms'] !== null && (int)$e['depth'] > 0; } ) );
            usort( $list, function ( $a, $b ) { return $b['ms'] <=> $a['ms']; } );
            foreach ( array_slice( $list, 0, self::MAX_TEMPLATES ) as $e )
                $slowest[] = array( 'template' => self::templateName( isset( $e['uri'] ) ? $e['uri'] : '' ), 'ms' => $round( $e['ms'] ) );
            $out['templates'] = array( 'count' => isset( $raw['tree_count'] ) ? (int)$raw['tree_count'] : count( $tree ),
                                       'compiled' => $compiled, 'slowest' => $slowest );
        }
        if ( $tree )
            $out['blocks'] = self::blocks( $tree );
        return $out;
    }

    /**
     * The layout blocks of a template tree, in the order they rendered: each design:explayouts/block/<name> template
     * with its time, how deep it sits among other blocks (a container's blocks are inside it) and the block template
     * it used (the deepest explayouts/block/... below it, block.tpl itself when there is none). Pure.
     *
     * @param array $tree expRenderStats::tree(): uri, depth, parent (index or -1), ms
     * @return array list of name, template, level, ms (null when it did not end); at most MAX_BLOCKS
     */
    public static function blocks( array $tree )
    {
        $isBlock = array();
        foreach ( $tree as $i => $e )
            $isBlock[$i] = isset( $e['uri'] ) && preg_match( self::BLOCK_TEMPLATE, (string)$e['uri'] ) === 1;
        // the block entry each template belongs to (its nearest block ancestor or itself)
        $owner = array();
        $out = array();
        $index = array();
        foreach ( $tree as $i => $e )
        {
            $parent = isset( $e['parent'] ) ? (int)$e['parent'] : -1;
            $up = $parent >= 0 && isset( $owner[$parent] ) ? $owner[$parent] : null;
            if ( !$isBlock[$i] )
            {
                // a template block is named by the template tpl_block.tpl includes (design:explayouts/tpl_block/<name>,
                // or the template an imported block maps to)
                if ( $up !== null && isset( $index[$up] ) && $out[$index[$up]]['template'] === 'tpl_block' && $parent >= 0
                     && isset( $tree[$parent]['uri'], $e['uri'] ) && preg_match( self::TPL_BLOCK_WRAPPER, (string)$tree[$parent]['uri'] ) )
                {
                    $out[$index[$up]]['template'] = 'tpl_block: ' . preg_replace( '#\.(tpl|stpl|twig)$#', '', basename( (string)$e['uri'] ) );
                    $out[$index[$up]]['named'] = true;
                }
                $owner[$i] = $up;
                continue;
            }
            preg_match( self::BLOCK_TEMPLATE, (string)$e['uri'], $m );
            $name = $m[2];
            // a wrapper (block.tpl, tpl_block.tpl) and the template it includes are one block: the inner one names it
            if ( $up !== null && isset( $index[$up] ) && in_array( $out[$index[$up]]['template'], array( 'block', 'tpl_block' ), true )
                 && !$out[$index[$up]]['named'] )
            {
                $out[$index[$up]]['template'] = $name;
                $out[$index[$up]]['named'] = true;
                $owner[$i] = $up;
                continue;
            }
            $level = $up !== null && isset( $index[$up] ) ? $out[$index[$up]]['level'] + 1 : 0;
            $owner[$i] = $i;
            if ( count( $out ) >= self::MAX_BLOCKS )
                continue;
            $index[$i] = count( $out );
            $out[] = array( 'template' => $name, 'level' => $level, 'ms' => isset( $e['ms'] ) && $e['ms'] !== null ? round( (float)$e['ms'], 1 ) : null,
                            'named' => !in_array( $name, array( 'block', 'tpl_block' ), true ) );
        }
        foreach ( $out as &$b )
            unset( $b['named'] );
        unset( $b );
        return $out;
    }

    /**
     * A template as the panel names it: the part after design: or after templates/, never a server path. Pure.
     *
     * @param string $template
     * @return string
     */
    public static function templateName( $template )
    {
        $template = (string)$template;
        if ( strncmp( $template, 'design:', 7 ) === 0 )
            return substr( $template, 7 );
        $at = strrpos( $template, '/templates/' );
        if ( $at !== false )
            return substr( $template, $at + 11 );
        return basename( $template );
    }

    /**
     * The page with the figures on the sidebar's element. Pure.
     *
     * @param string $html
     * @param array $view view()
     * @return string unchanged without the element
     */
    public static function attach( $html, array $view )
    {
        $tag = '<' . expwtHook::TAG . ' ';
        $pos = strpos( (string)$html, $tag );
        if ( $pos === false )
            return $html;
        $json = json_encode( $view, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR );
        if ( $json === false )
            return $html;
        $attribute = 'data-render="' . htmlspecialchars( $json, ENT_QUOTES, 'UTF-8' ) . '" ';
        return substr( $html, 0, $pos + strlen( $tag ) ) . $attribute . substr( $html, $pos + strlen( $tag ) );
    }

    /**
     * The action performance.load: what the panel needs besides the page's own figures. The server's time lets it tell
     * a page rendered for this view from one that came from a cache, whatever the clock of the device says.
     *
     * @return array now (Unix time in ms), kernel (collects without debug output), listed (expwt/performance is in
     *               CollectStatsPolicyList[]), debug (debug output on for the user now)
     */
    public static function status()
    {
        $listed = false;
        $ini = eZINI::instance();
        if ( $ini->hasVariable( 'DebugSettings', 'CollectStatsPolicyList' ) )
            $listed = in_array( 'expwt/performance', array_map( 'trim', (array)$ini->variable( 'DebugSettings', 'CollectStatsPolicyList' ) ), true );
        return array( 'now' => (int)round( microtime( true ) * 1000 ), 'kernel' => self::kernelCollects(), 'listed' => $listed,
                      'debug' => class_exists( 'eZDebug' ) && eZDebug::isDebugEnabled() );
    }
}
