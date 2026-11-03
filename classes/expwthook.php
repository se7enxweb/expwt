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
        // a visitor who is not signed in: only by ShowToAnonymous (enabled, or policy with expwt/use for Anonymous)
        if ( !empty( $c['anonymous'] ) ? ( empty( $c['show_to_anonymous'] ) || $c['show_to_anonymous'] === 'disabled' || empty( $c['may_use'] ) ) : empty( $c['may_use'] ) )
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
        // not into a field's editor in the sidebar's frame (expwt/field)
        if ( $markup === '' || !is_string( $html ) || strpos( $html, '<' . self::TAG ) !== false || strpos( $html, '<meta name="expwt" content="field"' ) !== false )
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
        // not on this siteaccess (an administration without ShowInAdmin=enabled, SiteAccessList[], SiteAccessExcludeList[]): off
        $settings['enabled'] = expwtSettings::onHere( $settings ) && !self::inFrame();
        $user = eZUser::currentUser();
        $anonymous = !$user->isRegistered();
        $mayUse = false;
        if ( !$anonymous )
        {
            $access = $user->hasAccessTo( 'expwt', 'use' );
            $mayUse = isset( $access['accessWord'] ) && $access['accessWord'] !== 'no';
        }
        else if ( $settings['show_to_anonymous'] !== 'disabled' )
        {
            // policy: the Anonymous user's roles decide, as for anyone else (expwt/use); enabled: every visitor
            $access = $settings['show_to_anonymous'] === 'policy' ? $user->hasAccessTo( 'expwt', 'use' ) : array();
            $mayUse = expwtSettings::anonymousMayUse( $settings['show_to_anonymous'], isset( $access['accessWord'] ) ? $access['accessWord'] : 'no' );
        }
        $script = '';
        $css = '';
        $panels = '';
        if ( $settings['enabled'] && $mayUse && class_exists( 'ezjscPacker' ) )
        {
            $script = self::packed( 'js', array( 'expwt.js' ) );
            $css = self::packed( 'css', array( 'expwt.css' ) );
            $panels = $anonymous ? '' : self::packed( 'js', array( 'expwt-panels.js' ) );
        }
        $url = function ( $path ) { eZURI::transformURI( $path ); return $path; };
        $context = array(
            'enabled' => $settings['enabled'], 'anonymous' => $anonymous, 'show_to_anonymous' => $settings['show_to_anonymous'],
            'may_use' => $mayUse, 'script' => $script,
            'config' => array(
                'anonymous' => $anonymous, 'node' => (int)$nodeId, 'css' => $css, 'panels' => $panels,
                'boot' => $anonymous ? '' : $url( '/expwt/api' ),
                'edge' => $settings['default_edge'], 'align' => $settings['default_align'], 'width' => $settings['default_width'],
                'minWidth' => $settings['min_width'], 'maxWidth' => $settings['max_width'],
                'height' => $settings['default_height'], 'minHeight' => $settings['min_height'], 'maxHeight' => $settings['max_height'],
                'overlayBelow' => $settings['overlay_below_width'], 'key' => $settings['shortcut_key'],
                'labels' => $settings['tab_labels'], 'icons' => self::iconBase( eZSys::rootDir(), eZSys::wwwDir() ),
                'header' => $settings['header_selector'], 'footer' => $settings['footer_selector'],
                'login' => $url( '/user/login' ), 'register' => $url( '/user/register' ), 'search' => $url( '/content/search' ),
                'strings' => expwtStrings::tab(),
            ),
        );
        // E10: the siteaccess's name, logo and accent (expwt.ini [BrandingSettings]); not one byte for the sidebar's own look
        $brand = class_exists( 'expwtBranding' ) ? expwtBranding::client( expwtBranding::fromIni(), eZSys::wwwDir() ) : array();
        if ( $brand )
            $context['config']['brand'] = $brand;
        return $context;
    }

    /**
     * Where the kernel's Bootstrap Icons are (share/icons/bootstrap-icons, the set of its bootstrap-icons icon theme),
     * as the address of their directory; '' on a kernel without them, whose tabs then show their names.
     *
     * @param string $rootDir eZSys::rootDir()
     * @param string $wwwDir eZSys::wwwDir(): '' or the site's subdirectory
     * @return string
     */
    public static function iconBase( $rootDir, $wwwDir )
    {
        if ( !is_dir( rtrim( (string)$rootDir, '/' ) . '/share/icons/bootstrap-icons/svg' ) )
            return '';
        return rtrim( (string)$wwwDir, '/' ) . '/share/icons/bootstrap-icons/svg/';
    }

    /** @return bool the page is shown in the sidebar's frame (?in=sidebar: a field's editor, its browse): no sidebar in it */
    public static function inFrame()
    {
        return isset( $_GET['in'] ) && $_GET['in'] === 'sidebar';
    }

    /** @var string|null DebugOutput as it was before a page of the frame switched it off */
    protected static $debugOutput = null;

    /**
     * @param string|null $uri the request's path (default: this request's)
     * @return bool a page of the sidebar's frame: ?in=sidebar, a field's editor (expwt/field), the small window of
     *              "Verify it's you" (expwt/reauth: the sidebar would cover the kernel's form there) or a page in the
     *              frame's layout (layout/set/expwtframe)
     */
    public static function framePage( $uri = null )
    {
        if ( $uri === null )
        {
            if ( self::inFrame() )
                return true;
            $uri = class_exists( 'eZSys' ) ? (string)eZSys::requestURI() : '';
        }
        $path = '/' . ltrim( (string)parse_url( (string)$uri, PHP_URL_PATH ), '/' );
        if ( strpos( (string)$uri, 'in=sidebar' ) !== false )
            return true;
        return (bool)preg_match( '#(^|/)(expwt/field|expwt/reauth|layout/set/expwtframe)(/|$)#', $path );
    }

    /**
     * response/output: puts DebugOutput back after a page of the frame.
     *
     * @param string|null $html
     * @return string|null
     */
    public static function output( $html )
    {
        if ( self::$debugOutput !== null )
        {
            eZINI::instance()->setVariable( 'DebugSettings', 'DebugOutput', self::$debugOutput );
            self::$debugOutput = null;
        }
        return $html;
    }

    /** @return string the first URL of the packed files ('' when the packer has none) */
    public static function packed( $type, array $files )
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
            // a page of the sidebar's frame shows no debug report (it would fill a narrow frame); the outer page keeps
            // it. eZDisplayDebug() reads the setting after this filter; output() puts it back for the next request
            // (a Velocity worker keeps its INI objects).
            if ( self::framePage() )
            {
                $ini = eZINI::instance();
                if ( self::$debugOutput === null )
                    self::$debugOutput = (string)$ini->variable( 'DebugSettings', 'DebugOutput' );
                $ini->setVariable( 'DebugSettings', 'DebugOutput', 'disabled' );
                return $html;
            }
            if ( !is_string( $html ) || stripos( $html, '</body>' ) === false )
                return $html;
            if ( strpos( $html, '<' . self::TAG ) === false )
            {
                $settings = expwtSettings::fromIni();
                // not on this siteaccess (an administration without ShowInAdmin=enabled, SiteAccessList[], SiteAccessExcludeList[]): off
                $settings['enabled'] = expwtSettings::onHere( $settings ) && !self::inFrame();
                if ( !$settings['enabled'] || !$settings['inject_without_hook'] )
                    return $html;
                $html = self::inject( $html, self::markup( self::context( self::currentNodeId() ) ) );
            }
            // E12: how this page was rendered, on the sidebar's element, for a user with expwt/performance only
            return class_exists( 'expwtPerformance' ) ? expwtPerformance::preOutput( $html ) : $html;
        }
        catch ( Exception $e )
        {
            eZDebug::writeError( $e->getMessage(), __METHOD__ );
            return $html;
        }
    }
}
