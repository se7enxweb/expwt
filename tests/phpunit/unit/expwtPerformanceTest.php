<?php
/**
 * The Performance panel's figures (E12, expwtPerformance): what the panel gets of the kernel's debug bar data and what
 * it leaves out, the time of each layout block from the template tree, the figures on the sidebar's element, and the
 * policy behind the action.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

require_once dirname( __DIR__ ) . '/bootstrap.php';

class expwtPerformanceTest extends PHPUnit\Framework\TestCase
{
    public function testWithoutCollectingOnlyTimeAndMemory()
    {
        $v = expwtPerformance::view( array( 'source' => 'none', 'at' => 1700000000.25, 'total_ms' => 84.26, 'memory' => 4194304,
                                            'engine' => 'fpm-fcgi', 'sql' => array( 'count' => 9 ), 'tree' => array( array( 'uri' => 'design:x.tpl' ) ) ) );
        $this->assertSame( 'none', $v['source'] );
        $this->assertSame( 1700000000250, $v['at'] );
        $this->assertSame( 84.3, $v['total_ms'] );
        $this->assertSame( 'php', $v['engine'] );
        $this->assertNull( $v['sql'], 'not measured: not shown as 0' );
        $this->assertNull( $v['blocks'] );
        $this->assertNull( $v['view_cache'] );
    }

    public function testCollectedFiguresWithoutPathsOrKeys()
    {
        $v = expwtPerformance::view( array(
            'source' => 'policy', 'engine' => 'velocity', 'total_ms' => 120.0, 'memory' => 1,
            'sql' => array( 'count' => 42, 'time_ms' => 12.345, 'level' => 'ok' ),
            'levels' => array( 'time' => 'warn', 'memory' => 'bogus' ),
            'view_cache' => array( 'state' => 'hit', 'age' => '30', 'path' => '/srv/var/cache/content/1.cache', 'hash' => 'abc', 'lookup_ms' => 0.4 ),
            'cache_block_totals' => array( 'count' => 3, 'hits' => 2 ),
            'cache_blocks' => array(
                array( 'template' => '/srv/extension/x/design/media/templates/pagelayout.tpl', 'line' => 12, 'state' => 'hit', 'lookup_ms' => 0.2, 'generate_ms' => null, 'keys' => 'secret', 'path' => '/srv/var/cache/template-block/1.php' ),
                array( 'template' => 'design/media/templates/menu.tpl', 'line' => 4, 'state' => 'generated', 'lookup_ms' => 0.1, 'generate_ms' => 30.0 ),
            ),
            'templates' => array( 'compiled' => array( 'a.tpl' ), 'executed' => 5 ),
            'tree' => array(), 'tree_count' => 7,
        ) );
        $this->assertSame( 'velocity', $v['engine'] );
        $this->assertSame( array( 'count' => 42, 'ms' => 12.3 ), $v['sql'] );
        $this->assertSame( array( 'time' => 'warn', 'memory' => null, 'sql' => 'ok' ), $v['levels'] );
        $this->assertSame( array( 'state' => 'hit', 'age' => 30, 'reason' => '', 'ms' => 0.4 ), $v['view_cache'] );
        $this->assertSame( 1, $v['cache_blocks']['misses'] );
        $this->assertSame( 'menu.tpl', $v['cache_blocks']['slowest'][0]['template'], 'the slowest first' );
        $this->assertSame( 'pagelayout.tpl', $v['cache_blocks']['slowest'][1]['template'] );
        $json = json_encode( $v );
        $this->assertStringNotContainsString( '/srv/', $json, 'no server path' );
        $this->assertStringNotContainsString( 'secret', $json, 'no cache keys' );
        $this->assertSame( 7, $v['templates']['count'] );
        $this->assertSame( 1, $v['templates']['compiled'] );
    }

    public function testBlocksFromTheTemplateTree()
    {
        $e = function ( $uri, $depth, $parent, $ms ) { return array( 'uri' => $uri, 'depth' => $depth, 'parent' => $parent, 'ms' => $ms ); };
        $tree = array(
            $e( 'design:pagelayout.tpl', 0, -1, 300.0 ),                    // 0
            $e( 'design:explayouts/zone.tpl', 1, 0, 120.0 ),                // 1
            $e( 'design:explayouts/block/block.tpl', 2, 1, 40.0 ),          // 2 a wrapper ...
            $e( 'design:explayouts/block/title.tpl', 3, 2, 30.0 ),          // 3 ... named by the block it includes
            $e( 'design:parts/x.tpl', 4, 3, 5.0 ),                          // 4
            $e( 'design:explayouts/block/block.tpl', 2, 1, 70.0 ),          // 5 a container ...
            $e( 'design:explayouts/block/container.tpl', 3, 5, 65.0 ),      // 6
            $e( 'design:explayouts/zone.tpl', 4, 6, 60.0 ),                 // 7
            $e( 'design:explayouts/block/block.tpl', 5, 7, 20.0 ),          // 8 ... with a block inside
            $e( 'design:explayouts/block/list.tpl', 6, 8, 18.0 ),           // 9
            $e( 'stpl:explayouts/block/html.stpl', 2, 1, null ),            // 10 did not end
        );
        $this->assertSame( array(
            array( 'template' => 'title', 'level' => 0, 'ms' => 40.0 ),
            array( 'template' => 'container', 'level' => 0, 'ms' => 70.0 ),
            array( 'template' => 'list', 'level' => 1, 'ms' => 20.0 ),
            array( 'template' => 'html', 'level' => 0, 'ms' => null ),
        ), expwtPerformance::blocks( $tree ) );
        $this->assertSame( array(), expwtPerformance::blocks( array( $e( 'design:pagelayout.tpl', 0, -1, 1.0 ) ) ) );
        // a template block is named by the template it includes
        $this->assertSame( array( array( 'template' => 'tpl_block: newsletter_signup', 'level' => 0, 'ms' => 9.0 ) ), expwtPerformance::blocks( array(
            $e( 'design:explayouts/block/tpl_block.tpl', 0, -1, 9.0 ),
            $e( 'design:explayouts/tpl_block/newsletter_signup.tpl', 1, 0, 8.0 ),
        ) ) );
    }

    public function testTemplateNames()
    {
        $this->assertSame( 'node/view/full.tpl', expwtPerformance::templateName( 'design:node/view/full.tpl' ) );
        $this->assertSame( 'explayouts/zone.tpl', expwtPerformance::templateName( '/srv/ext/design/media/templates/explayouts/zone.tpl' ) );
        $this->assertSame( 'a.twig', expwtPerformance::templateName( '/srv/x/a.twig' ) );
    }

    public function testFiguresOnTheSidebarsElement()
    {
        $html = '<html><body><p>x</p><exp-wt data-config="{}"></exp-wt><script src="/x.js" defer></script></body></html>';
        $out = expwtPerformance::attach( $html, array( 'total_ms' => 5.5, 'note' => '"<a>' ) );
        $this->assertStringContainsString( '<exp-wt data-render="{&quot;total_ms&quot;:5.5,&quot;note&quot;:&quot;\&quot;&lt;a&gt;&quot;}" data-config="{}">', $out );
        $this->assertSame( strlen( $html ), strlen( str_replace( substr( $out, strpos( $out, 'data-render=' ), strpos( $out, 'data-config=' ) - strpos( $out, 'data-render=' ) ), '', $out ) ) );
        $this->assertSame( '<p>no sidebar</p>', expwtPerformance::attach( '<p>no sidebar</p>', array( 'a' => 1 ) ) );
    }

    public function testTheActionNeedsExpwtPerformance()
    {
        $this->assertContains( 'performance', expwtAccess::FUNCTIONS );
        $this->assertSame( 'performance', expwtAccess::functionFor( 'performance.load' ) );
        $this->assertFalse( expwtAccess::writes( 'performance.load' ) );
        $caps = array_fill_keys( expwtAccess::FUNCTIONS, true );
        $this->assertTrue( expwtAccess::allows( 'performance.load', $caps ) );
        $caps['performance'] = false;
        $this->assertFalse( expwtAccess::allows( 'performance.load', $caps ), 'an editor with expwt/edit only is refused' );
        $this->assertContains( 'performance', expwtState::PANELS );
        $this->assertContains( 'performance', expwtRegistry::CORE_PANELS );
    }
}
