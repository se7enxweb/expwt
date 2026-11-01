<?php
/**
 * The fields of a page as a file (E13): Markdown or Word (.docx). The page's fields, in the form the Page panel has
 * them (rich text as HTML, everything else as its text), become a list of blocks (headings, paragraphs, list items,
 * quotes, code), and the blocks are written as Markdown or as a Word document. The Word file is written here, a
 * small ZIP of WordprocessingML with styles for the title and the headings: no library, no temporary file.
 *
 * A block: array( 'type' => h1|h2|h3|h4|p|li|quote|code|meta, 'runs' => array( run ... ), 'depth' => 0 ... )
 * A run:   array( 't' => text, 'b' => bold, 'i' => italic, 'href' => address, 'code' => true )
 *
 * Pure, tested without a database (tests/phpunit/unit/expwtExportTest.php).
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

class expwtExport
{
    const FORMATS = array(
        'md' => array( 'mime' => 'text/markdown; charset=utf-8', 'extension' => 'md' ),
        'docx' => array( 'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'extension' => 'docx' ),
    );

    /** @var int the lists numbered so far (each top-level list a group of its own) */
    protected static $lists = 0;

    // ------------------------------------------------------------------------------------------------ blocks

    /**
     * The document of a page.
     *
     * @param string $title the page's name
     * @param string[] $meta lines under the title (class, language, version, date)
     * @param array[] $fields each name, input (expwtFields), value (HTML for rich text), names
     * @param callable $tr function( string $text ): string, for Yes and No
     * @return array[] blocks
     */
    public static function blocks( $title, array $meta, array $fields, callable $tr )
    {
        $blocks = array( self::block( 'h1', (string)$title ) );
        foreach ( $meta as $line )
            $blocks[] = self::block( 'meta', (string)$line );
        foreach ( $fields as $field )
        {
            $value = isset( $field['value'] ) ? trim( (string)$field['value'] ) : '';
            $input = isset( $field['input'] ) ? (string)$field['input'] : 'text';
            if ( $value === '' )
                continue;
            $blocks[] = self::block( 'h2', (string)$field['name'] );
            if ( $input === 'richtext' )
            {
                foreach ( self::fromHtml( $value ) as $block )
                    $blocks[] = $block;
                continue;
            }
            if ( $input === 'checkbox' )
                $value = call_user_func( $tr, $value === '1' ? 'Yes' : 'No' );
            foreach ( preg_split( '/\R{2,}/u', $value ) as $paragraph )
                if ( trim( $paragraph ) !== '' )
                    $blocks[] = self::block( 'p', trim( $paragraph ) );
        }
        return $blocks;
    }

    /**
     * @param string $type
     * @param string $text
     * @return array a block of one plain run
     */
    public static function block( $type, $text )
    {
        return array( 'type' => $type, 'runs' => array( array( 't' => $text ) ), 'depth' => 0 );
    }

    /**
     * The blocks of rich text: headings one level below the field's name (h1 of the text is h3), paragraphs, list
     * items with their depth, quotes, code; bold, italic, code and links inside. Images are their alternative text;
     * a table is a paragraph per row, the cells parted by " | ".
     *
     * @param string $html
     * @return array[]
     */
    public static function fromHtml( $html )
    {
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors( true );
        $doc->loadHTML( '<?xml encoding="utf-8"?><div id="expwt-export">' . $html . '</div>', LIBXML_NONET | LIBXML_NOBLANKS );
        libxml_clear_errors();
        libxml_use_internal_errors( $previous );
        $root = $doc->getElementById( 'expwt-export' );
        $blocks = array();
        if ( $root )
            self::walk( $root, $blocks, 0 );
        return $blocks;
    }

    /**
     * @param DOMNode $parent
     * @param array $blocks
     * @param int $depth of lists
     * @return void
     */
    protected static function walk( DOMNode $parent, array &$blocks, $depth, $group = 0 )
    {
        $loose = array();
        $flush = function () use ( &$loose, &$blocks ) {
            $runs = self::trimRuns( $loose );
            if ( $runs )
                $blocks[] = array( 'type' => 'p', 'runs' => $runs, 'depth' => 0 );
            $loose = array();
        };
        foreach ( $parent->childNodes as $node )
        {
            $name = $node instanceof DOMElement ? strtolower( $node->nodeName ) : '';
            if ( preg_match( '/^h([1-6])$/', $name, $m ) )
            {
                $flush();
                $blocks[] = array( 'type' => 'h' . min( 4, (int)$m[1] + 2 ), 'runs' => self::trimRuns( self::runs( $node ) ), 'depth' => 0 );
            }
            else if ( $name === 'p' )
            {
                $flush();
                $runs = self::trimRuns( self::runs( $node ) );
                if ( $runs )
                    $blocks[] = array( 'type' => 'p', 'runs' => $runs, 'depth' => 0 );
            }
            else if ( $name === 'ul' || $name === 'ol' )
            {
                $flush();
                $number = 0;
                // a list at the top and the lists inside it are one group: Markdown keeps their items together
                $listGroup = $depth === 0 ? ++self::$lists : $group;
                foreach ( $node->childNodes as $item )
                {
                    if ( !$item instanceof DOMElement || strtolower( $item->nodeName ) !== 'li' )
                        continue;
                    $number++;
                    $runs = array();
                    $nested = array();
                    foreach ( $item->childNodes as $child )
                    {
                        $childName = $child instanceof DOMElement ? strtolower( $child->nodeName ) : '';
                        if ( $childName === 'ul' || $childName === 'ol' )
                            $nested[] = $child;
                        else
                            $runs = array_merge( $runs, self::runs( $child ) );
                    }
                    $blocks[] = array( 'type' => 'li', 'runs' => self::trimRuns( $runs ), 'depth' => $depth,
                                       'number' => $name === 'ol' ? $number : 0, 'group' => $listGroup );
                    foreach ( $nested as $list )
                    {
                        $wrapper = $list->ownerDocument->createElement( 'div' );
                        $wrapper->appendChild( $list->cloneNode( true ) );
                        self::walk( $wrapper, $blocks, $depth + 1, $listGroup );
                    }
                }
            }
            else if ( $name === 'blockquote' )
            {
                $flush();
                $inner = array();
                self::walk( $node, $inner, $depth, $group );
                foreach ( $inner as $block )
                    $blocks[] = array( 'type' => 'quote' ) + $block;
            }
            else if ( $name === 'pre' )
            {
                $flush();
                $blocks[] = array( 'type' => 'code', 'runs' => array( array( 't' => rtrim( $node->textContent ) ) ), 'depth' => 0 );
            }
            else if ( $name === 'table' )
            {
                $flush();
                foreach ( $node->getElementsByTagName( 'tr' ) as $row )
                {
                    $cells = array();
                    foreach ( $row->childNodes as $cell )
                        if ( $cell instanceof DOMElement && in_array( strtolower( $cell->nodeName ), array( 'td', 'th' ), true ) )
                            $cells[] = trim( preg_replace( '/\s+/u', ' ', $cell->textContent ) );
                    if ( implode( '', $cells ) !== '' )
                        $blocks[] = array( 'type' => 'p', 'runs' => array( array( 't' => implode( ' | ', $cells ) ) ), 'depth' => 0 );
                }
            }
            else if ( in_array( $name, array( 'div', 'section', 'article', 'figure', 'aside', 'header', 'footer' ), true ) )
            {
                $flush();
                self::walk( $node, $blocks, $depth, $group );
            }
            else if ( $name === 'br' )
                $loose[] = array( 't' => "\n" );
            else if ( !in_array( $name, array( 'script', 'style', 'template' ), true ) )
                $loose = array_merge( $loose, self::runs( $node ) );
        }
        $flush();
    }

    /**
     * @param DOMNode $node
     * @param array $style b, i, href, code of the runs around
     * @return array[] the inline runs of a node
     */
    protected static function runs( DOMNode $node, array $style = array() )
    {
        if ( $node instanceof DOMText )
        {
            $text = preg_replace( '/\s+/u', ' ', $node->nodeValue );
            return $text === '' ? array() : array( array( 't' => $text ) + $style );
        }
        if ( !$node instanceof DOMElement )
            return array();
        $name = strtolower( $node->nodeName );
        if ( in_array( $name, array( 'script', 'style', 'template' ), true ) )
            return array();
        if ( $name === 'br' )
            return array( array( 't' => "\n" ) );
        if ( $name === 'img' )
        {
            $alt = trim( (string)$node->getAttribute( 'alt' ) );
            return $alt === '' ? array() : array( array( 't' => '[' . $alt . ']' ) + $style );
        }
        if ( $name === 'strong' || $name === 'b' )
            $style['b'] = true;
        else if ( $name === 'em' || $name === 'i' )
            $style['i'] = true;
        else if ( $name === 'code' || $name === 'kbd' )
            $style['code'] = true;
        else if ( $name === 'a' && preg_match( '#^(https?:|mailto:|/)#i', (string)$node->getAttribute( 'href' ) ) )
            $style['href'] = (string)$node->getAttribute( 'href' );
        $out = array();
        foreach ( $node->childNodes as $child )
            $out = array_merge( $out, self::runs( $child, $style ) );
        return $out;
    }

    /**
     * @param array[] $runs
     * @return array[] without the white space at the start and the end, nothing when there is no text
     */
    protected static function trimRuns( array $runs )
    {
        while ( $runs && trim( $runs[0]['t'] ) === '' )
            array_shift( $runs );
        while ( $runs && trim( $runs[count( $runs ) - 1]['t'] ) === '' )
            array_pop( $runs );
        if ( $runs )
        {
            $runs[0]['t'] = ltrim( $runs[0]['t'] );
            $runs[count( $runs ) - 1]['t'] = rtrim( $runs[count( $runs ) - 1]['t'] );
        }
        return $runs;
    }

    // ------------------------------------------------------------------------------------------------ Markdown

    /**
     * @param array[] $blocks
     * @return string CommonMark
     */
    public static function markdown( array $blocks )
    {
        $out = array();
        $previous = null;
        foreach ( $blocks as $block )
        {
            $text = self::markdownRuns( $block['runs'], $block['type'] === 'code' );
            switch ( $block['type'] )
            {
                case 'h1': case 'h2': case 'h3': case 'h4':
                    $line = str_repeat( '#', (int)substr( $block['type'], 1 ) ) . ' ' . str_replace( "\n", ' ', $text );
                    break;
                case 'li':
                    $marker = !empty( $block['number'] ) ? $block['number'] . '.' : '-';
                    $line = str_repeat( '  ', (int)$block['depth'] ) . $marker . ' ' . str_replace( "\n", ' ', $text );
                    break;
                case 'quote':
                    $line = '> ' . str_replace( "\n", "\n> ", $text );
                    break;
                case 'code':
                    $line = "```\n" . $text . "\n```";
                    break;
                case 'meta':
                    $line = '_' . $text . '_';
                    break;
                default:
                    $line = str_replace( "\n", "  \n", $text );
            }
            // list items of one list stand together, everything else is parted by an empty line
            $together = $previous !== null && $previous['type'] === 'li' && $block['type'] === 'li'
                        && ( isset( $previous['group'] ) ? $previous['group'] : 0 ) === ( isset( $block['group'] ) ? $block['group'] : 0 );
            if ( $previous !== null )
                $out[] = $together ? "\n" : "\n\n";
            $out[] = $line;
            $previous = $block;
        }
        return implode( '', $out ) . "\n";
    }

    /**
     * @param array[] $runs
     * @param bool $raw code: no escaping, no marks
     * @return string
     */
    protected static function markdownRuns( array $runs, $raw = false )
    {
        $out = '';
        foreach ( $runs as $run )
        {
            if ( $raw )
            {
                $out .= $run['t'];
                continue;
            }
            $text = !empty( $run['code'] ) ? '`' . str_replace( '`', "'", $run['t'] ) . '`' : self::escape( $run['t'] );
            if ( trim( $text ) === '' )
            {
                $out .= $text;
                continue;
            }
            // the marks go round the text, the spaces around it stay outside
            preg_match( '/^(\s*)(.*?)(\s*)$/su', $text, $m );
            $inner = $m[2];
            if ( !empty( $run['href'] ) )
                $inner = '[' . $inner . '](' . str_replace( array( ' ', ')' ), array( '%20', '%29' ), $run['href'] ) . ')';
            if ( !empty( $run['i'] ) )
                $inner = '_' . $inner . '_';
            if ( !empty( $run['b'] ) )
                $inner = '**' . $inner . '**';
            $out .= $m[1] . $inner . $m[3];
        }
        return $out;
    }

    /**
     * @param string $text
     * @return string with the characters Markdown would read as marks escaped
     */
    public static function escape( $text )
    {
        $text = preg_replace( '/([\\\\`*_\[\]<>|])/u', '\\\\$1', (string)$text );
        // what starts a line: a heading, a quote, a list item
        $text = preg_replace( '/^(\s*)([#>+-])(\s)/mu', '$1\\\\$2$3', $text );
        return preg_replace( '/^(\s*)(\d+)\.(\s)/mu', '$1$2\\\\.$3', $text );
    }

    // ------------------------------------------------------------------------------------------------ Word

    /**
     * @param array[] $blocks
     * @param string $title the document's title (its properties)
     * @param int $time when it was made
     * @return string the bytes of a .docx file
     */
    public static function docx( array $blocks, $title, $time )
    {
        $body = '';
        foreach ( $blocks as $block )
            $body .= self::paragraph( $block );
        $w = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"';
        $links = array();
        $body = preg_replace_callback( '/\x00LINK:([^\x00]*)\x00/', function ( $m ) use ( &$links ) {
            $id = 'rIdL' . ( count( $links ) + 1 );
            $links[$id] = base64_decode( $m[1] );
            return $id;
        }, $body );
        $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
                  . '<w:document ' . $w . '><w:body>' . $body
                  . '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1418" w:right="1418" w:bottom="1418" w:left="1418" w:header="709" w:footer="709" w:gutter="0"/></w:sectPr>'
                  . '</w:body></w:document>';
        $rels = '';
        foreach ( $links as $id => $href )
            $rels .= '<Relationship Id="' . $id . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="'
                   . self::xml( $href ) . '" TargetMode="External"/>';
        $files = array(
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
                . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                . '<Default Extension="xml" ContentType="application/xml"/>'
                . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
                . '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
                . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
                . '</Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
                . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
                . '</Relationships>',
            'docProps/core.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
                . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
                . '<dc:title>' . self::xml( $title ) . '</dc:title>'
                . '<dcterms:created xsi:type="dcterms:W3CDTF">' . gmdate( 'Y-m-d\TH:i:s\Z', (int)$time ) . '</dcterms:created>'
                . '</cp:coreProperties>',
            'word/_rels/document.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
                . $rels . '</Relationships>',
            'word/document.xml' => $document,
            'word/styles.xml' => self::styles(),
        );
        return self::zip( $files, $time );
    }

    /**
     * @param array $block
     * @return string a w:p
     */
    protected static function paragraph( array $block )
    {
        $styles = array( 'h1' => 'Title', 'h2' => 'Heading1', 'h3' => 'Heading2', 'h4' => 'Heading3', 'quote' => 'Quote',
                         'code' => 'Code', 'meta' => 'Subtitle', 'li' => 'ListParagraph' );
        $props = isset( $styles[$block['type']] ) ? '<w:pStyle w:val="' . $styles[$block['type']] . '"/>' : '';
        if ( $block['type'] === 'li' )
            $props .= '<w:ind w:left="' . ( 360 * ( (int)$block['depth'] + 1 ) ) . '" w:hanging="360"/>';
        $runs = '';
        if ( $block['type'] === 'li' )
            $runs .= self::run( array( 't' => ( !empty( $block['number'] ) ? $block['number'] . '.' : "\u{2022}" ) . "\t" ) );
        foreach ( $block['runs'] as $run )
        {
            if ( !empty( $run['href'] ) && $block['type'] !== 'code' )
                $runs .= '<w:hyperlink r:id="' . "\x00LINK:" . base64_encode( $run['href'] ) . "\x00" . '">' . self::run( $run, true ) . '</w:hyperlink>';
            else
                $runs .= self::run( $run );
        }
        return '<w:p>' . ( $props !== '' ? '<w:pPr>' . $props . '</w:pPr>' : '' ) . $runs . '</w:p>';
    }

    /**
     * @param array $run
     * @param bool $link
     * @return string a w:r; a line break inside the text is a w:br
     */
    protected static function run( array $run, $link = false )
    {
        $props = ( !empty( $run['b'] ) ? '<w:b/>' : '' ) . ( !empty( $run['i'] ) ? '<w:i/>' : '' )
               . ( !empty( $run['code'] ) ? '<w:rFonts w:ascii="Consolas" w:hAnsi="Consolas" w:cs="Consolas"/>' : '' )
               . ( $link ? '<w:rStyle w:val="Hyperlink"/>' : '' );
        $parts = explode( "\n", (string)$run['t'] );
        $inner = array();
        foreach ( $parts as $part )
            $inner[] = '<w:t xml:space="preserve">' . self::xml( str_replace( "\t", '', $part ) ) . '</w:t>';
        $tab = strpos( (string)$run['t'], "\t" ) !== false ? '<w:tab/>' : '';
        return '<w:r>' . ( $props !== '' ? '<w:rPr>' . $props . '</w:rPr>' : '' ) . implode( '<w:br/>', $inner ) . $tab . '</w:r>';
    }

    /** @return string word/styles.xml: the normal text, the title, three headings, a quote, code, a list paragraph, links */
    protected static function styles()
    {
        $heading = function ( $id, $name, $size, $level ) {
            return '<w:style w:type="paragraph" w:styleId="' . $id . '"><w:name w:val="' . $name . '"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:qFormat/>'
                 . '<w:pPr><w:keepNext/><w:spacing w:before="240" w:after="80"/>' . ( $level !== null ? '<w:outlineLvl w:val="' . $level . '"/>' : '' ) . '</w:pPr>'
                 . '<w:rPr><w:b/><w:sz w:val="' . $size . '"/></w:rPr></w:style>';
        };
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
             . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
             . '<w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:cs="Calibri"/><w:sz w:val="22"/><w:lang w:val="en-US"/></w:rPr></w:rPrDefault>'
             . '<w:pPrDefault><w:pPr><w:spacing w:after="120" w:line="276" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults>'
             . '<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:qFormat/></w:style>'
             . $heading( 'Title', 'Title', 40, null )
             . $heading( 'Heading1', 'heading 1', 32, 0 )
             . $heading( 'Heading2', 'heading 2', 28, 1 )
             . $heading( 'Heading3', 'heading 3', 24, 2 )
             . '<w:style w:type="paragraph" w:styleId="Subtitle"><w:name w:val="Subtitle"/><w:basedOn w:val="Normal"/><w:rPr><w:i/><w:color w:val="595959"/></w:rPr></w:style>'
             . '<w:style w:type="paragraph" w:styleId="Quote"><w:name w:val="Quote"/><w:basedOn w:val="Normal"/><w:pPr><w:ind w:left="720"/></w:pPr><w:rPr><w:i/></w:rPr></w:style>'
             . '<w:style w:type="paragraph" w:styleId="Code"><w:name w:val="Code"/><w:basedOn w:val="Normal"/><w:rPr><w:rFonts w:ascii="Consolas" w:hAnsi="Consolas" w:cs="Consolas"/><w:sz w:val="20"/></w:rPr></w:style>'
             . '<w:style w:type="paragraph" w:styleId="ListParagraph"><w:name w:val="List Paragraph"/><w:basedOn w:val="Normal"/><w:pPr><w:spacing w:after="40"/></w:pPr></w:style>'
             . '<w:style w:type="character" w:styleId="Hyperlink"><w:name w:val="Hyperlink"/><w:rPr><w:color w:val="0563C1"/><w:u w:val="single"/></w:rPr></w:style>'
             . '</w:styles>';
    }

    /**
     * @param string $text
     * @return string escaped for XML, without the characters XML 1.0 does not allow
     */
    protected static function xml( $text )
    {
        $text = preg_replace( '/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', (string)$text );
        return htmlspecialchars( (string)$text, ENT_QUOTES | ENT_XML1, 'UTF-8' );
    }

    /**
     * A ZIP archive (deflated entries) in memory.
     *
     * @param array $files name => bytes
     * @param int $time
     * @return string
     */
    public static function zip( array $files, $time )
    {
        $date = getdate( (int)$time );
        $dosTime = ( $date['hours'] << 11 ) | ( $date['minutes'] << 5 ) | intdiv( $date['seconds'], 2 );
        $dosDate = ( max( 0, $date['year'] - 1980 ) << 9 ) | ( $date['mon'] << 5 ) | $date['mday'];
        $data = '';
        $central = '';
        foreach ( $files as $name => $bytes )
        {
            $name = (string)$name;
            $deflated = gzdeflate( $bytes, 6 );
            $crc = crc32( $bytes );
            $offset = strlen( $data );
            $common = pack( 'vvvvvVVVv', 20, 0x0800, 8, $dosTime, $dosDate, $crc, strlen( $deflated ), strlen( $bytes ), strlen( $name ) );
            $data .= "PK\x03\x04" . $common . pack( 'v', 0 ) . $name . $deflated;
            $central .= "PK\x01\x02" . pack( 'v', 20 ) . $common . pack( 'vvvvVV', 0, 0, 0, 0, 0, $offset ) . $name;
        }
        return $data . $central . "PK\x05\x06" . pack( 'vvvvVVv', 0, 0, count( $files ), count( $files ), strlen( $central ), strlen( $data ), 0 );
    }

    /**
     * @param string $name the page's name
     * @param string $format md or docx
     * @return string a file name: letters, digits and dashes
     */
    public static function fileName( $name, $format )
    {
        $base = trim( preg_replace( '/[^\p{L}\p{N}]+/u', '-', (string)$name ), '-' );
        $base = $base === '' ? 'page' : mb_substr( $base, 0, 80 );
        return $base . '.' . self::FORMATS[$format]['extension'];
    }
}
