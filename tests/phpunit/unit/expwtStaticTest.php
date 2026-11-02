<?php
/**
 * The Static panel (S6, expwtStatic): whether this page is stale on a target, its public address there, the last
 * publish as the panel shows it, the actions and their policies, and that every text is the kernel's own.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

require_once dirname( __DIR__ ) . '/bootstrap.php';

class expwtStaticTest extends PHPUnit\Framework\TestCase
{
    public function testPageState()
    {
        $rows = array(
            'index.html' => array( 'path' => 'index.html', 'node_id' => 2, 'stale' => 0 ),
            'news/index.html' => array( 'path' => 'news/index.html', 'node_id' => 57, 'stale' => 0 ),
            'news/page2/index.html' => array( 'path' => 'news/page2/index.html', 'node_id' => 57, 'stale' => 1730000000 ),
            '.queue/99' => array( 'path' => '.queue/99', 'node_id' => 99, 'stale' => 1730000000 ),
        );
        $this->assertSame( array( 'state' => 'current', 'path' => 'index.html' ), expwtStatic::pageState( $rows, 2 ) );
        // one of the node's pages changed after the publish: the page is stale; its address is the shortest path
        $this->assertSame( array( 'state' => 'stale', 'path' => 'news/index.html' ), expwtStatic::pageState( $rows, 57 ) );
        // changed, but never published: queued only, not in the build
        $this->assertSame( array( 'state' => 'missing', 'path' => '' ), expwtStatic::pageState( $rows, 99 ) );
        $this->assertSame( 'missing', expwtStatic::pageState( array(), 2 )['state'] );
    }

    public function testPageUrl()
    {
        $this->assertSame( 'https://owner.github.io/site/', expwtStatic::pageUrl( 'https://owner.github.io/site/', 'index.html' ) );
        $this->assertSame( 'https://owner.github.io/site/news/', expwtStatic::pageUrl( 'https://owner.github.io/site', 'news/index.html' ) );
        $this->assertSame( 'https://example.com/a%20b/x.html', expwtStatic::pageUrl( 'https://example.com/', 'a b/x.html' ) );
        $this->assertSame( '', expwtStatic::pageUrl( '', 'index.html' ), 'no public address: no link' );
        $this->assertSame( '', expwtStatic::pageUrl( 'javascript:alert(1)', 'index.html' ) );
        $this->assertSame( '', expwtStatic::pageUrl( 'https://example.com/', '' ), 'not in the build: no link' );
    }

    public function testRunView()
    {
        $this->assertNull( expwtStatic::runView( null ) );
        $this->assertSame( array( 'at' => 20, 'state' => 'pushed', 'tag' => 'static/demo/3' ),
                           expwtStatic::runView( array( 'run_id' => 'r', 'state' => 'done', 'push_state' => 'pushed', 'started_at' => 10, 'finished_at' => 20, 'tag_name' => 'static/demo/3' ) ) );
        $this->assertSame( 'not-pushed', expwtStatic::runView( array( 'run_id' => 'r', 'state' => 'done', 'push_state' => 'pending' ) )['state'] );
        $this->assertSame( 'failed', expwtStatic::runView( array( 'run_id' => 'r', 'state' => 'failed', 'started_at' => 10 ) )['state'] );
        $this->assertSame( 10, expwtStatic::runView( array( 'run_id' => 'r', 'state' => 'failed', 'started_at' => 10 ) )['at'] );
        $this->assertSame( 'unchanged', expwtStatic::runView( array( 'run_id' => 'r', 'state' => 'unchanged' ) )['state'] );
    }

    public function testActionsAndPolicies()
    {
        $this->assertContains( 'static', expwtState::PANELS );
        $this->assertContains( 'static', expwtRegistry::CORE_PANELS );
        $this->assertFalse( expwtAccess::writes( 'static.load' ) );
        $this->assertTrue( expwtAccess::writes( 'static.publish' ) );
        // "Verify it's you" as the administration's Publish of Setup > Static publishing asks it
        $this->assertSame( array( 'module' => 'staticpublish', 'view' => 'preview', 'post' => array( 'PublishButton' => '1' ) ), expwtAccess::kernelView( 'static.publish' ) );
        $this->assertNull( expwtAccess::kernelView( 'static.load' ) );
        $use = array_fill_keys( expwtAccess::FUNCTIONS, false );
        $use['use'] = true;
        $this->assertTrue( expwtAccess::allows( 'static.publish', $use ), 'the kernel\'s staticpublish/publish decides' );
        $this->assertFalse( expwtAccess::allows( 'static.load', array_fill_keys( expwtAccess::FUNCTIONS, false ) ) );
    }

    /** The panel adds no text to translate: every text is a source of the kernel's context design/admin/staticpublish */
    public function testEveryTextIsTheKernels()
    {
        $file = getcwd() . '/share/translations/untranslated/translation.ts';
        if ( !is_file( $file ) )
            $this->markTestSkipped( 'run from the installation\'s root' );
        $xml = simplexml_load_file( $file );
        $sources = array();
        foreach ( $xml->context as $context )
            if ( (string)$context->name === expwtStatic::CONTEXT )
                foreach ( $context->message as $message )
                    $sources[(string)$message->source] = true;
        if ( !$sources )
            $this->markTestSkipped( 'a kernel without static publishing' );
        foreach ( expwtStatic::LABELS as $key => $source )
            $this->assertArrayHasKey( $source, $sources, $key );
        $this->assertArrayHasKey( 'The job could not be started.', $sources );
    }
}
