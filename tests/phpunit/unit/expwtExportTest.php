<?php
/**
 * The page as a file (E13, expwtExport): the blocks of a page's fields, rich text to blocks, Markdown, the Word file
 * (a ZIP read back from its central directory, every part well-formed XML), the file name; the share link actions,
 * their policies and "Verify it's you", and the lifetimes the panel offers.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

require_once dirname( __DIR__ ) . '/bootstrap.php';

class expwtExportTest extends PHPUnit\Framework\TestCase
{
    private static function page()
    {
        $tr = function ( $text ) { return $text === 'Yes' ? 'Ja' : $text; };
        return expwtExport::blocks( 'Fit & Healthy', array( 'Article · eng-GB' ), array(
            array( 'name' => 'Title', 'input' => 'text', 'value' => 'Fit & Healthy' ),
            array( 'name' => 'Empty', 'input' => 'text', 'value' => '' ),
            array( 'name' => 'Body', 'input' => 'richtext', 'value' => '<h2>Start *slowly*</h2><p>Walk <strong>daily</strong>, see <a href="https://example.com/a b">this</a>.</p>'
                . '<ul><li>One<ul><li>Inner</li></ul></li><li>Two</li></ul><ol><li>First</li></ol><blockquote><p>Quoted</p></blockquote>'
                . '<pre>code &lt;x&gt;</pre><table><tr><th>A</th><td>B</td></tr></table><p><img src="/x.jpg" alt="A runner"></p><script>alert(1)</script>' ),
            array( 'name' => 'Featured', 'input' => 'checkbox', 'value' => '1' ),
            array( 'name' => 'Notes', 'input' => 'textarea', 'value' => "First paragraph\nsame one\n\n# Second" ),
        ), $tr );
    }

    public function testBlocksOfThePage()
    {
        $blocks = self::page();
        $types = array_column( $blocks, 'type' );
        $this->assertSame( array( 'h1', 'meta', 'h2', 'p', 'h2', 'h4', 'p', 'li', 'li', 'li', 'li', 'quote', 'code', 'p', 'p', 'h2', 'p', 'h2', 'p', 'p' ), $types );
        $this->assertNotContains( 'Empty', array_map( function ( $b ) { return $b['runs'][0]['t']; }, $blocks ), 'an empty field is left out' );
        $this->assertSame( 1, $blocks[8]['depth'], 'a nested list item' );
        $this->assertSame( 1, $blocks[10]['number'], 'a numbered item' );
        $this->assertSame( 'Ja', $blocks[16]['runs'][0]['t'], 'a checkbox as Yes, translated' );
    }

    public function testMarkdown()
    {
        $md = expwtExport::markdown( self::page() );
        $this->assertStringStartsWith( "# Fit & Healthy\n\n_Article · eng-GB_\n\n## Title\n\nFit & Healthy\n\n## Body\n\n#### Start \\*slowly\\*\n\n", $md );
        $this->assertStringContainsString( 'Walk **daily**, see [this](https://example.com/a%20b).', $md );
        $this->assertStringContainsString( "- One\n  - Inner\n- Two\n\n1. First", $md );
        $this->assertStringContainsString( "> Quoted", $md );
        $this->assertStringContainsString( "```\ncode <x>\n```", $md );
        $this->assertStringContainsString( 'A \\| B', $md );
        $this->assertStringContainsString( '\\[A runner\\]', $md );
        $this->assertStringContainsString( "First paragraph  \nsame one\n\n\\# Second", $md, 'a line break kept, a # at the start escaped' );
        $this->assertStringNotContainsString( 'alert', $md, 'no script' );
    }

    public function testWordFileIsAZipWithParsedWordprocessingML()
    {
        $bytes = expwtExport::docx( self::page(), 'Fit & Healthy', 1767225600 );
        $this->assertSame( "PK\x03\x04", substr( $bytes, 0, 4 ) );
        $files = $this->unzip( $bytes );
        $this->assertSame( array( '[Content_Types].xml', '_rels/.rels', 'docProps/core.xml', 'word/_rels/document.xml.rels', 'word/document.xml', 'word/styles.xml' ), array_keys( $files ) );
        $document = $files['word/document.xml'];
        $rels = $files['word/_rels/document.xml.rels'];
        foreach ( $files as $xml )
        {
            $dom = new DOMDocument();
            $this->assertTrue( $dom->loadXML( $xml ) );
        }
        $this->assertStringContainsString( '<w:pStyle w:val="Title"/>', $document );
        $this->assertStringContainsString( 'Fit &amp; Healthy', $document );
        $this->assertStringContainsString( '<w:b/>', $document );
        $this->assertStringContainsString( '<w:hyperlink r:id="rIdL1">', $document );
        $this->assertStringContainsString( 'Target="https://example.com/a b" TargetMode="External"', $rels );
        $this->assertStringNotContainsString( "\x00", $document );
    }

    /**
     * Reads a ZIP from its central directory, as an unzip program does: name => bytes, each entry's CRC checked.
     *
     * @return array
     */
    private function unzip( $bytes )
    {
        $end = strrpos( $bytes, "PK\x05\x06" );
        $this->assertNotFalse( $end, 'the end of the central directory' );
        $e = unpack( 'vdisk/vstart/vhere/vtotal/Vsize/Voffset/vcomment', substr( $bytes, $end + 4, 18 ) );
        $files = array();
        $at = $e['offset'];
        for ( $i = 0; $i < $e['total']; $i++ )
        {
            $this->assertSame( "PK\x01\x02", substr( $bytes, $at, 4 ) );
            $c = unpack( 'vmade/vneed/vflags/vmethod/vtime/vdate/Vcrc/Vcsize/Vusize/vnlen/velen/vclen/vdisk/vint/Vext/Voffset', substr( $bytes, $at + 4, 42 ) );
            $name = substr( $bytes, $at + 46, $c['nlen'] );
            $this->assertSame( "PK\x03\x04", substr( $bytes, $c['offset'], 4 ), $name );
            $l = unpack( 'vneed/vflags/vmethod/vtime/vdate/Vcrc/Vcsize/Vusize/vnlen/velen', substr( $bytes, $c['offset'] + 4, 26 ) );
            $this->assertSame( array( $c['crc'], $c['csize'], $c['usize'] ), array( $l['crc'], $l['csize'], $l['usize'] ), $name );
            $data = gzinflate( substr( $bytes, $c['offset'] + 30 + $l['nlen'] + $l['elen'], $l['csize'] ) );
            $this->assertSame( $c['usize'], strlen( $data ), $name );
            $this->assertSame( $c['crc'], crc32( $data ) & 0xFFFFFFFF, $name );
            $files[$name] = $data;
            $at += 46 + $c['nlen'] + $c['elen'] + $c['clen'];
        }
        $this->assertSame( $end, $at, 'the central directory ends where its end record is' );
        return $files;
    }

    public function testFileName()
    {
        $this->assertSame( 'Fit-Healthy.md', expwtExport::fileName( 'Fit & Healthy', 'md' ) );
        $this->assertSame( 'Über-uns.docx', expwtExport::fileName( 'Über uns!', 'docx' ) );
        $this->assertSame( 'page.md', expwtExport::fileName( '../..', 'md' ) );
    }

    public function testEscape()
    {
        $this->assertSame( '\\- not a list', expwtExport::escape( '- not a list' ) );
        $this->assertSame( '1\\. not a list', expwtExport::escape( '1. not a list' ) );
        $this->assertSame( 'a \\<b\\> \\_c\\_', expwtExport::escape( 'a <b> _c_' ) );
    }

    public function testActionsPoliciesAndVerifyItsYou()
    {
        $this->assertSame( 'use', expwtAccess::functionFor( 'export.fields' ) );
        $this->assertTrue( expwtAccess::writes( 'export.fields' ), 'a POST, so it can ask "Verify it\'s you"' );
        foreach ( array( 'share.load', 'share.create', 'share.revoke' ) as $action )
            $this->assertSame( 'edit', expwtAccess::functionFor( $action ), $action );
        $this->assertFalse( expwtAccess::writes( 'share.load' ) );
        $this->assertTrue( expwtAccess::writes( 'share.create' ) );
        $this->assertSame( 'export', expwtAccess::REAUTH_GROUPS['share.create'] );
        $this->assertSame( 'export', expwtAccess::REAUTH_GROUPS['export.fields'] );
        $caps = array_fill_keys( expwtAccess::FUNCTIONS, false );
        $caps['use'] = true;
        $this->assertTrue( expwtAccess::allows( 'export.fields', $caps ) );
        $this->assertFalse( expwtAccess::allows( 'share.create', $caps ), 'no expwt/edit, no share link' );
        $this->assertContains( 'export', expwtState::PANELS );
        $this->assertContains( 'export', expwtRegistry::CORE_PANELS );
    }

    public function testShareLifetimesUpToTheKernelsLongest()
    {
        $this->assertSame( array( array( 'seconds' => 3600, 'chosen' => false ), array( 'seconds' => 86400, 'chosen' => true ),
                                  array( 'seconds' => 259200, 'chosen' => false ), array( 'seconds' => 604800, 'chosen' => false ) ),
                           expwtGateway::shareLifetimes( 604800, 86400 ) );
        $this->assertSame( array( 3600, 86400 ), array_column( expwtGateway::shareLifetimes( 172800, 3600 ), 'seconds' ) );
        $this->assertSame( array( array( 'seconds' => 1800, 'chosen' => true ) ), expwtGateway::shareLifetimes( 1800, 1800 ) );
    }
}
