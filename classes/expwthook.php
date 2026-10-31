<?php
/**
 * The sidebar's place in the page: one custom element and one deferred script before </body>, or nothing.
 *
 * The kernel's hook design:pagelayout/hooks/body_end.tpl (one line in a pagelayout of every engine: {include} in .tpl,
 * {expinclude} in .stpl and .twig) includes expwt/hook.tpl, which prints markup(). A pagelayout without the hook gets
 * the same markup from the response filter preOutput() (expwt.ini InjectWithoutHook).
 *
 * What the markup depends on is what the pagelayout's cache blocks and the full-page cache already key on: the
 * siteaccess, the node, whether the visitor is signed in, and their roles (expwt/use). Nothing about the user
 * themselves is in it (no name, no token, no state): those come with the one deferred request the script makes
 * after the page has loaded (expwt/api boot), so a page stays cacheable for everybody with the same roles
 * (decision 34). Without the sidebar (switched off, or anonymous while ShowToAnonymous is disabled, or no expwt/use)
 * the markup is empty: not one byte and not one request (decision F12).
 *
 * markup() and inject() are pure.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

class expwtHook
{
    /** The custom element's tag (expwt.js defines it) */
    const TAG = 'exp-wt';

    /**
     * @param array $c enabled, anonymous, show_to_anonymous, may_use, script, config (array for data-config)
     * @return string
     */
    public static function markup( array $c )
    {
        if ( empty( $c['enabled'] ) || empty( $c['script'] ) )
            return '';
        if ( !empty( $c['anonymous'] ) ? empty( $c['show_to_anonymous'] ) : empty( $c['may_use'] ) )
            return '';
        $config = htmlspecialchars( json_encode( $c['config'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ), ENT_QUOTES, 'UTF-8' );
        return '<' . self::TAG . ' data-config="' . $config . '"></' . self::TAG . '>'
             . '<script src="' . htmlspecialchars( $c['script'], ENT_QUOTES, 'UTF-8' ) . '" defer></script>';
    }

    /**
     * @param string $html the page
     * @param string $markup
     * @return string the page with the markup before its last </body>, unchanged when it has the sidebar already,
     *                has no </body> (not a page: JSON, a fragment) or the markup is empty
     */
    public static function inject( $html, $markup )
    {
        if ( $markup === '' || !is_string( $html ) || strpos( $html, '<' . self::TAG ) !== false )
            return $html;
        $pos = strripos( $html, '</body>' );
        return $pos === false ? $html : substr( $html, 0, $pos ) . $markup . substr( $html, $pos );
    }

    /**
     * The context of markup() for this request.
     *
     * @param int $nodeId the page's node (0: not a content page)
     * @return array
     */
    public static function context( $nodeId = 0 )
    {
        $settings = expwtSettings::fromIni();
        $user = eZUser::currentUser();
        $anonymous = !$user->isRegistered();
        $mayUse = false;
        if ( !$anonymous )
        {
            $access = $user->hasAccessTo( 'expwt', 'use' );
            $mayUse = isset( $access['accessWord'] ) && $access['accessWord'] !== 'no';
        }
        $script = '';
        $css = '';
        $panels = '';
        if ( $settings['enabled'] && ( $anonymous ? $settings['show_to_anonymous'] : $mayUse ) && class_exists( 'ezjscPacker' ) )
        {
            $script = self::packed( 'js', array( 'expwt.js' ) );
            $css = self::packed( 'css', array( 'expwt.css' ) );
            $panels = $anonymous ? '' : self::packed( 'js', array( 'expwt-panels.js' ) );
        }
        $url = function ( $path ) { eZURI::transformURI( $path ); return $path; };
        return array(
            'enabled' => $settings['enabled'], 'anonymous' => $anonymous, 'show_to_anonymous' => $settings['show_to_anonymous'],
            'may_use' => $mayUse, 'script' => $script,
            'config' => array(
                'anonymous' => $anonymous, 'node' => (int)$nodeId, 'css' => $css, 'panels' => $panels,
                'boot' => $anonymous ? '' : $url( '/expwt/api' ),
                'side' => $settings['default_side'], 'width' => $settings['default_width'],
                'minWidth' => $settings['min_width'], 'maxWidth' => $settings['max_width'],
                'overlayBelow' => $settings['overlay_below_width'], 'key' => $settings['shortcut_key'],
                'header' => $settings['header_selector'],
                'login' => $url( '/user/login' ), 'register' => $url( '/user/register' ), 'search' => $url( '/content/search' ),
                'strings' => expwtStrings::tab(),
            ),
        );
    }

    /** @return string the first URL of the packed files ('' when the packer has none) */
    protected static function packed( $type, array $files )
    {
        $urls = $type === 'css' ? ezjscPacker::buildStylesheetFiles( $files, 3 ) : ezjscPacker::buildJavascriptFiles( $files, 2 );
        return is_array( $urls ) && $urls ? (string)reset( $urls ) : '';
    }

    /** @return int the node of the page being rendered (the pagelayout's $module_result), 0 for none */
    public static function currentNodeId()
    {
        $tpl = eZTemplate::factory();
        if ( !$tpl->hasVariable( 'module_result' ) )
            return 0;
        $result = $tpl->variable( 'module_result' );
        return is_array( $result ) && isset( $result['content_info']['node_id'] ) ? (int)$result['content_info']['node_id'] : 0;
    }

    /**
     * response/preoutput: the sidebar for a pagelayout without the hook.
     *
     * @param string|null $html
     * @return string|null
     */
    public static function preOutput( $html )
    {
        try
        {
            if ( !is_string( $html ) || stripos( $html, '</body>' ) === false || strpos( $html, '<' . self::TAG ) !== false )
                return $html;
            $settings = expwtSettings::fromIni();
            if ( !$settings['enabled'] || !$settings['inject_without_hook'] )
                return $html;
            return self::inject( $html, self::markup( self::context( self::currentNodeId() ) ) );
        }
        catch ( Exception $e )
        {
            eZDebug::writeError( $e->getMessage(), __METHOD__ );
            return $html;
        }
    }
}
