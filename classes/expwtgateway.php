<?php
/**
 * What the sidebar's actions do on the installation: the current user, their preferences, and the content
 * operations through the kernel (expwtWriter, the kernel's operations content/publish, content/move, content/hide,
 * content/remove, content/updatepriority), each with the user's rights checked by the kernel. expwtApi calls it;
 * a test hands expwtApi a subclass that answers from arrays.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

class expwtGateway
{
    /** @var array expwtSettings */
    protected $settings;

    /** @var array|null */
    protected $capabilities = null;

    /** @var expwtWriter|null */
    protected $writer = null;

    public function __construct( array $settings )
    {
        $this->settings = $settings;
    }

    // ------------------------------------------------------------------------------------------------ the user

    /** @return eZUser */
    protected function currentUser()
    {
        return eZUser::currentUser();
    }

    /** @return int */
    protected function userId()
    {
        return (int)$this->currentUser()->attribute( 'contentobject_id' );
    }

    /** @return array function => bool (expwtAccess::capabilities()) */
    public function capabilities()
    {
        if ( $this->capabilities === null )
        {
            $user = $this->currentUser();
            $this->capabilities = expwtAccess::capabilities( function ( $function ) use ( $user ) {
                if ( !$user->isRegistered() )
                    return false;
                $access = $user->hasAccessTo( 'expwt', $function );
                return isset( $access['accessWord'] ) && $access['accessWord'] !== 'no';
            } );
        }
        return $this->capabilities;
    }

    /** @return array id, login, name */
    public function user()
    {
        $user = $this->currentUser();
        $object = $user->contentObject();
        return array( 'id' => (int)$user->attribute( 'contentobject_id' ), 'login' => (string)$user->attribute( 'login' ),
                      'name' => $object instanceof eZContentObject ? (string)$object->attribute( 'name' ) : (string)$user->attribute( 'login' ) );
    }

    /** @return array the user's sidebar (expwtState) */
    public function state()
    {
        return expwtState::decode( eZPreferences::value( expwtState::PREFERENCE ), $this->settings );
    }

    public function saveState( array $state )
    {
        eZPreferences::setValue( expwtState::PREFERENCE, json_encode( $state ) );
    }

    /** @return string|null the user's light or dark choice for the administration */
    public function adminColorMode()
    {
        $value = eZPreferences::value( 'admin_color_mode' );
        return is_string( $value ) && $value !== '' ? $value : null;
    }

    /** @return string the CSRF token the writes carry (ezformtoken), '' without it */
    public function token()
    {
        return class_exists( 'ezxFormToken' ) && ezxFormToken::isEnabled() ? (string)ezxFormToken::getToken() : '';
    }

    /** @return array layer and the addresses of both transports */
    public function transport()
    {
        $graphql = $this->settings['data_layer'] === 'graphql' && class_exists( 'expGraphQL' ) && expGraphQL::isEnabled()
                   && expGraphQLMutations::enabled() && $this->may( 'graphql', 'query' );
        return array( 'layer' => $graphql ? 'graphql' : 'rest', 'rest' => self::url( '/expwt/api' ),
                      'graphql' => self::url( '/graphql/query' ), 'reauth' => self::url( '/expwt/reauth' ) );
    }

    /** @return array the addresses of the kernel's own views the sidebar opens */
    public function urls()
    {
        return array( 'logout' => self::url( '/user/logout' ), 'admin' => self::adminUrl() );
    }

    /** @return array source => translation, the panels' strings in the user's language */
    public function strings()
    {
        return expwtStrings::all();
    }

    /**
     * The Me panel (decision 9): the account at a glance and the kernel's own views for each part of it (no copy of
     * any of them: the profile, password, passkeys, sessions, notification and preference pages stay the kernel's).
     */
    public function me()
    {
        $user = $this->currentUser();
        $object = $user->contentObject();
        $links = array();
        foreach ( array( 'account' => array( '/user/edit', 'user', 'selfedit' ), 'password' => array( '/user/password', 'user', 'password' ),
                         'passkeys' => array( '/user/passkeys', 'user', 'passkeys' ), 'sessions' => array( '/user/sessions', 'user', 'sessions' ),
                         'security' => array( '/user/security', 'user', 'security' ),
                         'notifications' => array( '/notification/settings', 'notification', 'use' ),
                         'drafts' => array( '/content/draft', 'content', 'read' ), 'bookmarks' => array( '/content/bookmark', 'content', 'bookmark' ) ) as $name => $view )
        {
            if ( $this->viewExists( $view[0] ) )
                $links[$name] = self::url( $view[0] );
        }
        $lastVisit = method_exists( $user, 'lastVisit' ) ? (int)$user->lastVisit() : 0;
        return array( 'name' => $object instanceof eZContentObject ? (string)$object->attribute( 'name' ) : (string)$user->attribute( 'login' ),
                      'login' => (string)$user->attribute( 'login' ), 'email' => (string)$user->attribute( 'email' ),
                      'lastVisit' => $lastVisit, 'links' => $links, 'logout' => self::url( '/user/logout' ) );
    }

    // ------------------------------------------------------------------------------------------------ the page

    /**
     * The page the sidebar stands on (nodeId), or a draft of a new object (objectId and version): its fields in the
     * version the user edits (their own draft in the language if there is one, else the published version), what
     * the user may do with it, and who else is editing it.
     */
    public function page( array $in )
    {
        $node = null;
        if ( isset( $in['nodeId'] ) )
        {
            $node = eZContentObjectTreeNode::fetch( $in['nodeId'] );
            if ( !$node instanceof eZContentObjectTreeNode || !$node->canRead() )
                throw new expwtApiError( 404, 'not_found', 'This page is not there, or you may not read it.' );
            $object = $node->object();
        }
        else if ( isset( $in['objectId'] ) )
            $object = eZContentObject::fetch( $in['objectId'] );
        else
            throw new expwtApiError( 400, 'bad_input', 'Which page?' );
        if ( !$object instanceof eZContentObject || ( $node === null && !$this->ownDraft( $object, isset( $in['version'] ) ? $in['version'] : 0 ) && !$object->canRead() ) )
            throw new expwtApiError( 404, 'not_found', 'This page is not there, or you may not read it.' );

        $language = isset( $in['language'] ) ? $in['language'] : (string)$object->attribute( 'initial_language_code' );
        if ( $node && !isset( $in['language'] ) && method_exists( $node, 'currentLanguage' ) && $node->currentLanguage() )
            $language = (string)$node->currentLanguage();
        $published = (int)$object->attribute( 'current_version' );
        $draft = isset( $in['version'] ) ? $this->ownDraft( $object, $in['version'] ) : $this->latestOwnDraft( $object, $language );
        $version = $draft ?: $object->version( $published );
        $id = (int)$object->attribute( 'id' );
        $fields = $version instanceof eZContentObjectVersion ? $this->fieldsOf( $version, $language ) : array();
        // the date fields of the schedule are edited as "Publish at" and "Take down at", not twice
        $schedule = self::scheduleView( $object, $version );
        $scheduled = array();
        foreach ( $schedule as $field )
            if ( $field )
                $scheduled[] = $field['identifier'];
        $fields = array_values( array_filter( $fields, function ( $f ) use ( $scheduled ) { return !in_array( $f['identifier'], $scheduled, true ); } ) );
        $lock = expwtLock::heldByOther( expwtLock::load( $id ), $this->userId(), time() );
        $versionNumber = $version instanceof eZContentObjectVersion ? (int)$version->attribute( 'version' ) : $published;
        return array(
            'nodeId' => $node ? (int)$node->attribute( 'node_id' ) : null,
            'parentId' => $node ? (int)$node->attribute( 'parent_node_id' ) : null,
            'objectId' => $id,
            'name' => (string)$object->attribute( 'name' ),
            'class' => array( 'identifier' => (string)$object->attribute( 'class_identifier' ), 'name' => (string)$object->attribute( 'class_name' ) ),
            'language' => $language,
            'publishedVersion' => (int)$object->attribute( 'status' ) === eZContentObject::STATUS_PUBLISHED ? $published : null,
            'draft' => $draft ? array( 'version' => (int)$draft->attribute( 'version' ), 'modified' => (int)$draft->attribute( 'modified' ) ) : null,
            'hidden' => $node ? (bool)$node->attribute( 'is_hidden' ) : false,
            'fields' => $fields,
            'categories' => self::categories(),
            'schedule' => $schedule,
            // what Exponential Intelligence offers for the fields of this page (E1), null where it is not there
            'ai' => $this->aiOffer(),
            // the focal point and crop of image fields (E7): only with a kernel that stores them
            'images' => expwtImages::available(),
            'can' => array(
                'edit' => (bool)$object->canEdit( false, false, false, $language ),
                'create' => $node ? (bool)$node->canCreate() : false,
                'hide' => $node ? (bool)$node->canHide() : false,
                'remove' => $node ? (bool)$node->canRemove() : false,
                'move' => $node ? (bool)$node->canMoveFrom() : false,
                // a copy: the page is read; where it may go (create of its class) is checked on the place chosen
                'copy' => $node ? (bool)$object->canRead() : false,
            ),
            'lock' => $lock ? array( 'name' => (string)$lock['name'], 'since' => (int)$lock['since'] ) : null,
            'urls' => array(
                'view' => $node ? self::url( '/' . ltrim( (string)$node->attribute( 'url_alias' ), '/' ) ) : null,
                'preview' => self::url( '/content/versionview/' . $id . '/' . $versionNumber . '/' . $language ),
                'fullEdit' => self::url( '/content/edit/' . $id . '/' . ( $draft ? $versionNumber : 'f' ) . '/' . $language ),
            ),
        );
    }

    /**
     * @return array the fields of a version as the sidebar edits them: identifier, name, datatype, input, inline,
     *               editable, required, value (the string form; HTML for rich text), options (a selection)
     */
    protected function fieldsOf( eZContentObjectVersion $version, $language )
    {
        $out = array();
        foreach ( (array)$version->contentObjectAttributes( $language ) as $attribute )
        {
            $classAttribute = $attribute->contentClassAttribute();
            $datatype = (string)$attribute->attribute( 'data_type_string' );
            $multiple = false;
            $options = null;
            if ( $datatype === 'ezselection' && $classAttribute )
            {
                $content = $classAttribute->content();
                $multiple = !empty( $content['is_multiselect'] );
                $options = array();
                foreach ( isset( $content['options'] ) ? (array)$content['options'] : array() as $option )
                    $options[] = (string)$option['name'];
            }
            $kind = expwtFields::describe( $datatype, $multiple );
            if ( $kind['input'] === 'hidden' )
                continue;
            $shown = expwtFieldValue::of( $attribute, $kind['input'] );
            $out[] = array(
                'identifier' => (string)$attribute->attribute( 'contentclass_attribute_identifier' ),
                'attribute' => (int)$attribute->attribute( 'id' ),
                'name' => self::fieldName( $classAttribute, (string)$attribute->attribute( 'contentclass_attribute_identifier' ) ),
                // the attribute's category (content.ini [ClassAttributeSettings]): the Page panel groups the fields of
                // every category but the default one at its end, as content/edit does
                'category' => self::categoryOf( $classAttribute ),
                'datatype' => $datatype,
                'required' => $classAttribute ? (bool)$classAttribute->attribute( 'is_required' ) : false,
                'value' => $shown['value'],
                'options' => $options,
                'names' => $shown['names'],
            ) + $kind;
        }
        return $out;
    }


    // ------------------------------------------------------------------------------- what extensions add (32)

    /** @var array|null */
    protected $registryAll = null;

    /** @var array|null */
    protected $registryMine = null;

    /** @return array every action the extensions registered (expwtRegistry), whoever may call it */
    public function registeredActions()
    {
        if ( $this->registryAll === null )
            $this->registryAll = expwtRegistry::fromIni();
        return $this->registryAll['actions'];
    }

    /** @return array the registry's entries the user gets (their expwt function and kernel policies) */
    public function registry()
    {
        if ( $this->registryMine === null )
        {
            $this->registeredActions();
            $self = $this;
            $this->registryMine = expwtRegistry::forUser( $this->registryAll, $this->capabilities(),
                                                          function ( $module, $function ) use ( $self ) { return $self->mayDo( $module, $function ); } );
        }
        return $this->registryMine;
    }

    /** @return array the registry as the boot answer has it: labels translated, scripts and stylesheets as addresses */
    public function registryForBoot()
    {
        return expwtRegistry::forBoot( $this->registry(),
            function ( $context, $source ) { return ezpI18n::tr( $context, $source ); },
            function ( $type, $file ) { return class_exists( 'ezjscPacker' ) ? expwtHook::packed( $type, array( $file ) ) : ''; } );
    }

    /**
     * The panel order and visibility of the user's roles on this siteaccess (E9): null for a visitor who is not
     * signed in (the Welcome panel only) and for a user none of whose roles has its own panels.
     *
     * @return array|null order, hidden (expwtRolePanels::forCurrentUser())
     */
    public function panelRule()
    {
        $user = $this->currentUser();
        if ( empty( $this->capabilities()['use'] ) || !$user->isRegistered() )
            return null;
        return expwtRolePanels::forCurrentUser( $user );
    }

    /** @return bool the current user has the kernel policy module/function (for the registry and extensions) */
    public function mayDo( $module, $function )
    {
        return $this->may( $module, $function );
    }

    // ------------------------------------------------------------------------------------------- insights (F25)

    /**
     * Read-only insights into the page from the kernel's own statistics, no third-party tracking: its views (the
     * view counter the cronjob updateviewcount fills from the access log; collected false when it holds nothing) and
     * the searches on this site (the kernel's search log) for a word of the page's name. The kernel records no
     * referrers: referrers false says so.
     */
    public function insights( array $in )
    {
        $node = isset( $in['nodeId'] ) ? eZContentObjectTreeNode::fetch( (int)$in['nodeId'] ) : null;
        if ( !$node instanceof eZContentObjectTreeNode || !$node->canRead() )
            throw new expwtApiError( 404, 'not_found', 'This page is not there, or you may not read it.' );
        $counter = eZViewCounter::fetch( (int)$node->attribute( 'node_id' ) );
        $collected = $counter instanceof eZViewCounter || (int)eZPersistentObject::count( eZViewCounter::definition() ) > 0;
        $phrases = eZSearchLog::mostFrequentPhraseArray( array( 'offset' => 0, 'limit' => 500 ) );
        $object = $node->object();
        return array(
            'nodeId' => (int)$node->attribute( 'node_id' ),
            'name' => (string)$node->attribute( 'name' ),
            'published' => (int)$object->attribute( 'published' ),
            'modified' => (int)$object->attribute( 'modified' ),
            'views' => array( 'collected' => $collected, 'count' => $counter instanceof eZViewCounter ? (int)$counter->attribute( 'count' ) : 0 ),
            'searches' => self::searchesFor( (string)$node->attribute( 'name' ), is_array( $phrases ) ? $phrases : array() ),
            'referrers' => false,
        );
    }

    /**
     * @param string $name the page's name
     * @param array $phrases the search log (phrase, phrase_count, result_count), most frequent first
     * @return array the phrases with a word of the name (3 letters or more), at most 10: phrase, searches, results
     */
    public static function searchesFor( $name, array $phrases )
    {
        $words = array();
        foreach ( preg_split( '/[^\p{L}\p{N}]+/u', mb_strtolower( (string)$name, 'UTF-8' ), -1, PREG_SPLIT_NO_EMPTY ) as $word )
            if ( mb_strlen( $word, 'UTF-8' ) >= 3 )
                $words[$word] = true;
        $out = array();
        foreach ( $phrases as $p )
        {
            $terms = preg_split( '/[^\p{L}\p{N}]+/u', mb_strtolower( (string)$p['phrase'], 'UTF-8' ), -1, PREG_SPLIT_NO_EMPTY );
            if ( !array_intersect_key( array_flip( $terms ), $words ) )
                continue;
            $out[] = array( 'phrase' => (string)$p['phrase'], 'searches' => (int)$p['phrase_count'], 'results' => (int)round( (float)$p['result_count'] ) );
            if ( count( $out ) >= 10 )
                break;
        }
        return $out;
    }

    // ------------------------------------------------------------------------------------------- SEO check (F26)

    /** The most links of one page the check looks at */
    const SEO_MAX_LINKS = 200;

    /**
     * The server's half of the SEO check: whether each internal link of the rendered page leads somewhere, and which
     * fields of the page a finding opens (its title field, its metadata field). The checks of the rendered page
     * itself (title, description, headings, alternative texts, canonical, robots) are made in the browser.
     *
     * A link is looked up as the kernel would answer it, without a request: a view of a module (content/view/full/<n>
     * only when that node is there), a URL alias (also one that redirects), a wildcard, or a file of the storage or a
     * design. A link of another siteaccess, or to a file elsewhere, is not checked (skipped).
     */
    public function seoCheck( array $in )
    {
        $node = isset( $in['nodeId'] ) ? eZContentObjectTreeNode::fetch( (int)$in['nodeId'] ) : null;
        if ( !$node instanceof eZContentObjectTreeNode || !$node->canRead() )
            throw new expwtApiError( 404, 'not_found', 'This page is not there, or you may not read it.' );
        $object = $node->object();
        $class = $object->contentClass();
        $meta = null;
        $images = array();
        foreach ( (array)$object->contentObjectAttributes() as $attribute )
        {
            $type = (string)$attribute->attribute( 'data_type_string' );
            if ( $type === 'xrowmetadata' && $meta === null )
                $meta = (string)$attribute->attribute( 'contentclass_attribute_identifier' );
            if ( $type === 'ezimage' )
                $images[] = (string)$attribute->attribute( 'contentclass_attribute_identifier' );
        }
        $prefix = rtrim( self::url( '/' ), '/' );
        $access = eZSiteAccess::current();
        $here = is_array( $access ) && isset( $access['name'] ) ? (string)$access['name'] : '';
        $ini = eZINI::instance();
        $others = array_values( array_diff( (array)$ini->variable( 'SiteAccessSettings', 'AvailableSiteAccessList' ), array( $here ) ) );
        $links = array();
        $paths = isset( $in['paths'] ) && is_array( $in['paths'] ) ? array_slice( array_values( $in['paths'] ), 0, self::SEO_MAX_LINKS ) : array();
        foreach ( $paths as $path )
        {
            if ( !is_string( $path ) || strlen( $path ) > 1000 )
                continue;
            $local = self::linkPath( $path, $prefix, $others );
            $links[] = array( 'path' => $path, 'status' => $local === null ? 'skipped' : self::linkStatus( $local ) );
        }
        return array(
            'nodeId' => (int)$node->attribute( 'node_id' ),
            'fields' => array( 'title' => $class instanceof eZContentClass ? self::titleField( $class ) : null, 'meta' => $meta, 'images' => $images ),
            'links' => $links,
        );
    }

    /**
     * A link's path inside this siteaccess, as the kernel looks it up: the siteaccess's prefix taken off, decoded,
     * without the query, the anchor and the slashes at its ends. Pure.
     *
     * @param string $path the link's path as the browser has it (/smarty/news/a-page?x=1)
     * @param string $prefix this siteaccess's prefix ('' or /smarty)
     * @param string[] $others the names of the other siteaccesses
     * @return string|null null for a link this siteaccess does not answer (another siteaccess's, not a path)
     */
    public static function linkPath( $path, $prefix, array $others )
    {
        $path = (string)$path;
        if ( $path === '' || $path[0] !== '/' || strpos( $path, '//' ) === 0 )
            return null;
        $path = preg_replace( '/[?#].*$/s', '', $path );
        if ( $prefix !== '' )
        {
            if ( $path !== $prefix && strpos( $path, $prefix . '/' ) !== 0 )
                return null;
            $path = (string)substr( $path, strlen( $prefix ) );
        }
        $path = trim( rawurldecode( $path ), '/' );
        if ( preg_match( '#^index\.php(/|$)#', $path ) )
            $path = trim( (string)substr( $path, 9 ), '/' );
        $first = strtok( $path, '/' );
        if ( $first !== false && in_array( $first, $others, true ) )
            return null;
        if ( strpos( '/' . $path . '/', '/../' ) !== false || strpos( $path, "\0" ) !== false )
            return null;
        return $path;
    }

    /** @return string ok, broken or skipped: what the kernel answers for a path of this siteaccess (linkPath()) */
    protected static function linkStatus( $path )
    {
        if ( $path === '' )
            return 'ok';
        // a file: of the storage and the designs only (the rest of the installation's files are no one's business)
        if ( preg_match( '#^(var/([^/]+/)?storage|design|extension/[^/]+/design)/#', $path ) )
            return is_file( eZSys::rootDir() . '/' . $path ) ? 'ok' : 'broken';
        if ( preg_match( '#\.[a-z0-9]{2,5}$#i', $path ) && !preg_match( '#\.html?$#i', $path ) )
            return 'skipped';
        $elements = explode( '/', $path );
        if ( eZModule::exists( $elements[0] ) instanceof eZModule )
        {
            if ( $elements[0] === 'content' && isset( $elements[1], $elements[3] ) && $elements[1] === 'view' && ctype_digit( $elements[3] ) )
                return eZContentObjectTreeNode::fetch( (int)$elements[3] ) instanceof eZContentObjectTreeNode ? 'ok' : 'broken';
            return 'ok';
        }
        $uri = $path;
        if ( eZURLAliasML::translate( $uri ) )
            return 'ok';
        $uri = eZURI::instance( $path );
        if ( class_exists( 'eZURLWildcard' ) && eZURLWildcard::translate( $uri ) )
            return 'ok';
        return 'broken';
    }

    // --------------------------------------------------------------------- other siteaccesses and languages (F27)

    /**
     * The page on the other sites and in its other languages: the sites are those of the kernel's own site menu
     * (ezpSiteAccessMenu, the admin4 globe: RelatedSiteAccessList or AvailableSiteAccessList, no administration, only
     * where the user may log in), each with the kernel's language switcher address of this page there
     * (switchlanguage/to, which finds the page's address in that site's languages); the translations are the page's
     * languages with the kernel's preview of each, and the installation's other languages the user may translate the
     * page into, with the full editor's address for it.
     */
    public function elsewhere( array $in )
    {
        $node = isset( $in['nodeId'] ) ? eZContentObjectTreeNode::fetch( (int)$in['nodeId'] ) : null;
        if ( !$node instanceof eZContentObjectTreeNode || !$node->canRead() )
            throw new expwtApiError( 404, 'not_found', 'This page is not there, or you may not read it.' );
        $object = $node->object();
        $have = (array)$object->availableLanguages();
        $always = (bool)$object->attribute( 'always_available' );
        $alias = ltrim( (string)$node->attribute( 'url_alias' ), '/' );
        $languages = array();
        foreach ( (array)eZContentLanguage::fetchList() as $language )
            $languages[(string)$language->attribute( 'locale' )] = (string)$language->attribute( 'name' );
        $sites = array();
        $menu = class_exists( 'ezpSiteAccessMenu' ) ? self::siteMenu( $this->currentUser() ) : array();
        foreach ( $menu as $item )
        {
            $sites[] = array(
                'identifier' => $item['identifier'],
                'name' => $item['name'],
                'locale' => $item['locale'],
                'language' => isset( $languages[$item['locale']] ) ? $languages[$item['locale']] : $item['locale'],
                'current' => (bool)$item['current'],
                // the page is shown there: translated into the site's language, or always available
                'shown' => $always || in_array( $item['locale'], $have, true ),
                'url' => $item['current'] ? self::url( '/' . $alias ) : self::url( '/switchlanguage/to/' . $item['identifier'] . '/' . $alias ),
            );
        }
        $published = (int)$object->attribute( 'current_version' );
        $id = (int)$object->attribute( 'id' );
        $main = (string)$object->attribute( 'initial_language_code' );
        $translations = array();
        foreach ( $languages as $locale => $name )
        {
            $exists = in_array( $locale, $have, true );
            if ( !$exists && !$object->canEdit( false, false, false, $locale ) )
                continue;
            $translations[] = array(
                'locale' => $locale,
                'name' => $name,
                'exists' => $exists,
                'main' => $locale === $main,
                'preview' => $exists ? self::url( '/content/versionview/' . $id . '/' . $published . '/' . $locale ) : null,
                'translate' => !$exists ? self::url( '/content/edit/' . $id . '/f/' . $locale . '/' . $main ) : null,
                'editable' => $exists && (bool)$object->canEdit( false, false, false, $locale ),
            );
        }
        return array( 'nodeId' => (int)$node->attribute( 'node_id' ), 'sites' => $sites, 'translations' => $translations );
    }

    /** @return array the items of the kernel's site menu for the user, the current site first, administrations left out */
    protected static function siteMenu( eZUser $user )
    {
        $menu = ezpSiteAccessMenu::current( $user );
        $items = array();
        if ( !empty( $menu['front'] ) )
            $items[] = $menu['front'];
        foreach ( (array)$menu['groups'] as $group )
            if ( $group['identifier'] === 'site' )
                $items = array_merge( $items, (array)$group['items'] );
        $current = eZSiteAccess::current();
        $here = is_array( $current ) && isset( $current['name'] ) ? (string)$current['name'] : '';
        $out = array();
        foreach ( $items as $item )
            if ( empty( $item['admin'] ) )
                $out[$item['identifier']] = array( 'current' => $item['identifier'] === $here ) + $item;
        // the current site is listed though the menu has it only as its front (or not at all)
        if ( $here !== '' && !isset( $out[$here] ) && class_exists( 'ezpSiteAccessMenu' ) )
        {
            $info = ezpSiteAccessMenu::siteAccessInfo( $here );
            $out = array( $here => array( 'identifier' => $here, 'name' => $info['site_name'] !== '' ? $info['site_name'] : $here,
                                          'locale' => $info['locale'], 'current' => true, 'admin' => false ) ) + $out;
        }
        uasort( $out, function ( $a, $b ) { return (int)$b['current'] - (int)$a['current']; } );
        return array_values( $out );
    }

    // ------------------------------------------------------------------------------- Exponential Intelligence (E1)

    /** The instruction of Summarize: expai's Write action with this instruction */
    const AI_SUMMARY = 'Write a short summary of the page for this field: two or three sentences in the language of the field, from the other fields of the object.';

    /** Datatypes Summarize is offered on (where expai's Write is) */
    const AI_SUMMARY_DATATYPES = array( 'ezstring', 'eztext', 'ezxmltext' );

    /** @var expAiConfig|false|null */
    protected $aiConfig = null;

    /**
     * expai where the sidebar may use it: the extension active in this siteaccess (an inactive one is never used:
     * its settings are not loaded there), switched on and configured for it, its edit form actions on, and the user
     * with expai/write, as for the field actions of the administration's edit form.
     *
     * @return expAiConfig|null
     */
    protected function ai()
    {
        if ( $this->aiConfig === null )
        {
            $this->aiConfig = false;
            if ( class_exists( 'expAiConfig' ) && class_exists( 'expAiEditForm' ) && class_exists( 'eZExtension' )
                 && in_array( 'expai', (array)eZExtension::activeExtensions(), true ) )
            {
                $config = expAiConfig::instance();
                if ( $config->isEnabled() && $config->isConfigured() && $config->flag( 'EditViewSettings', 'Enabled', true )
                     && $this->may( 'expai', 'write' ) )
                    $this->aiConfig = $config;
            }
        }
        return $this->aiConfig ?: null;
    }

    /**
     * What the Page panel offers: expai's field actions (expai.ini [EditViewSettings] FieldActions[], those that can be
     * used at all) on the datatypes each names and expai may write, Summarize (Write with a fixed instruction), and
     * the alt text of an image where expai's alt text is on; the languages Translate may go to.
     *
     * @return array|null actions (name, label, input, datatypes), languages (locale, name), or null without expai
     */
    public function aiOffer()
    {
        $config = $this->ai();
        if ( !$config )
            return null;
        $writable = $config->writableDatatypes();
        $actions = array();
        $registry = new expAiFieldActionRegistry( $config );
        foreach ( $registry->describe() as $action )
        {
            $datatypes = array_values( array_intersect( (array)$action['datatypes'], $writable ) );
            if ( $datatypes )
                $actions[] = array( 'name' => (string)$action['name'], 'label' => (string)$action['label'], 'input' => (string)$action['input'], 'datatypes' => $datatypes );
        }
        $names = array_column( $actions, 'name' );
        if ( in_array( 'write', $names, true ) )
            $actions[] = array( 'name' => 'summarize', 'label' => expwtStrings::tr( 'Summarize' ), 'input' => 'none',
                                'datatypes' => array_values( array_intersect( self::AI_SUMMARY_DATATYPES, $actions[array_search( 'write', $names, true )]['datatypes'] ) ) );
        if ( class_exists( 'expAiAltText' ) && expAiAltText::enabled( $config ) )
            $actions[] = array( 'name' => 'alttext', 'label' => expwtStrings::tr( 'Alt text' ), 'input' => 'none', 'datatypes' => array( 'ezimage' ) );
        $languages = array();
        foreach ( (array)eZContentLanguage::prioritizedLanguages() as $language )
            $languages[] = array( 'locale' => (string)$language->attribute( 'locale' ), 'name' => (string)$language->attribute( 'name' ) );
        return $actions ? array( 'actions' => $actions, 'languages' => $languages ) : null;
    }

    /**
     * Asks Exponential Intelligence for a field of the user's draft (E1): expai's field action (Write, Rewrite,
     * Shorten, Translate, Suggest ...), Summarize, or the alt text of an image. Nothing is written into the field: the
     * answer is a suggestion the user takes with ai.apply (or into the form). The draft is the user's newest in the
     * language, or a new one, as expai's edit form needs one (the request is marked in expai's draft log).
     *
     * @param array $in objectId, version (optional), language, field (identifier), action, instruction, targetLanguage,
     *                  value (the value as it is in the sidebar's form, for a text field typed into and not saved yet)
     * @return array value, note, action, field, datatype, version (the draft)
     */
    public function aiField( array $in )
    {
        $config = $this->ai();
        if ( !$config )
            throw new expwtApiError( 403, 'ai_off', 'Exponential Intelligence is not available here.' );
        list( $object, $version, $language, $attribute ) = $this->aiDraftField( $in, true );
        $action = isset( $in['action'] ) ? (string)$in['action'] : '';
        $datatype = (string)$attribute->attribute( 'data_type_string' );
        $offered = false;
        foreach ( (array)$this->aiOffer()['actions'] as $a )
            if ( $a['name'] === $action && in_array( $datatype, $a['datatypes'], true ) )
                $offered = true;
        if ( !$offered )
            throw new expwtApiError( 400, 'bad_input', 'This action is not available for this field.' );
        $versionNumber = (int)$version->attribute( 'version' );
        $context = class_exists( 'expAiAltText' ) ? expAiAltText::contextFromVersion( $object, $versionNumber, $language ) : array();
        unset( $context[(string)$attribute->attribute( 'contentclass_attribute_identifier' )] );
        try
        {
            if ( $action === 'alttext' )
            {
                $current = (string)$attribute->content()->attribute( 'alternative_text' );
                $r = expAiAltText::suggest( $config, $object, $attribute, $language, $context, $current, array( 'action' => 'expwt:alt_text' ) );
                expAiEditForm::mark( $object, $version, $language, 'field', 'alt_text', $r['model'], isset( $r['provider'] ) ? $r['provider'] : '' );
                $value = (string)$r['value'];
                $note = (string)$r['note'];
            }
            else
            {
                $value = isset( $in['value'] ) && is_string( $in['value'] ) ? $in['value']
                       : expwtFieldValue::value( $attribute, expwtFields::describe( $datatype )['input'] );
                $r = expAiEditForm::fieldAction( $config, array(
                    'object_id' => (int)$object->attribute( 'id' ), 'version' => $versionNumber, 'language' => $language,
                    'attribute_id' => (int)$attribute->attribute( 'id' ),
                    'action' => $action === 'summarize' ? 'write' : $action,
                    'value' => $value,
                    'instruction' => $action === 'summarize' ? self::AI_SUMMARY : ( isset( $in['instruction'] ) ? (string)$in['instruction'] : '' ),
                    'target_language' => isset( $in['targetLanguage'] ) ? (string)$in['targetLanguage'] : '',
                    'context' => json_encode( $context ) ) );
                $value = (string)$r['value'];
                $note = (string)$r['note'];
            }
        }
        catch ( expAiException $e )
        {
            throw new expwtApiError( 422, 'ai_failed', $e->translatedMessage() );
        }
        return array( 'value' => $value, 'note' => $note, 'action' => $action, 'datatype' => $datatype,
                      'field' => (string)$attribute->attribute( 'contentclass_attribute_identifier' ), 'version' => $versionNumber );
    }

    /**
     * Writes a suggestion into the user's draft (E1), for a field the sidebar edits in its own editor (a frame):
     * rich text through the same HTML conversion as the sidebar's form, the alt text of an image into the image
     * field. Text fields of the sidebar's form take the suggestion into the form and are saved with it.
     *
     * @param array $in objectId, version, language, field, value
     * @return array version, field
     */
    public function aiApply( array $in )
    {
        if ( !$this->ai() )
            throw new expwtApiError( 403, 'ai_off', 'Exponential Intelligence is not available here.' );
        list( $object, $version, $language, $attribute ) = $this->aiDraftField( $in, false );
        $value = isset( $in['value'] ) && is_string( $in['value'] ) ? $in['value'] : '';
        $datatype = (string)$attribute->attribute( 'data_type_string' );
        $identifier = (string)$attribute->attribute( 'contentclass_attribute_identifier' );
        $versionNumber = (int)$version->attribute( 'version' );
        if ( $datatype === 'ezxmltext' )
        {
            $this->writer()->updateDraft( (int)$object->attribute( 'id' ), $versionNumber, $language,
                                          array( array( 'identifier' => $identifier, 'value' => $value, 'format' => 'HTML' ) ) );
        }
        else if ( $datatype === 'ezimage' )
        {
            $handler = $attribute->content();
            if ( !is_object( $handler ) || !$handler->attribute( 'is_valid' ) )
                throw new expwtApiError( 400, 'bad_value', 'This value does not fit the field.' );
            $db = eZDB::instance();
            $db->begin();
            $handler->setAttribute( 'alternative_text', trim( preg_replace( '/\s+/u', ' ', $value ) ) );
            $handler->store( $attribute );
            $attribute->store();
            $version->setAttribute( 'modified', time() );
            $version->store();
            $db->commit();
        }
        else
            throw new expwtApiError( 400, 'bad_input', 'This field cannot be edited here.' );
        return array( 'version' => $versionNumber, 'field' => $identifier );
    }

    /**
     * The user's draft and its field for the AI actions: the object editable by the user in the language, the draft
     * given (theirs) or their newest in the language, or (when $create) a new one.
     *
     * @return array eZContentObject, eZContentObjectVersion, language, eZContentObjectAttribute
     */
    protected function aiDraftField( array $in, $create )
    {
        $object = $this->readableObject( $in );
        $language = isset( $in['language'] ) && $in['language'] !== '' ? (string)$in['language'] : (string)$object->attribute( 'initial_language_code' );
        if ( !$object->canEdit( false, false, false, $language ) )
            throw new expwtApiError( 403, 'forbidden', 'You may not do this.' );
        $draft = isset( $in['version'] ) && $in['version'] ? $this->ownDraft( $object, $in['version'] ) : $this->latestOwnDraft( $object, $language );
        if ( isset( $in['version'] ) && $in['version'] && !$draft )
            throw new expwtApiError( 404, 'not_found', 'This draft is not there any more.' );
        if ( !$draft && $create )
        {
            try
            {
                $writer = new expAiContentWriter( $this->ai() );
                list( $draft, $language ) = $writer->draftFor( $object, $language );
            }
            catch ( expAiException $e )
            {
                throw new expwtApiError( 422, 'ai_failed', $e->translatedMessage() );
            }
        }
        if ( !$draft )
            throw new expwtApiError( 404, 'not_found', 'This draft is not there any more.' );
        $field = isset( $in['field'] ) ? (string)$in['field'] : '';
        $dataMap = $object->fetchDataMap( (int)$draft->attribute( 'version' ), $language );
        if ( $field === '' || !isset( $dataMap[$field] ) || in_array( (string)$dataMap[$field]->attribute( 'data_type_string' ), expwtFields::NEVER, true ) )
            throw new expwtApiError( 400, 'bad_input', 'This field cannot be edited here.', array( 'field' => $field ) );
        return array( $object, $draft, $language, $dataMap[$field] );
    }

    // -------------------------------------------------------------------------------- the page's forms (E2)

    /** How many submissions of each form the panel lists */
    const FORM_RECENT = 10;

    /**
     * The page's forms: the page itself when its class collects information (the kernel's information collection),
     * and the objects it relates to or embeds that do, each the user may read. For each its number of submissions;
     * the submissions themselves (date, who, the first answers) only for a user with the kernel's infocollector/read,
     * the policy of the administration's Collected information, which also guards the kernel's CSV export linked here.
     *
     * @param array $in nodeId
     * @return array nodeId, canRead, forms (objectId, name, isPage, count, sensitive, exportUrl, adminUrl, recent)
     */
    public function forms( array $in )
    {
        $node = $this->readableNode( $in );
        $object = $node->object();
        $canRead = $this->may( 'infocollector', 'read' );
        $candidates = array( (int)$object->attribute( 'id' ) => $object );
        foreach ( (array)$object->relatedObjects( false, false, 0, false, array( 'AllRelations' => true ) ) as $related )
            if ( $related instanceof eZContentObject )
                $candidates[(int)$related->attribute( 'id' )] = $related;
        $forms = array();
        $admin = self::adminUrl();
        foreach ( $candidates as $id => $candidate )
        {
            if ( !self::collectsInformation( $candidate ) || !$candidate->canRead() )
                continue;
            $form = array( 'objectId' => $id, 'name' => (string)$candidate->attribute( 'name' ), 'isPage' => $id === (int)$object->attribute( 'id' ),
                           'count' => (int)eZInformationCollection::fetchCollectionCountForObject( $id ),
                           'sensitive' => (bool)eZInformationCollection::isCollectingSensitiveData( $candidate ),
                           'exportUrl' => null, 'adminUrl' => null, 'recent' => array() );
            if ( $canRead )
            {
                $form['exportUrl'] = self::url( '/infocollector/export/' . $id );
                $form['adminUrl'] = $admin ? $admin . 'infocollector/collectionlist/' . $id : null;
                $list = eZInformationCollection::fetchCollectionsList( $id, false, false, array( 'limit' => self::FORM_RECENT, 'offset' => 0 ),
                                                                        array( 'created', false ) );
                foreach ( (array)$list as $collection )
                    $form['recent'][] = array( 'id' => (int)$collection->attribute( 'id' ), 'created' => (int)$collection->attribute( 'created' ),
                                               'by' => self::collectionAuthor( $collection ), 'preview' => self::collectionPreview( $collection ) );
            }
            $forms[] = $form;
        }
        return array( 'nodeId' => (int)$node->attribute( 'node_id' ), 'canRead' => $canRead, 'forms' => $forms );
    }

    /**
     * One submission (E2): its answers, field by field, for a user with infocollector/read who may read its form;
     * audited as the kernel's infocollector/view is (which collection was opened, never its values).
     *
     * @param array $in collectionId
     * @return array id, objectId, form, created, by, fields (name, value), adminUrl
     */
    public function formView( array $in )
    {
        if ( !$this->may( 'infocollector', 'read' ) )
            throw new expwtApiError( 403, 'forbidden', 'You may not do this.' );
        $collection = isset( $in['collectionId'] ) ? eZInformationCollection::fetch( (int)$in['collectionId'] ) : null;
        $object = $collection ? eZContentObject::fetch( (int)$collection->attribute( 'contentobject_id' ) ) : null;
        if ( !$collection || !$object instanceof eZContentObject || !$object->canRead() )
            throw new expwtApiError( 404, 'not_found', 'This submission is not there, or you may not read it.' );
        if ( class_exists( 'expAuditHook' ) )
            expAuditHook::emit( 'data.infocollection.view', array( 'object' => array( 'type' => 'collection', 'id' => (int)$collection->attribute( 'id' ) ),
                'target' => array( 'type' => 'object', 'id' => (int)$object->attribute( 'id' ), 'name' => (string)$object->attribute( 'name' ) ), 'verb' => 'read' ) );
        $admin = self::adminUrl();
        return array( 'id' => (int)$collection->attribute( 'id' ), 'objectId' => (int)$object->attribute( 'id' ), 'form' => (string)$object->attribute( 'name' ),
                      'created' => (int)$collection->attribute( 'created' ), 'by' => self::collectionAuthor( $collection ),
                      'fields' => self::collectionFields( $collection ),
                      'adminUrl' => $admin ? $admin . 'infocollector/view/' . (int)$collection->attribute( 'id' ) : null );
    }

    /** @return bool the object's class has an attribute that collects information */
    protected static function collectsInformation( eZContentObject $object )
    {
        $list = eZContentClassAttribute::fetchFilteredList( array( 'contentclass_id' => (int)$object->attribute( 'contentclass_id' ), 'version' => 0,
                                                                   'is_information_collector' => 1 ), false );
        return !empty( $list );
    }

    /** @return string who sent it: the user's name, or empty for a visitor */
    protected static function collectionAuthor( eZInformationCollection $collection )
    {
        $creatorId = (int)$collection->attribute( 'creator_id' );
        if ( !$creatorId || $creatorId === (int)eZINI::instance()->variable( 'UserSettings', 'AnonymousUserID' ) )
            return '';
        $creator = eZContentObject::fetch( $creatorId );
        return $creator instanceof eZContentObject ? (string)$creator->attribute( 'name' ) : '';
    }

    /** @return array the answers: name, value (text; a number or a choice as the kernel stored it) */
    protected static function collectionFields( eZInformationCollection $collection )
    {
        $out = array();
        foreach ( (array)$collection->informationCollectionAttributes() as $attribute )
            $out[] = array( 'name' => (string)$attribute->attribute( 'contentclass_attribute_name' ),
                            'value' => self::collectedValue( (string)$attribute->attribute( 'data_text' ), $attribute->attribute( 'data_int' ), $attribute->attribute( 'data_float' ) ) );
        return $out;
    }

    /** @return string the first two answers that have text, each cut to 80 characters (the list's preview) */
    protected static function collectionPreview( eZInformationCollection $collection )
    {
        return self::previewOf( self::collectionFields( $collection ) );
    }

    /** Pure: the stored value of an answer as text */
    public static function collectedValue( $text, $int, $float )
    {
        if ( trim( (string)$text ) !== '' )
            return (string)$text;
        if ( $float !== null && (float)$float != 0.0 )
            return (string)(float)$float;
        if ( $int !== null && (int)$int !== 0 )
            return (string)(int)$int;
        return '';
    }

    /** Pure: the preview of a submission: its first two answers with text, one line each, at most 80 characters */
    public static function previewOf( array $fields )
    {
        $parts = array();
        foreach ( $fields as $field )
        {
            $value = trim( preg_replace( '/\s+/u', ' ', (string)$field['value'] ) );
            if ( $value === '' )
                continue;
            $parts[] = mb_strlen( $value, 'UTF-8' ) > 80 ? rtrim( mb_substr( $value, 0, 79, 'UTF-8' ) ) . '…' : $value;
            if ( count( $parts ) === 2 )
                break;
        }
        return implode( ' · ', $parts );
    }

    // ------------------------------------------------------------------------------- the newsletter (E3, cjw_newsletter)

    /** The most editions carrying the page, and the most lists, the panel lists */
    const NEWSLETTER_MAX = 20;

    /** The reauth group of the real send (site.ini [ReauthGroup_newsletter], expwtAccess::REAUTH_GROUPS) */
    const NEWSLETTER_REAUTH_GROUP = 'newsletter';

    /** @return bool cjw_newsletter is active here with its edition class */
    protected static function newsletterInstalled()
    {
        return in_array( 'cjw_newsletter', (array)eZExtension::activeExtensions(), true ) && class_exists( 'CjwNewsletterEditionBuilder' )
               && class_exists( 'CjwNewsletterMail' ) && eZContentClass::classIDByIdentifier( 'cjw_newsletter_edition' );
    }

    /**
     * Whether the Newsletter panel is offered (boot): with cjw_newsletter, for a user with its newsletter/editorial
     * (making an edition, as the administration's article picker needs) or newsletter/send (the test and the real send,
     * as the administration's send page needs).
     *
     * @return array|null create, send
     */
    public function newsletterOffer()
    {
        if ( !$this->capabilities()['edit'] || !self::newsletterInstalled() )
            return null;
        $create = $this->may( 'newsletter', 'editorial' );
        $send = $this->may( 'newsletter', 'send' );
        return $create || $send ? array( 'create' => $create, 'send' => $send ) : null;
    }

    /** @return array the offer, or a refusal when the user has no part of the newsletter */
    protected function newsletterOfferOrRefuse()
    {
        $offer = $this->newsletterOffer();
        if ( !$offer )
            throw new expwtApiError( 403, 'forbidden', 'You may not do this.' );
        return $offer;
    }

    /**
     * The Newsletter panel of a page (E3): when the page is an edition, the edition; else the editions that carry the
     * page as an article (newest first) and, for a user with newsletter/editorial, the lists where the user may make an
     * edition (content/create of an edition below the list).
     *
     * @param array $in nodeId
     * @return array nodeId, name, canCreate, canSend, edition, editions, lists (objectId, name, subscribers, approval)
     */
    public function newsletter( array $in )
    {
        $offer = $this->newsletterOfferOrRefuse();
        $node = $this->readableNode( $in );
        $object = $node->object();
        $out = array( 'nodeId' => (int)$node->attribute( 'node_id' ), 'name' => (string)$node->attribute( 'name' ), 'canCreate' => $offer['create'],
                      'canSend' => $offer['send'], 'edition' => null, 'editions' => array(), 'lists' => array() );
        if ( (string)$object->attribute( 'class_identifier' ) === 'cjw_newsletter_edition' )
        {
            $out['edition'] = $this->editionInfo( $object );
            return $out;
        }
        $seen = array();
        foreach ( CjwNewsletterEditionArticle::fetchListByContentobjectId( (int)$object->attribute( 'id' ), self::NEWSLETTER_MAX ) as $row )
        {
            $id = (int)$row->attribute( 'edition_contentobject_id' );
            $edition = isset( $seen[$id] ) ? null : eZContentObject::fetch( $id );
            $seen[$id] = true;
            if ( $edition instanceof eZContentObject && $edition->canRead() && $edition->attribute( 'main_node_id' ) )
                $out['editions'][] = $this->editionInfo( $edition );
        }
        if ( $offer['create'] )
            $out['lists'] = $this->newsletterLists();
        return $out;
    }

    /** @return array the lists where the user may make an edition: objectId, name, subscribers, approval */
    protected function newsletterLists()
    {
        $classId = (int)eZContentClass::classIDByIdentifier( 'cjw_newsletter_edition' );
        $out = array();
        $nodes = eZContentObjectTreeNode::subTreeByNodeID( array( 'ClassFilterType' => 'include', 'ClassFilterArray' => array( 'cjw_newsletter_list' ),
                                                                  'SortBy' => array( 'name', true ), 'Limit' => self::NEWSLETTER_MAX ), 1 );
        foreach ( (array)$nodes as $list )
        {
            if ( !$list instanceof eZContentObjectTreeNode
                 || $list->checkAccess( 'create', $classId, (int)$list->attribute( 'contentclass_id' ) ) != 1 )
                continue;
            $listId = (int)$list->attribute( 'contentobject_id' );
            $out[] = array( 'objectId' => $listId, 'name' => (string)$list->attribute( 'name' ), 'subscribers' => self::subscriberCount( $listId ),
                            'approval' => CjwNewsletterApprovalFlow::isRequired( $listId ) );
        }
        return $out;
    }

    /** @return int the subscribers a send of the list goes to (approved subscriptions, as the mail queue takes them) */
    protected static function subscriberCount( $listObjectId )
    {
        $list = CjwNewsletterList::fetchByListObjectVersion( (int)$listObjectId, 0 );
        if ( is_object( $list ) && method_exists( $list, 'getSubscriptionObjectCount' ) )
            return (int)$list->getSubscriptionObjectCount( CjwNewsletterSubscription::STATUS_APPROVED );
        return (int)CjwNewsletterSubscription::fetchSubscriptionListByListIdCount( (int)$listObjectId, CjwNewsletterSubscription::STATUS_APPROVED );
    }

    /**
     * An edition as the panel shows it.
     *
     * @return array objectId, nodeId, version, name, list (objectId, name), status (draft, process, archive, abort),
     *               subscribers, approval (required, state), articles, testRecipients, send (status, created), reason
     *               (why it cannot be sent now, empty when it can), adminUrl
     */
    protected function editionInfo( eZContentObject $edition )
    {
        $id = (int)$edition->attribute( 'id' );
        $version = (int)$edition->attribute( 'current_version' );
        $content = CjwNewsletterEditionBuilder::editionContent( $edition );
        $listId = CjwNewsletterApprovalFlow::listOfEdition( $id );
        $list = $listId ? eZContentObject::fetch( $listId ) : null;
        $listContent = $content ? $content->attribute( 'list_attribute_content' ) : null;
        $status = $content ? (string)$content->attribute( 'status' ) : '';
        $send = null;
        foreach ( (array)CjwNewsletterEditionSend::fetchByEditionContentObjectId( $id ) as $row )
            if ( !$send || (int)$row->attribute( 'id' ) > (int)$send->attribute( 'id' ) )
                $send = $row;
        $required = $listId ? CjwNewsletterApprovalFlow::isRequired( $listId ) : false;
        $admin = self::adminUrl();
        $info = array( 'objectId' => $id, 'nodeId' => (int)$edition->attribute( 'main_node_id' ), 'version' => $version,
                       'name' => (string)$edition->attribute( 'name' ),
                       'list' => array( 'objectId' => $listId, 'name' => $list instanceof eZContentObject ? (string)$list->attribute( 'name' ) : '' ),
                       'status' => in_array( $status, array( 'draft', 'process', 'archive', 'abort' ), true ) ? $status : 'draft',
                       'subscribers' => $listId ? self::subscriberCount( $listId ) : 0,
                       'approval' => array( 'required' => $required, 'state' => $required ? CjwNewsletterApprovalFlow::state( $id, $version ) : 'none' ),
                       'articles' => count( CjwNewsletterEditionArticle::fetchByEdition( $id ) ),
                       'testRecipients' => is_object( $listContent ) && $listContent->hasAttribute( 'email_receiver_test' ) ? (string)$listContent->attribute( 'email_receiver_test' ) : '',
                       'send' => $send ? array( 'status' => self::sendState( (int)$send->attribute( 'status' ) ), 'created' => (int)$send->attribute( 'created' ) ) : null,
                       'reason' => '',
                       'adminUrl' => $admin ? $admin . 'newsletter/send/' . (int)$edition->attribute( 'main_node_id' ) : null );
        $info['reason'] = self::editionBlocker( $content !== null, $info['status'], $info['approval'], $info['subscribers'] );
        return $info;
    }

    /** Pure: the state of a send by its status (CjwNewsletterEditionSend::STATUS_*) */
    public static function sendState( $status )
    {
        $map = array( 0 => 'waiting', 4 => 'waiting', 1 => 'queued', 2 => 'sending', 3 => 'sent', 9 => 'aborted' );
        return isset( $map[(int)$status] ) ? $map[(int)$status] : 'waiting';
    }

    /**
     * Pure: why an edition cannot go to its subscribers now (the reasons of the administration's send page), '' when
     * it can.
     *
     * @param bool $hasContent the edition has its newsletter data
     * @param string $status draft, process, archive, abort
     * @param array $approval required, state
     * @param int $subscribers
     * @return string the message (a source string of expwtStrings)
     */
    public static function editionBlocker( $hasContent, $status, array $approval, $subscribers )
    {
        if ( !$hasContent )
            return 'This edition has no newsletter data.';
        if ( $status === 'process' )
            return 'This edition is being sent.';
        if ( $status === 'archive' )
            return 'This edition was sent and is in the archive.';
        if ( !empty( $approval['required'] ) && $approval['state'] !== 'approved' )
            return $approval['state'] === 'pending' ? 'This edition waits for its approval. It can be sent when it is approved.'
                 : ( $approval['state'] === 'rejected' ? 'This edition was rejected. Change it and ask for the approval again.'
                 : 'The list needs an approval before an edition is sent. Ask for the approval in the administration.' );
        if ( (int)$subscribers < 1 )
            return 'The list has no subscribers.';
        return '';
    }

    /**
     * Makes an edition from the page (E3): a new edition (cjw_newsletter_edition) below the list, with the title and
     * the introduction given, published; and the page in it as its article, as the administration's article picker
     * takes it (a newsletter article with the page's title, its intro and "Read more", and the pick that the article
     * statistics read). Needs newsletter/editorial and content/create of an edition below the list.
     *
     * @param array $in nodeId (the page), listId, title, intro
     * @return array the edition (editionInfo())
     */
    public function newsletterCreate( array $in )
    {
        $offer = $this->newsletterOfferOrRefuse();
        if ( !$offer['create'] )
            throw new expwtApiError( 403, 'forbidden', 'You may not do this.' );
        $node = $this->readableNode( $in );
        $list = isset( $in['listId'] ) ? eZContentObject::fetch( (int)$in['listId'] ) : null;
        $listNode = $list instanceof eZContentObject ? $list->attribute( 'main_node' ) : null;
        if ( !$listNode instanceof eZContentObjectTreeNode || (string)$list->attribute( 'class_identifier' ) !== 'cjw_newsletter_list' || !$list->canRead() )
            throw new expwtApiError( 404, 'not_found', 'There is no such newsletter list.' );
        $title = isset( $in['title'] ) ? trim( (string)$in['title'] ) : '';
        if ( $title === '' )
            throw new expwtApiError( 400, 'bad_input', 'Give a title.' );
        $class = eZContentClass::fetchByIdentifier( 'cjw_newsletter_edition' );
        if ( !$class instanceof eZContentClass
             || $listNode->checkAccess( 'create', (int)$class->attribute( 'id' ), (int)$listNode->attribute( 'contentclass_id' ) ) != 1 )
            throw new expwtApiError( 403, 'forbidden', 'You may not do this.' );

        $db = eZDB::instance();
        $db->begin();
        $edition = $class->instantiate( $this->userId(), (int)$list->attribute( 'section_id' ), false );
        $edition->store();
        $assignment = eZNodeAssignment::create( array( 'contentobject_id' => $edition->attribute( 'id' ), 'contentobject_version' => 1,
                                                       'parent_node' => (int)$listNode->attribute( 'node_id' ), 'is_main' => 1 ) );
        $assignment->store();
        $map = $edition->attribute( 'current' )->attribute( 'data_map' );
        if ( isset( $map['title'] ) )
        {
            $map['title']->setAttribute( 'data_text', mb_substr( $title, 0, 255, 'UTF-8' ) );
            $map['title']->store();
        }
        if ( isset( $map['description'] ) )
        {
            $map['description']->setAttribute( 'data_text', self::paragraphsXml( isset( $in['intro'] ) && trim( (string)$in['intro'] ) !== '' ? (string)$in['intro'] : $title ) );
            $map['description']->store();
        }
        if ( isset( $map['newsletter_edition'] ) )
        {
            // the edition's row of the datatype, as its form stores it on the first save
            $row = new CjwNewsletterEdition( array( 'contentobject_attribute_id' => $map['newsletter_edition']->attribute( 'id' ),
                                                    'contentobject_attribute_version' => $map['newsletter_edition']->attribute( 'version' ),
                                                    'contentobject_id' => $edition->attribute( 'id' ), 'contentclass_id' => $class->attribute( 'id' ) ) );
            $row->store();
        }
        $db->commit();
        eZOperationHandler::execute( 'content', 'publish', array( 'object_id' => $edition->attribute( 'id' ), 'version' => 1 ) );
        $edition = eZContentObject::fetch( $edition->attribute( 'id' ) );
        if ( !$edition instanceof eZContentObject || (int)$edition->attribute( 'status' ) !== eZContentObject::STATUS_PUBLISHED || !$edition->attribute( 'main_node_id' ) )
            throw new expwtApiError( 409, 'held', 'The edition was made, but a workflow holds its publishing. Find it in the administration.' );
        $pool = class_exists( 'CjwNewsletterArticlePool' ) ? CjwNewsletterArticlePool::forList( (int)$list->attribute( 'id' ) ) : null;
        if ( !CjwNewsletterEditionBuilder::addArticle( $edition, $node, CjwNewsletterEditionArticle::ADDED_BY_EDITOR,
                                                        is_object( $pool ) ? (int)$pool->attribute( 'id' ) : 0 ) )
            throw new expwtApiError( 500, 'internal', 'The edition was made, but the page could not be taken into it.' );
        if ( class_exists( 'expAuditHook' ) )
            expAuditHook::emit( 'content.newsletter.edition.create', array( 'object' => array( 'type' => 'object', 'id' => (int)$edition->attribute( 'id' ), 'name' => (string)$edition->attribute( 'name' ) ),
                'target' => array( 'type' => 'object', 'id' => (int)$node->attribute( 'contentobject_id' ), 'name' => (string)$node->attribute( 'name' ) ), 'verb' => 'create' ) );
        return $this->editionInfo( eZContentObject::fetch( $edition->attribute( 'id' ) ) );
    }

    /** Pure: plain text as the paragraphs of an ezxmltext section (one per block of lines; a line break inside one) */
    public static function paragraphsXml( $text )
    {
        $dom = new DOMDocument( '1.0', 'utf-8' );
        $section = $dom->createElement( 'section' );
        $section->setAttribute( 'xmlns:image', 'http://ez.no/namespaces/ezpublish3/image/' );
        $section->setAttribute( 'xmlns:xhtml', 'http://ez.no/namespaces/ezpublish3/xhtml/' );
        $section->setAttribute( 'xmlns:custom', 'http://ez.no/namespaces/ezpublish3/custom/' );
        $dom->appendChild( $section );
        $text = mb_substr( str_replace( array( "\r\n", "\r" ), "\n", (string)$text ), 0, 10000, 'UTF-8' );
        foreach ( preg_split( '/\n\s*\n/u', trim( $text ) ) as $block )
        {
            $block = trim( preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $block ) );
            if ( $block === '' )
                continue;
            $paragraph = $dom->createElement( 'paragraph' );
            foreach ( explode( "\n", $block ) as $i => $line )
            {
                if ( $i )
                    $paragraph->appendChild( $dom->createElement( 'line' ) );
                $paragraph->appendChild( $dom->createTextNode( trim( $line ) ) );
            }
            $section->appendChild( $paragraph );
        }
        return $dom->saveXML();
    }

    /** @return eZContentObject an edition the user may read, with its newsletter data */
    protected function sendableEdition( array $in )
    {
        $edition = isset( $in['editionId'] ) ? eZContentObject::fetch( (int)$in['editionId'] ) : null;
        if ( !$edition instanceof eZContentObject || (string)$edition->attribute( 'class_identifier' ) !== 'cjw_newsletter_edition' || !$edition->canRead()
             || !$edition->attribute( 'main_node_id' ) )
            throw new expwtApiError( 404, 'not_found', 'There is no such edition, or you may not read it.' );
        if ( !CjwNewsletterEditionBuilder::editionContent( $edition ) )
            throw new expwtApiError( 409, 'not_sendable', 'This edition has no newsletter data.' );
        return $edition;
    }

    /**
     * A test send of the edition (E3), as the administration's send page makes it (newsletter/send): marked as a test,
     * to the addresses given (the list's test addresses by default), at most as many as a test group may have. Needs
     * newsletter/send. Where the installation writes mail to files (cjw_newsletter.ini TransportMethodPreview=file),
     * the mail goes there.
     *
     * @param array $in editionId, recipients
     * @return array sent (the addresses), invalid, failed (output formats that failed)
     */
    public function newsletterTest( array $in )
    {
        $offer = $this->newsletterOfferOrRefuse();
        if ( !$offer['send'] )
            throw new expwtApiError( 403, 'forbidden', 'You may not do this.' );
        $edition = $this->sendableEdition( $in );
        $parsed = self::testRecipients( isset( $in['recipients'] ) ? (string)$in['recipients'] : '',
                                        class_exists( 'CjwNewsletterTestSend' ) ? (int)CjwNewsletterTestSend::maxSize() : 10 );
        if ( $parsed['invalid'] )
            throw new expwtApiError( 400, 'bad_input', 'Some of the addresses are not valid.', array( 'invalid' => $parsed['invalid'] ) );
        if ( !$parsed['valid'] )
            throw new expwtApiError( 400, 'bad_input', 'Give at least one address.' );
        if ( $parsed['over'] )
            throw new expwtApiError( 400, 'bad_input', 'Too many addresses at once.', array( 'max' => $parsed['max'] ) );
        $mail = new CjwNewsletterMail();
        $result = $mail->sendNewsletterTestMail( $edition->attribute( 'current' ), implode( ', ', $parsed['valid'] ), -1 );
        $failed = array();
        foreach ( (array)$result as $format => $one )
            if ( !is_array( $one ) || empty( $one['send_result'] ) )
                $failed[] = (string)$format;
        if ( class_exists( 'expAuditHook' ) )
            expAuditHook::emit( 'content.newsletter.test', array( 'object' => array( 'type' => 'object', 'id' => (int)$edition->attribute( 'id' ), 'name' => (string)$edition->attribute( 'name' ) ),
                'verb' => 'send', 'count' => count( $parsed['valid'] ) ) );
        return array( 'sent' => $parsed['valid'], 'failed' => $failed, 'edition' => $this->editionInfo( $edition ) );
    }

    /**
     * Pure: the addresses of a test send.
     *
     * @param string $text separated by commas, semicolons or lines
     * @param int $max the most addresses at once
     * @return array valid (lower case, each once), invalid, over (more than max), max
     */
    public static function testRecipients( $text, $max )
    {
        $valid = array();
        $invalid = array();
        foreach ( preg_split( '/[\r\n;,]+/', (string)$text ) as $part )
        {
            $part = trim( $part );
            if ( $part === '' )
                continue;
            if ( filter_var( $part, FILTER_VALIDATE_EMAIL ) !== false && ( !class_exists( 'eZMail' ) || eZMail::validate( $part ) ) )
                $valid[strtolower( $part )] = true;
            else
                $invalid[] = $part;
        }
        $max = max( 1, (int)$max );
        return array( 'valid' => array_keys( $valid ), 'invalid' => $invalid, 'over' => count( $valid ) > $max, 'max' => $max );
    }

    /**
     * The real send of the edition to its list's subscribers (E3), as the administration's send page makes it: a send
     * of the edition's current version, now, which the newsletter's cronjob mails; the other parts of the newsletter
     * (approval, skin, tracking, throttling) check and set it as for a send of the form without choices. Needs
     * newsletter/send and "Verify it's you" (the reauth group "newsletter", which the kernel asks before the request
     * reaches here; checked here again, so no data layer sends without it). The version the user saw must still be
     * the current one.
     *
     * @param array $in editionId, version
     * @return array the edition (editionInfo()) with its send
     */
    public function newsletterSend( array $in )
    {
        $offer = $this->newsletterOfferOrRefuse();
        if ( !$offer['send'] )
            throw new expwtApiError( 403, 'forbidden', 'You may not do this.' );
        $edition = $this->sendableEdition( $in );
        self::requireFreshConfirmation();
        if ( !isset( $in['version'] ) || (int)$in['version'] !== (int)$edition->attribute( 'current_version' ) )
            throw new expwtApiError( 409, 'changed', 'The edition was changed in the meantime. Look at it again before you send it.' );
        $info = $this->editionInfo( $edition );
        if ( $info['reason'] !== '' )
            throw new expwtApiError( 409, 'not_sendable', $info['reason'] );
        $version = $edition->attribute( 'current' );
        $errors = (array)CjwNewsletterExtensionPoints::errors( 'sendFormValidate', array( eZHTTPTool::instance(), $version ) );
        if ( $errors )
            throw new expwtApiError( 409, 'not_sendable', implode( ' ', array_map( 'strval', $errors ) ) );
        $send = CjwNewsletterEditionBuilder::editionContent( $edition )->createNewsletterSendObject( time() );
        if ( !is_object( $send ) )
            throw new expwtApiError( 500, 'internal', 'The send could not be made.' );
        // the other parts set their values of the send as for the form without choices (the list's defaults)
        CjwNewsletterExtensionPoints::call( 'sendFormStored', array( $send, eZHTTPTool::instance(), $version ) );
        if ( class_exists( 'expAuditHook' ) )
            expAuditHook::emit( 'content.newsletter.send', array( 'object' => array( 'type' => 'object', 'id' => (int)$edition->attribute( 'id' ), 'name' => (string)$edition->attribute( 'name' ) ),
                'verb' => 'send', 'count' => $info['subscribers'] ) );
        return $this->editionInfo( eZContentObject::fetch( $edition->attribute( 'id' ) ) );
    }

    /**
     * "Verify it's you" for the real send: the user confirmed within the minutes a confirmation counts. When the kernel
     * would not ask (the feature or the group "newsletter" switched off), the sidebar does not send at all.
     */
    protected static function requireFreshConfirmation()
    {
        if ( !class_exists( 'expReauth' ) )
            throw new expwtApiError( 403, 'reauth_off', 'Sending to the subscribers needs "Verify it\'s you", which is switched off here. Send it in the administration.' );
        $settings = expReauth::settings();
        $group = self::NEWSLETTER_REAUTH_GROUP;
        if ( empty( $settings['enabled'] ) || empty( $settings['groups'][$group]['enabled'] ) )
            throw new expwtApiError( 403, 'reauth_off', 'Sending to the subscribers needs "Verify it\'s you", which is switched off here. Send it in the administration.' );
        if ( !expReauth::isFresh() )
            throw new expwtApiError( 403, 'reauth_required', 'Please confirm it is you first.' );
    }

    // ------------------------------------------------------------------------------------------------ the comments (E4)

    /** The most comments of a page the panel lists (newest first) */
    const COMMENTS_MAX = 50;

    /** The longest reply, in characters */
    const COMMENT_REPLY_MAX = 10000;

    /**
     * @return string|null the content class of the comments here (expservices.ini [Community] CommentClass, the class
     *                     the site's comment form and the comment services use; else comment), null where it does not exist
     */
    public static function commentClass()
    {
        $identifier = 'comment';
        $ini = eZINI::instance( 'expservices.ini' );
        if ( $ini->hasVariable( 'Community', 'CommentClass' ) && trim( (string)$ini->variable( 'Community', 'CommentClass' ) ) !== '' )
            $identifier = trim( (string)$ini->variable( 'Community', 'CommentClass' ) );
        return eZContentClass::classIDByIdentifier( $identifier ) ? $identifier : null;
    }

    /**
     * The comments of the page for the boot (E4): their number, and how many wait for moderation (hidden comments the
     * user may reveal), for the badge on the Page panel's tab. Null without expwt/edit, without a comment class, or on
     * a page without comments the user could see.
     *
     * @param array $in nodeId
     * @return array|null total, waiting
     */
    public function commentsOffer( array $in )
    {
        if ( !$this->capabilities()['edit'] || !isset( $in['nodeId'] ) || ( $class = self::commentClass() ) === null )
            return null;
        $node = eZContentObjectTreeNode::fetch( (int)$in['nodeId'] );
        if ( !$node instanceof eZContentObjectTreeNode || !$node->canRead() )
            return null;
        $total = 0;
        $waiting = 0;
        foreach ( $this->commentNodes( $node, $class ) as $comment )
        {
            if ( !$comment->attribute( 'is_hidden' ) )
                $total++;
            elseif ( $comment->canHide() )
            {
                $total++;
                $waiting++;
            }
        }
        return $total || $this->commentCreateAccess( $node, $class ) ? array( 'total' => $total, 'waiting' => $waiting ) : null;
    }

    /** @return eZContentObjectTreeNode[] the comments directly below the page, hidden ones too, newest first */
    protected function commentNodes( eZContentObjectTreeNode $node, $class )
    {
        return (array)eZContentObjectTreeNode::subTreeByNodeID( array( 'ClassFilterType' => 'include', 'ClassFilterArray' => array( $class ),
            'Depth' => 1, 'DepthOperator' => 'eq', 'IgnoreVisibility' => true,
            'SortBy' => array( array( 'published', false ), array( 'node_id', false ) ), 'Limit' => self::COMMENTS_MAX ), (int)$node->attribute( 'node_id' ) );
    }

    /** @return bool the user may write a comment below the page (content/create of the comment class there) */
    protected function commentCreateAccess( eZContentObjectTreeNode $node, $class )
    {
        $classId = (int)eZContentClass::classIDByIdentifier( $class );
        return $classId && $node->checkAccess( 'create', $classId, (int)$node->attribute( 'contentclass_id' ) ) == 1;
    }

    /**
     * The comments of the page (E4), newest first: the visible ones, and the hidden ones (waiting for moderation) for
     * those who may reveal them. Each with what the user may do to it by the kernel's content policies: approve and
     * hide (content/hide of the comment), mark as spam (content/remove: to the trash); reply (content/create of a
     * comment below the page).
     *
     * @param array $in nodeId
     * @return array nodeId, name, canReply, total, waiting, comments (nodeId, objectId, subject, author, message,
     *               published, hidden, can: moderate, spam)
     */
    public function comments( array $in )
    {
        $class = self::commentClass();
        if ( $class === null )
            throw new expwtApiError( 404, 'not_found', 'There are no comments here.' );
        $node = $this->readableNode( $in );
        $out = array( 'nodeId' => (int)$node->attribute( 'node_id' ), 'name' => (string)$node->attribute( 'name' ),
                      'canReply' => $this->commentCreateAccess( $node, $class ), 'total' => 0, 'waiting' => 0, 'comments' => array() );
        foreach ( $this->commentNodes( $node, $class ) as $comment )
        {
            $hidden = (bool)$comment->attribute( 'is_hidden' );
            $moderate = (bool)$comment->canHide();
            if ( $hidden && !$moderate )
                continue;
            $out['total']++;
            if ( $hidden )
                $out['waiting']++;
            $out['comments'][] = $this->commentInfo( $comment, $moderate );
        }
        return $out;
    }

    /** @return array a comment as the panel shows it */
    protected function commentInfo( eZContentObjectTreeNode $comment, $moderate )
    {
        $object = $comment->object();
        $map = $object instanceof eZContentObject ? (array)$object->attribute( 'data_map' ) : array();
        $text = function ( $id ) use ( $map ) {
            return isset( $map[$id] ) && $map[$id]->hasContent() ? (string)$map[$id]->attribute( 'data_text' ) : '';
        };
        return array( 'nodeId' => (int)$comment->attribute( 'node_id' ), 'objectId' => (int)$comment->attribute( 'contentobject_id' ),
                      'subject' => $text( 'subject' ) !== '' ? $text( 'subject' ) : (string)$comment->attribute( 'name' ),
                      'author' => $text( 'author' ), 'message' => self::commentText( $text( 'message' ) ),
                      'published' => $object instanceof eZContentObject ? (int)$object->attribute( 'published' ) : 0,
                      'hidden' => (bool)$comment->attribute( 'is_hidden' ),
                      'can' => array( 'moderate' => (bool)$moderate, 'spam' => (bool)$comment->canRemove() ) );
    }

    /** Pure: a comment's message as the panel shows it: plain text, control characters out, at most 5000 characters */
    public static function commentText( $text )
    {
        $text = str_replace( array( "\r\n", "\r" ), "\n", (string)$text );
        $text = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text );
        return mb_substr( trim( (string)$text ), 0, 5000, 'UTF-8' );
    }

    /**
     * Pure: what a moderation does to a comment: approve reveals a hidden comment, hide hides a visible one, spam takes
     * it to the trash (visible or hidden).
     *
     * @param string $decision approve, hide, spam
     * @param bool $hidden the comment is hidden now
     * @return string|null reveal, hide, trash, or null when there is nothing to do (an approved comment approved again)
     */
    public static function commentStep( $decision, $hidden )
    {
        if ( $decision === 'spam' )
            return 'trash';
        if ( $decision === 'approve' )
            return $hidden ? 'reveal' : null;
        if ( $decision === 'hide' )
            return $hidden ? null : 'hide';
        return null;
    }

    /** @return eZContentObjectTreeNode a comment the user may read */
    protected function readableComment( array $in )
    {
        $class = self::commentClass();
        $comment = isset( $in['commentId'] ) ? eZContentObjectTreeNode::fetch( (int)$in['commentId'] ) : null;
        if ( $class === null || !$comment instanceof eZContentObjectTreeNode || (string)$comment->attribute( 'class_identifier' ) !== $class || !$comment->canRead() )
            throw new expwtApiError( 404, 'not_found', 'This comment is not there any more, or you may not read it.' );
        return $comment;
    }

    /**
     * Moderates a comment in place (E4): approve (reveal a hidden comment) and hide need content/hide of the comment,
     * spam (to the trash, where it can be restored) content/remove; each through the kernel's operation, as the
     * administration does it, and audited as content.comment.<decision>.
     *
     * @param array $in commentId, decision (approve, hide, spam)
     * @return array status (DONE, or the writer's when a workflow holds it), decision, comment (as comments() lists it; null after spam)
     */
    public function commentModerate( array $in )
    {
        $decision = isset( $in['decision'] ) ? (string)$in['decision'] : '';
        if ( !in_array( $decision, array( 'approve', 'hide', 'spam' ), true ) )
            throw new expwtApiError( 400, 'bad_input', 'There is no such decision.' );
        $comment = $this->readableComment( $in );
        $step = self::commentStep( $decision, (bool)$comment->attribute( 'is_hidden' ) );
        if ( $step === 'trash' ? !$comment->canRemove() : !$comment->canHide() )
            throw new expwtApiError( 403, 'forbidden', 'You may not do this.' );
        $nodeId = (int)$comment->attribute( 'node_id' );
        $parentId = (int)$comment->attribute( 'parent_node_id' );
        $name = (string)$comment->attribute( 'name' );
        $status = 'DONE';
        if ( $step === 'trash' )
            $status = (string)$this->writer()->remove( array( 'nodeId' => $nodeId, 'trash' => true ) )['status'];
        elseif ( $step !== null )
            $status = (string)$this->writer()->visibility( $nodeId, $step === 'hide' )['status'];
        // the trash is where spam goes: done
        if ( in_array( $status, array( 'TRASHED', 'DELETED' ), true ) )
            $status = 'DONE';
        eZContentCacheManager::clearContentCacheIfNeeded( (int)$comment->attribute( 'contentobject_id' ) );
        $parent = eZContentObjectTreeNode::fetch( $parentId );
        if ( $parent instanceof eZContentObjectTreeNode )
            eZContentCacheManager::clearContentCacheIfNeeded( (int)$parent->attribute( 'contentobject_id' ) );
        if ( class_exists( 'expAuditHook' ) )
            expAuditHook::emit( 'content.comment.' . $decision, array( 'object' => array( 'type' => 'node', 'id' => $nodeId, 'name' => $name ),
                'target' => array( 'type' => 'node', 'id' => $parentId ), 'verb' => $decision ) );
        $after = $step === 'trash' ? null : eZContentObjectTreeNode::fetch( $nodeId );
        return array( 'status' => $status, 'decision' => $decision,
                      'comment' => $after instanceof eZContentObjectTreeNode ? $this->commentInfo( $after, (bool)$after->canHide() ) : null );
    }

    /**
     * Pure: the subject of a reply: "Re: " and the subject replied to (once), else the page's name; at most 255 characters.
     */
    public static function replySubject( $subject, $pageName )
    {
        $subject = trim( (string)$subject );
        if ( $subject === '' )
            $subject = trim( (string)$pageName );
        if ( !preg_match( '/^re:\s/iu', $subject ) )
            $subject = 'Re: ' . $subject;
        return mb_substr( $subject, 0, 255, 'UTF-8' );
    }

    /**
     * A reply on the page (E4): a new comment below the page, by the user (their name as the author), published through
     * the kernel's content/publish (its workflows apply, an approval too); needs content/create of the comment class
     * below the page. Audited as content.comment.reply.
     *
     * @param array $in nodeId (the page), commentId (the comment replied to, optional), message
     * @return array status (DONE when published, PENDING when a workflow holds it), comment (null while held)
     */
    public function commentReply( array $in )
    {
        $class = self::commentClass();
        if ( $class === null )
            throw new expwtApiError( 404, 'not_found', 'There are no comments here.' );
        $node = $this->readableNode( $in );
        $message = isset( $in['message'] ) ? trim( (string)$in['message'] ) : '';
        if ( $message === '' )
            throw new expwtApiError( 400, 'bad_input', 'Write the reply first.' );
        if ( !$this->commentCreateAccess( $node, $class ) )
            throw new expwtApiError( 403, 'forbidden', 'You may not do this.' );
        $about = '';
        if ( isset( $in['commentId'] ) )
        {
            $to = $this->readableComment( $in );
            if ( (int)$to->attribute( 'parent_node_id' ) !== (int)$node->attribute( 'node_id' ) )
                throw new expwtApiError( 404, 'not_found', 'This comment is not there any more, or you may not read it.' );
            $about = (string)$to->attribute( 'name' );
        }
        $user = $this->currentUser();
        $userObject = $user->attribute( 'contentobject' );
        $author = $userObject instanceof eZContentObject ? (string)$userObject->attribute( 'name' ) : (string)$user->attribute( 'login' );
        $fields = array( array( 'identifier' => 'subject', 'value' => self::replySubject( $about, $node->attribute( 'name' ) ), 'format' => 'RAW' ),
                         array( 'identifier' => 'author', 'value' => mb_substr( $author, 0, 255, 'UTF-8' ), 'format' => 'RAW' ),
                         array( 'identifier' => 'message', 'value' => mb_substr( $message, 0, self::COMMENT_REPLY_MAX, 'UTF-8' ), 'format' => 'RAW' ) );
        $draft = $this->writer()->createDraft( array( 'parentId' => (int)$node->attribute( 'node_id' ), 'classIdentifier' => $class, 'fields' => $fields ) );
        $result = $this->writer()->publishDraft( (int)$draft['contentId'], (int)$draft['version'] );
        // published at once: done; else a workflow holds it (an approval)
        $status = (string)$result['status'] === 'PUBLISHED' ? 'DONE' : 'PENDING';
        if ( class_exists( 'expAuditHook' ) )
            expAuditHook::emit( 'content.comment.reply', array( 'object' => array( 'type' => 'object', 'id' => (int)$draft['contentId'] ),
                'target' => array( 'type' => 'node', 'id' => (int)$node->attribute( 'node_id' ), 'name' => (string)$node->attribute( 'name' ) ), 'verb' => 'reply' ) );
        $new = !empty( $result['nodeId'] ) ? eZContentObjectTreeNode::fetch( (int)$result['nodeId'] ) : null;
        return array( 'status' => $status, 'comment' => $new instanceof eZContentObjectTreeNode ? $this->commentInfo( $new, (bool)$new->canHide() ) : null );
    }

    // ------------------------------------------------------------------------------------------------ the addresses (E5)

    /** The most addresses, redirects and broken addresses the URLs panel lists */
    const URLS_MAX = 50;

    /** How much of the end of the not-found log the URLs panel reads, in bytes */
    const NOT_FOUND_TAIL = 1048576;

    /**
     * The addresses of the page (E5): its address in each language, its old addresses (which redirect to the current
     * one), the aliases added to it (each redirecting or not), the URL wildcards that lead to it, and the addresses
     * visitors asked for and did not find that look like the page's (the kernel's not-found log, the page's own last
     * part of the address), still not found now. What the user may add: an alias with content/edit of the page (the
     * administration's URL aliases of a page, content/urlalias), a wildcard with content/urltranslator.
     *
     * @param array $in nodeId
     * @return array nodeId, name, mainPath, canAlias, canWildcard, languages, addresses, wildcards, broken
     */
    public function addresses( array $in )
    {
        $node = $this->readableNode( $in );
        $nodeId = (int)$node->attribute( 'node_id' );
        $object = $node->object();
        $canAlias = $object instanceof eZContentObject && $object->canEdit() && $this->may( 'content', 'edit' );
        $canWildcard = $this->may( 'content', 'urltranslator' );
        $addresses = array();
        $slugs = array();
        $prefix = self::pathPrefix();
        foreach ( (array)eZURLAliasML::fetchByAction( 'eznode', $nodeId, false, false, true ) as $element )
        {
            if ( !$element instanceof eZURLAliasML )
                continue;
            // as this siteaccess answers it: without its path prefix
            $path = self::withoutPrefix( (string)$element->getPath(), $prefix );
            $kind = (int)$element->attribute( 'is_alias' ) ? 'alias' : ( (int)$element->attribute( 'is_original' ) ? 'main' : 'history' );
            $codes = eZContentLanguage::decodeLanguageMask( (int)$element->attribute( 'lang_mask' ) & ~1, true );
            $addresses[] = array( 'path' => $path, 'url' => self::url( $path ), 'kind' => $kind,
                                  'redirects' => $kind === 'history' || ( $kind === 'alias' && (int)$element->attribute( 'alias_redirects' ) ),
                                  'language' => implode( ', ', isset( $codes['language_list'] ) ? (array)$codes['language_list'] : array() ) );
            $slugs[] = (string)$element->attribute( 'text' );
            if ( count( $addresses ) >= self::URLS_MAX )
                break;
        }
        usort( $addresses, function ( $a, $b ) {
            $order = array( 'main' => 0, 'alias' => 1, 'history' => 2 );
            return $order[$a['kind']] - $order[$b['kind']] ?: strcmp( $a['path'], $b['path'] );
        } );
        $main = trim( (string)$node->attribute( 'url_alias' ), '/' );
        $own = array_map( function ( $a ) { return $a['path']; }, $addresses );
        $wildcards = array();
        foreach ( (array)eZURLWildcard::fetchList( false, false, false ) as $row )
        {
            if ( !self::wildcardLeadsTo( (string)$row['destination_url'], $own, $nodeId ) )
                continue;
            $wildcards[] = array( 'id' => (int)$row['id'], 'source' => '/' . ltrim( (string)$row['source_url'], '/' ),
                                  'destination' => '/' . ltrim( (string)$row['destination_url'], '/' ),
                                  'type' => (int)$row['type'] === eZURLWildcard::TYPE_FORWARD ? 'redirect' : 'direct' );
            if ( count( $wildcards ) >= self::URLS_MAX )
                break;
        }
        $broken = array();
        if ( $canAlias && class_exists( 'expRequestLog' ) )
        {
            foreach ( self::brokenFor( self::notFoundCounts( self::logTail( expRequestLog::logFile(), self::NOT_FOUND_TAIL ) ), $slugs, $own ) as $path => $seen )
            {
                // still not found now: no address of the kernel answers it (in this siteaccess, with its path prefix)
                if ( eZURLAliasML::fetchByPath( trim( ( $prefix !== '' ? $prefix . '/' : '' ) . trim( $path, '/' ), '/' ) ) || eZURLAliasML::fetchByPath( trim( $path, '/' ) ) )
                    continue;
                $broken[] = array( 'path' => $path, 'count' => $seen['count'], 'last' => $seen['last'] );
                if ( count( $broken ) >= 20 )
                    break;
            }
        }
        $languages = array();
        foreach ( (array)eZContentLanguage::prioritizedLanguages() as $language )
            $languages[] = array( 'code' => (string)$language->attribute( 'locale' ), 'name' => (string)$language->attribute( 'name' ) );
        $admin = self::adminUrl();
        return array( 'nodeId' => $nodeId, 'name' => (string)$node->attribute( 'name' ), 'mainPath' => '/' . $main,
                      'canAlias' => $canAlias, 'canWildcard' => $canWildcard, 'languages' => $languages,
                      'addresses' => $addresses, 'wildcards' => $wildcards, 'broken' => $broken,
                      'adminUrl' => $admin && $canAlias ? $admin . 'content/urlalias/' . $nodeId : null );
    }

    /** @return string the path prefix of this siteaccess (site.ini [SiteAccessSettings] PathPrefix), without slashes */
    protected static function pathPrefix()
    {
        return trim( (string)eZURLAliasML::getPathPrefix(), '/' );
    }

    /** Pure: an address from the top of the aliases as a siteaccess with the path prefix answers it: "/" and the rest */
    public static function withoutPrefix( $path, $prefix )
    {
        $path = trim( (string)$path, '/' );
        $prefix = trim( (string)$prefix, '/' );
        if ( $prefix !== '' && ( strcasecmp( $path, $prefix ) === 0 || stripos( $path, $prefix . '/' ) === 0 ) )
            $path = (string)substr( $path, strlen( $prefix ) + 1 );
        return '/' . $path;
    }

    /** Pure: a wildcard's destination leads to the page (one of its addresses, or its content/view/full address) */
    public static function wildcardLeadsTo( $destination, array $paths, $nodeId )
    {
        $d = strtolower( '/' . trim( preg_replace( '/[?#].*$/', '', (string)$destination ), '/' ) );
        if ( $d === '/content/view/full/' . (int)$nodeId )
            return true;
        foreach ( $paths as $p )
            if ( strtolower( '/' . trim( (string)$p, '/' ) ) === $d )
                return true;
        return false;
    }

    /** @return string[] the lines of the end of a log file (at most $bytes), the first (cut) line left out */
    protected static function logTail( $file, $bytes )
    {
        if ( !is_file( $file ) || !is_readable( $file ) )
            return array();
        $size = filesize( $file );
        $h = fopen( $file, 'rb' );
        if ( !$h )
            return array();
        if ( $size > $bytes )
            fseek( $h, $size - $bytes );
        $text = (string)stream_get_contents( $h );
        fclose( $h );
        $lines = explode( "\n", $text );
        if ( $size > $bytes )
            array_shift( $lines );
        return $lines;
    }

    /**
     * Pure: the paths of the not-found log's lines (expRequestLog's format: "[ time ] [address] 404 GET /path
     * siteaccess=x ..."), without the siteaccess's part of the address and without the query, each with how often and
     * when last. The visitors' addresses are not kept.
     *
     * @param string[] $lines
     * @return array path => count, last
     */
    public static function notFoundCounts( array $lines )
    {
        $out = array();
        foreach ( $lines as $line )
        {
            if ( !preg_match( '#^\[ ([^\]]+) \] \[[^\]]*\] (?:404|410) (?:GET|HEAD) (/\S*) siteaccess=(\S+)#', (string)$line, $m ) )
                continue;
            $path = preg_replace( '/[?#].*$/', '', $m[2] );
            $prefix = '/' . $m[3];
            if ( $m[3] !== '-' && ( $path === $prefix || strpos( $path, $prefix . '/' ) === 0 ) )
                $path = substr( $path, strlen( $prefix ) );
            $path = '/' . trim( rawurldecode( $path ), '/' );
            if ( $path === '/' || strlen( $path ) > 300 || preg_match( '/[\x00-\x1F\x7F<>"]/', $path ) )
                continue;
            if ( !isset( $out[$path] ) )
                $out[$path] = array( 'count' => 0, 'last' => '' );
            $out[$path]['count']++;
            $out[$path]['last'] = trim( $m[1] );
        }
        return $out;
    }

    /** Pure: the last part of an address as compared: lower case, letters and digits, words joined by "-" */
    public static function slug( $text )
    {
        $text = mb_strtolower( (string)$text, 'UTF-8' );
        $text = preg_replace( '/\.(html?|php)$/', '', $text );
        return trim( preg_replace( '/[^\p{L}\p{N}]+/u', '-', $text ), '-' );
    }

    /**
     * Pure: the not-found paths that look like the page's: their last part is the last part of one of the page's
     * addresses, and they are none of the page's addresses. Most often first.
     *
     * @param array $counts of notFoundCounts()
     * @param string[] $slugs the last parts of the page's addresses
     * @param string[] $own the page's addresses
     * @return array path => count, last
     */
    public static function brokenFor( array $counts, array $slugs, array $own )
    {
        $want = array();
        foreach ( $slugs as $s )
            if ( ( $s = self::slug( $s ) ) !== '' )
                $want[$s] = true;
        $mine = array();
        foreach ( $own as $p )
            $mine[strtolower( '/' . trim( (string)$p, '/' ) )] = true;
        $out = array();
        foreach ( $counts as $path => $seen )
        {
            $parts = explode( '/', trim( (string)$path, '/' ) );
            if ( isset( $want[self::slug( end( $parts ) )] ) && !isset( $mine[strtolower( (string)$path )] ) )
                $out[$path] = $seen;
        }
        uasort( $out, function ( $a, $b ) { return $b['count'] - $a['count']; } );
        return $out;
    }

    /**
     * Pure: an address as typed for a new alias or wildcard: without the leading and trailing slashes, the query and
     * the host; null for an empty one or one that leaves the site ("..", a scheme).
     */
    public static function addressInput( $text )
    {
        $text = trim( (string)$text );
        if ( preg_match( '#^[a-z][a-z0-9+.-]*://[^/]*(/.*)?$#i', $text, $m ) )
            $text = isset( $m[1] ) ? $m[1] : '';
        $text = trim( preg_replace( '/[?#].*$/', '', $text ), "/ \t" );
        if ( $text === '' || preg_match( '#(^|/)\.\.?(/|$)#', $text ) || preg_match( '/[\x00-\x1F\x7F]/', $text ) || strlen( $text ) > 300 )
            return null;
        return preg_replace( '#/+#', '/', $text );
    }

    /**
     * Adds an address to the page (E5), as the administration's URL aliases of the page add it (content/urlalias,
     * NewAlias, from the top of the site): in a language, redirecting to the page's address or showing the page under
     * it. Needs content/edit of the page. Also what "Redirect to this page" of a broken address does (redirects on).
     * Audited as content.urlalias.change, as the administration's.
     *
     * @param array $in nodeId, path, language, redirects
     * @return array status (created, cleaned: stored as path), path; or a refusal when the address is taken
     */
    public function urlAlias( array $in )
    {
        $node = $this->readableNode( $in );
        $object = $node->object();
        if ( !$object instanceof eZContentObject || !$object->canEdit() || !$this->may( 'content', 'edit' ) )
            throw new expwtApiError( 403, 'forbidden', 'You may not do this.' );
        $text = self::addressInput( isset( $in['path'] ) ? $in['path'] : '' );
        if ( $text === null )
            throw new expwtApiError( 400, 'bad_input', 'Give an address on this site, such as /campaign/running.' );
        $language = eZContentLanguage::fetchByLocale( isset( $in['language'] ) ? (string)$in['language'] : '' );
        if ( !$language instanceof eZContentLanguage )
            $language = eZContentLanguage::topPriorityLanguage();
        if ( !$language instanceof eZContentLanguage )
            throw new expwtApiError( 400, 'bad_input', 'There is no such language.' );
        $nodeId = (int)$node->attribute( 'node_id' );
        $linkId = 0;
        $filter = new eZURLAliasQuery();
        $filter->actions = array( 'eznode:' . $nodeId );
        $filter->type = 'name';
        $filter->limit = false;
        $existing = $filter->fetchAll();
        if ( $existing )
            $linkId = (int)$existing[0]->attribute( 'id' );
        $alwaysMask = (int)$object->attribute( 'language_mask' ) & 1;
        // from the top of this siteaccess: below its path prefix, where the kernel looks a visitor's address up
        $prefix = self::pathPrefix();
        $result = eZURLAliasML::storePath( ( $prefix !== '' ? $prefix . '/' : '' ) . $text, 'eznode:' . $nodeId, $language, $linkId, $alwaysMask, 0, true, false, false, !empty( $in['redirects'] ) );
        if ( $result['status'] === eZURLAliasML::LINK_ALREADY_TAKEN )
            throw new expwtApiError( 409, 'taken', 'This address is taken already.', array( 'path' => self::withoutPrefix( (string)$result['path'], $prefix ) ) );
        if ( $result['status'] !== true )
            throw new expwtApiError( 500, 'internal', 'Something went wrong on the server.' );
        $stored = self::withoutPrefix( (string)$result['path'], $prefix );
        ezpEvent::getInstance()->notify( 'content/cache', array( array( $nodeId ), array( (int)$node->attribute( 'contentobject_id' ) ) ) );
        if ( class_exists( 'expAuditHook' ) )
            expAuditHook::emit( 'content.urlalias.change', array( 'object' => array( 'type' => 'alias', 'id' => $stored ),
                'target' => array( 'type' => 'node', 'id' => $nodeId, 'name' => (string)$node->attribute( 'name' ) ), 'verb' => 'add',
                'after' => array( 'alias' => $stored, 'redirects' => !empty( $in['redirects'] ), 'language' => (string)$language->attribute( 'locale' ) ) ) );
        return array( 'status' => strcasecmp( $stored, '/' . $text ) === 0 ? 'created' : 'cleaned', 'path' => $stored );
    }

    /**
     * Adds a URL wildcard that leads to the page (E5), as the administration's URL wildcards add one
     * (content/urlwildcards, NewWildcard): the address pattern given (a "*" matches the rest), the page's address as
     * the destination, redirecting (the visitor's browser goes to the page's address) or not (the page is shown under
     * the pattern's address). Needs content/urltranslator. Audited as content.urlalias.change.
     *
     * @param array $in nodeId, source, type (redirect, direct)
     * @return array status created, source, destination
     */
    public function urlWildcard( array $in )
    {
        $node = $this->readableNode( $in );
        if ( !$this->may( 'content', 'urltranslator' ) )
            throw new expwtApiError( 403, 'forbidden', 'You may not do this.' );
        $source = self::addressInput( isset( $in['source'] ) ? $in['source'] : '' );
        if ( $source === null )
            throw new expwtApiError( 400, 'bad_input', 'Give an address on this site, such as /campaign/running.' );
        if ( eZURLWildcard::fetchBySourceURL( $source, false ) )
            throw new expwtApiError( 409, 'taken', 'There is a wildcard for this address already.' );
        $destination = trim( (string)$node->attribute( 'url_alias' ), '/' );
        if ( $destination === '' )
            $destination = 'content/view/full/' . (int)$node->attribute( 'node_id' );
        $wildcard = new eZURLWildcard( array( 'source_url' => $source, 'destination_url' => $destination,
                                              'type' => isset( $in['type'] ) && $in['type'] === 'direct' ? eZURLWildcard::TYPE_DIRECT : eZURLWildcard::TYPE_FORWARD ) );
        $wildcard->store();
        eZURLWildcard::expireCache();
        if ( class_exists( 'expAuditHook' ) )
            expAuditHook::emit( 'content.urlalias.change', array( 'object' => array( 'type' => 'wildcard', 'id' => '/' . $source ),
                'target' => array( 'type' => 'node', 'id' => (int)$node->attribute( 'node_id' ), 'name' => (string)$node->attribute( 'name' ) ), 'verb' => 'add',
                'after' => array( 'source' => '/' . $source, 'destination' => '/' . $destination, 'type' => isset( $in['type'] ) && $in['type'] === 'direct' ? 'direct' : 'redirect' ) ) );
        return array( 'status' => 'created', 'source' => '/' . $source, 'destination' => '/' . $destination );
    }

    // ------------------------------------------------------------------------------------------------ the calendar

    /** How many waiting workflow processes the calendar looks at, at most, for the drafts that wait for their date */
    const CALENDAR_SCAN = 500;

    /**
     * Everything scheduled for the page, its subtree or the whole site in a stretch of time (E6, expwtCalendar): the
     * drafts the wait-until-date event holds (publish), the pages the hide cronjob takes down (takedown) and the layout
     * variants of explayouts (variant, variant-end: the start and the end of a rule's time condition). Only what the
     * user may read; each item says whether the user may move it. setup says which of the kernel's mechanisms the
     * installation has (a wait-until-date event in the content/publish workflow, [HideSettings], explayouts).
     *
     * @param array $in nodeId, scope (page, subtree, site), from, to
     * @return array nodeId, name, scope, from, to, now, setup, items
     */
    public function calendar( array $in )
    {
        $node = $this->readableNode( $in );
        $scope = expwtCalendar::scope( isset( $in['scope'] ) ? $in['scope'] : 'page' );
        $now = time();
        $window = expwtCalendar::window( isset( $in['from'] ) ? $in['from'] : 0, isset( $in['to'] ) ? $in['to'] : 0, $now );
        $setup = array( 'publish' => false, 'takeDown' => false, 'variants' => class_exists( 'expLayoutsRuleCondition' ) );
        $items = array();
        if ( class_exists( 'expContentSchedule' ) )
        {
            $ini = eZINI::instance( 'content.ini' );
            $setup['publish'] = (bool)expContentSchedule::waitAttributeIds();
            $setup['takeDown'] = (bool)array_filter( (array)$ini->variable( 'HideSettings', 'HideDateAttributeList' ) )
                                 && (bool)array_filter( (array)$ini->variable( 'HideSettings', 'RootNodeList' ) );
            if ( $setup['publish'] )
                $items = array_merge( $items, $this->scheduledPublishes( $scope, $node, $window ) );
            if ( $setup['takeDown'] )
                $items = array_merge( $items, $this->scheduledTakeDowns( $scope, $node, $window ) );
        }
        if ( $setup['variants'] )
            $items = array_merge( $items, $this->scheduledVariants( $scope, $node, $window ) );
        return array( 'nodeId' => (int)$node->attribute( 'node_id' ), 'name' => (string)$node->attribute( 'name' ), 'scope' => $scope,
                      'from' => $window['from'], 'to' => $window['to'], 'now' => $now, 'setup' => $setup,
                      'items' => expwtCalendar::sorted( $items ) );
    }

    /** @return array the drafts the wait-until-date event holds, dated in the window, in the scope */
    protected function scheduledPublishes( $scope, eZContentObjectTreeNode $node, array $window )
    {
        $out = array();
        $mayEdit = $this->may( 'content', 'edit' );
        foreach ( self::waitingProcesses() as $key => $process )
        {
            list( $id, $number ) = array_map( 'intval', explode( '-', $key ) );
            $object = eZContentObject::fetch( $id );
            $version = $object instanceof eZContentObject ? $object->version( $number ) : null;
            if ( !$version instanceof eZContentObjectVersion || (int)$version->attribute( 'status' ) !== eZContentObjectVersion::STATUS_PENDING )
                continue;
            $can = expContentSchedule::forObject( $object );
            $at = $can['publish'] ? expContentSchedule::dateOf( $version, $can['publish']['identifier'] ) : 0;
            if ( $at < $window['from'] || $at > $window['to'] )
                continue;
            $own = (int)$version->attribute( 'creator_id' ) === $this->userId();
            if ( !$own && !$object->canRead() )
                continue;
            $paths = array();
            foreach ( (array)$object->assignedNodes() as $n )
                $paths[] = (string)$n->attribute( 'path_string' );
            if ( !$paths )
                $paths[] = expContentSchedule::parentPathOf( $object ) . '0/';
            if ( !expwtCalendar::inScope( $scope, (int)$node->attribute( 'node_id' ), $paths ) )
                continue;
            $out[] = array( 'kind' => 'publish', 'key' => 'p' . $key, 'at' => $at, 'title' => (string)$version->name(),
                            'objectId' => $id, 'version' => $number, 'precision' => $can['publish']['precision'],
                            'url' => self::url( '/content/versionview/' . $id . '/' . $number ),
                            'canMove' => $mayEdit && $object->canEdit() );
            if ( count( $out ) >= expwtCalendar::MAX_ITEMS )
                break;
        }
        return $out;
    }

    /**
     * The workflow processes that wait in a wait-until-date event for the workflow cronjob, by the version they hold
     * (the process keeps the object and the version in its parameters).
     *
     * @return eZWorkflowProcess[] "<object id>-<version>" => process
     */
    protected static function waitingProcesses()
    {
        $out = array();
        $events = array();
        foreach ( (array)eZWorkflowProcess::fetchForStatus( eZWorkflow::STATUS_DEFERRED_TO_CRON ) as $process )
        {
            $eventId = (int)$process->attribute( 'event_id' );
            if ( !isset( $events[$eventId] ) )
            {
                $event = eZWorkflowEvent::fetch( $eventId );
                $events[$eventId] = $event && preg_replace( '/^event_/', '', (string)$event->attribute( 'workflow_type_string' ) ) === expContentSchedule::WAIT_EVENT;
            }
            $parameters = (array)$process->attribute( 'parameter_list' );
            if ( $events[$eventId] && !empty( $parameters['object_id'] ) && !empty( $parameters['version'] ) )
                $out[(int)$parameters['object_id'] . '-' . (int)$parameters['version']] = $process;
            if ( count( $out ) >= self::CALENDAR_SCAN )
                break;
        }
        return $out;
    }

    /** @return eZWorkflowProcess|null the process that holds the version in a wait-until-date event */
    protected static function waitingProcess( $objectId, $version )
    {
        $all = self::waitingProcesses();
        $key = (int)$objectId . '-' . (int)$version;
        return isset( $all[$key] ) ? $all[$key] : null;
    }

    /** @return array the pages the hide cronjob takes down in the window, in the scope */
    protected function scheduledTakeDowns( $scope, eZContentObjectTreeNode $node, array $window )
    {
        $ini = eZINI::instance( 'content.ini' );
        $map = array_filter( (array)$ini->variable( 'HideSettings', 'HideDateAttributeList' ) );
        $pagePath = (string)$node->attribute( 'path_string' );
        $nodeId = (int)$node->attribute( 'node_id' );
        $mayEdit = $this->may( 'content', 'edit' );
        $found = array();
        // the page itself (and, for its subtree, the page as the top of it)
        if ( $scope !== 'site' )
            $found[$nodeId] = $node;
        foreach ( (array)$ini->variable( 'HideSettings', 'RootNodeList' ) as $rootId )
        {
            if ( $scope === 'page' )
                break;
            $root = eZContentObjectTreeNode::fetch( (int)$rootId );
            if ( !$root instanceof eZContentObjectTreeNode )
                continue;
            if ( $scope === 'site' )
                $base = $root;
            else if ( strpos( $pagePath, '/' . (int)$rootId . '/' ) !== false )
                $base = $node;
            else if ( strpos( (string)$root->attribute( 'path_string' ), '/' . $nodeId . '/' ) !== false )
                $base = $root;
            else
                continue;
            foreach ( $map as $class => $attribute )
            {
                $list = $base->subTree( array( 'ClassFilterType' => 'include', 'ClassFilterArray' => array( $class ), 'LoadDataMap' => false,
                    'Limit' => expwtCalendar::MAX_ITEMS, 'SortBy' => array( 'published', true ),
                    'AttributeFilter' => array( 'and', array( "$class/$attribute", '>=', $window['from'] ), array( "$class/$attribute", '<=', $window['to'] ) ) ) );
                foreach ( (array)$list as $n )
                    $found[(int)$n->attribute( 'node_id' )] = $n;
            }
        }
        $out = array();
        foreach ( $found as $id => $n )
        {
            $object = $n->object();
            if ( !$object instanceof eZContentObject || !$n->canRead() || (int)$n->attribute( 'is_invisible' ) )
                continue;
            $can = expContentSchedule::forObject( $object );
            $version = $object->currentVersion();
            $at = $can['takeDown'] && $version ? expContentSchedule::dateOf( $version, $can['takeDown']['identifier'] ) : 0;
            if ( $at < $window['from'] || $at > $window['to'] )
                continue;
            $out[] = array( 'kind' => 'takedown', 'key' => 't' . $id, 'at' => $at, 'title' => (string)$n->attribute( 'name' ),
                            'nodeId' => $id, 'objectId' => (int)$object->attribute( 'id' ), 'precision' => $can['takeDown']['precision'],
                            'url' => self::url( '/' . ltrim( (string)$n->attribute( 'url_alias' ), '/' ) ),
                            'canMove' => $mayEdit && $object->canEdit() );
            if ( count( $out ) >= expwtCalendar::MAX_ITEMS )
                break;
        }
        return $out;
    }

    /** @return array the starts and ends of the layout variants (rules with a time condition) in the window, in the scope */
    protected function scheduledVariants( $scope, eZContentObjectTreeNode $node, array $window )
    {
        $out = array();
        $canMove = !empty( $this->capabilities()['layouts'] ) && $this->may( 'explayouts_ui_api', 'edit' );
        $pagePath = (string)$node->attribute( 'path_string' );
        $nodeId = (int)$node->attribute( 'node_id' );
        $address = '/' . ltrim( (string)$node->attribute( 'url_alias' ), '/' );
        $conditions = eZPersistentObject::fetchObjectList( expLayoutsRuleCondition::definition(), null, array( 'condition_type' => 'time' ), array( 'id' => 'asc' ), null, true );
        foreach ( (array)$conditions as $condition )
        {
            $range = expwtCalendar::timeRange( (string)$condition->attribute( 'condition_value' ) );
            if ( $range === null || !expwtCalendar::touches( $range['from'], $range['to'], $window ) )
                continue;
            $rule = expLayoutsRule::fetch( (int)$condition->attribute( 'rule_id' ) );
            if ( !$rule )
                continue;
            $in = false;
            $where = array();
            foreach ( (array)$rule->targets() as $target )
            {
                $type = (string)$target->attribute( 'target_type' );
                $value = (string)$target->attribute( 'target_value' );
                $targetNode = ( $type === 'node' || $type === 'subtree' ) && ctype_digit( $value ) ? eZContentObjectTreeNode::fetch( (int)$value ) : null;
                $answer = expwtCalendar::targetInScope( $scope, $nodeId, $pagePath, $type, $targetNode ? (string)$targetNode->attribute( 'path_string' ) : '' );
                if ( $answer === null && class_exists( 'expLayoutsResolver' ) )
                {
                    try { $answer = (bool)expLayoutsResolver::targetMatches( $target, $address ); }
                    catch ( Exception $e ) { $answer = false; }
                }
                $in = $in || $answer;
                $where[] = $targetNode && $targetNode->canRead() ? ( $type === 'subtree' ? '/' . ltrim( (string)$targetNode->attribute( 'url_alias' ), '/' ) . ' …' : (string)$targetNode->attribute( 'name' ) )
                                                               : $type . ( $value !== '' ? ' ' . $value : '' );
            }
            if ( !$in )
                continue;
            $layout = class_exists( 'expLayoutsLayout' ) ? expLayoutsLayout::fetch( (int)$rule->attribute( 'layout_id' ) ) : null;
            $base = array( 'conditionId' => (int)$condition->attribute( 'id' ), 'ruleId' => (int)$rule->attribute( 'id' ),
                           'title' => $layout ? (string)$layout->attribute( 'name' ) : '#' . (int)$rule->attribute( 'layout_id' ),
                           'from' => $range['from'], 'to' => $range['to'], 'enabled' => (bool)(int)$rule->attribute( 'enabled' ),
                           'targets' => array_slice( $where, 0, 5 ), 'precision' => 'datetime', 'canMove' => $canMove );
            if ( $range['from'] !== null && $range['from'] >= $window['from'] && $range['from'] <= $window['to'] )
                $out[] = array( 'kind' => 'variant', 'key' => 'v' . $base['conditionId'] . 's', 'at' => $range['from'], 'which' => 'start' ) + $base;
            if ( $range['to'] !== null && $range['to'] >= $window['from'] && $range['to'] <= $window['to'] )
                $out[] = array( 'kind' => 'variant-end', 'key' => 'v' . $base['conditionId'] . 'e', 'at' => $range['to'], 'which' => 'end' ) + $base;
            if ( count( $out ) >= expwtCalendar::MAX_ITEMS )
                break;
        }
        return $out;
    }

    /** @return int the time a move asks for, in the future; refused otherwise */
    protected static function moveTime( array $in )
    {
        $at = expwtSchedule::parse( isset( $in['at'] ) ? $in['at'] : '' );
        $error = expwtCalendar::moveError( $at, time() );
        if ( $error === 'past' )
            throw new expwtApiError( 400, 'past', 'Choose a time that is still to come.' );
        if ( $error !== null )
            throw new expwtApiError( 400, 'bad_value', 'This is not a date and time.' );
        return $at;
    }

    /**
     * Moves the date a draft waits for (E6): the draft's date field the wait-until-date event reads, and the time its
     * workflow process wakes up, so the workflow cronjob publishes it then (every other event of the workflow, an
     * approval, still applies). As editing the date of the draft and publishing it again would; needs content/edit
     * of the object. Audited as content.expwt.schedule.change.
     *
     * @param array $in objectId, version, at
     * @return array status MOVED, at
     */
    public function calendarPublish( array $in )
    {
        $object = isset( $in['objectId'] ) ? eZContentObject::fetch( $in['objectId'] ) : null;
        $version = $object instanceof eZContentObject && isset( $in['version'] ) ? $object->version( (int)$in['version'] ) : null;
        if ( !$version instanceof eZContentObjectVersion || (int)$version->attribute( 'status' ) !== eZContentObjectVersion::STATUS_PENDING )
            throw new expwtApiError( 404, 'not_found', 'This is not scheduled any more.' );
        if ( !$object->canEdit() || !$this->may( 'content', 'edit' ) )
            throw new expwtApiError( 403, 'forbidden', 'You may not do this.' );
        $process = self::waitingProcess( (int)$object->attribute( 'id' ), (int)$version->attribute( 'version' ) );
        if ( !$process || !class_exists( 'expContentSchedule' ) )
            throw new expwtApiError( 409, 'not_waiting', 'This is not scheduled any more.' );
        $at = self::moveTime( $in );
        $can = expContentSchedule::forObject( $object );
        $before = $can['publish'] ? expContentSchedule::dateOf( $version, $can['publish']['identifier'] ) : 0;
        $plan = expContentSchedule::plan( $can['publish'], null, $at, null, time() );
        if ( $plan['error'] !== null || !$plan['writes'] )
            throw new expwtApiError( 400, 'not_schedulable', 'Not possible here: this kind of page has no date field for it.' );
        $written = (int)reset( $plan['writes'] );
        $db = eZDB::instance();
        $db->begin();
        expContentSchedule::setDates( $version, $plan['writes'] );
        $process->setAttribute( 'activation_date', $written );
        $process->store();
        $db->commit();
        self::auditMove( 'publish', array( 'type' => 'object', 'id' => (int)$object->attribute( 'id' ), 'name' => (string)$version->name() ),
                         array( 'version' => (int)$version->attribute( 'version' ), 'at' => $before ), array( 'at' => $written ) );
        return array( 'status' => 'MOVED', 'at' => $written );
    }

    /**
     * Moves the date the hide cronjob takes a page down (E6) as the administration does it: a new version of the page
     * with the new date, published through content/publish (an approval applies: PENDING). Needs content/edit of the
     * page. Audited as content.expwt.schedule.change.
     *
     * @param array $in nodeId, at
     * @return array status (PUBLISHED, PENDING), at
     */
    public function calendarTakeDown( array $in )
    {
        $node = $this->readableNode( $in );
        $object = $node->object();
        if ( !$object instanceof eZContentObject || !$object->canEdit() || !$this->may( 'content', 'edit' ) )
            throw new expwtApiError( 403, 'forbidden', 'You may not do this.' );
        if ( !class_exists( 'expContentSchedule' ) )
            throw new expwtApiError( 400, 'not_schedulable', 'Not possible here: this kind of page has no date field for it.' );
        $at = self::moveTime( $in );
        $can = expContentSchedule::forObject( $object );
        $current = $object->currentVersion();
        $before = $can['takeDown'] && $current ? expContentSchedule::dateOf( $current, $can['takeDown']['identifier'] ) : 0;
        $plan = expContentSchedule::plan( null, $can['takeDown'], null, $at, time() );
        if ( $plan['error'] !== null || !$plan['writes'] )
            throw new expwtApiError( 400, 'not_schedulable', 'Not possible here: this kind of page has no date field for it.' );
        $db = eZDB::instance();
        $db->begin();
        $draft = $object->createNewVersion( false, true );
        if ( !$draft instanceof eZContentObjectVersion )
        {
            $db->rollback();
            throw new expwtApiError( 409, 'version_limit', 'No new version can be made of this page now.' );
        }
        expContentSchedule::setDates( $draft, $plan['writes'] );
        $db->commit();
        $result = $this->writer()->publishDraft( (int)$object->attribute( 'id' ), (int)$draft->attribute( 'version' ) );
        $written = (int)reset( $plan['writes'] );
        self::auditMove( 'takedown', array( 'type' => 'node', 'id' => (int)$node->attribute( 'node_id' ), 'name' => (string)$node->attribute( 'name' ) ),
                         array( 'at' => $before ), array( 'at' => $written, 'version' => (int)$draft->attribute( 'version' ) ) );
        return array( 'status' => (string)$result['status'], 'at' => $written );
    }

    /**
     * Moves a layout variant (E6): the start of a rule's time condition (its end moves with it) or only its end, as
     * explayouts' rule editor would change the condition; needs expwt/layouts and explayouts_ui_api/edit. The
     * resolver's cache and the template blocks expire as after any rule change. Audited as content.expwt.schedule.change.
     *
     * @param array $in conditionId, which (start, end), at
     * @return array status MOVED, from, to
     */
    public function calendarVariant( array $in )
    {
        if ( !class_exists( 'expLayoutsRuleCondition' ) )
            throw new expwtApiError( 404, 'not_found', 'This is not scheduled any more.' );
        if ( !$this->may( 'explayouts_ui_api', 'edit' ) )
            throw new expwtApiError( 403, 'forbidden', 'You may not do this.' );
        $condition = isset( $in['conditionId'] ) ? eZPersistentObject::fetchObject( expLayoutsRuleCondition::definition(), null, array( 'id' => (int)$in['conditionId'] ), true ) : null;
        if ( !$condition || (string)$condition->attribute( 'condition_type' ) !== 'time' )
            throw new expwtApiError( 404, 'not_found', 'This is not scheduled any more.' );
        $at = self::moveTime( $in );
        $before = (string)$condition->attribute( 'condition_value' );
        $moved = expwtCalendar::moveRange( $before, isset( $in['which'] ) && $in['which'] === 'end' ? 'end' : 'start', $at );
        if ( $moved['error'] === 'end_before_start' )
            throw new expwtApiError( 400, 'end_before_start', 'The variant would end before it starts.' );
        if ( $moved['error'] !== null )
            throw new expwtApiError( 400, 'bad_value', 'This is not a date and time.' );
        $condition->setAttribute( 'condition_value', $moved['value'] );
        $condition->store();
        if ( class_exists( 'expLayoutsResolver' ) && method_exists( 'expLayoutsResolver', 'clearCache' ) )
            expLayoutsResolver::clearCache();
        eZContentObject::expireTemplateBlockCache();
        self::auditMove( 'variant', array( 'type' => 'layout_rule', 'id' => (int)$condition->attribute( 'rule_id' ) ),
                         array( 'condition' => $before ), array( 'condition' => $moved['value'] ) );
        return array( 'status' => 'MOVED', 'from' => $moved['from'], 'to' => $moved['to'] );
    }

    /** Records a move of the calendar (expwtAuditBranch: content.expwt.schedule.change) */
    protected static function auditMove( $kind, array $object, array $before, array $after )
    {
        if ( class_exists( 'expAuditHook' ) )
            expAuditHook::emit( 'content.expwt.schedule.change', array( 'object' => $object, 'verb' => 'change',
                'before' => $before, 'after' => array( 'kind' => $kind ) + $after ) );
    }

    // ---------------------------------------------------------------------------------------- the migrations

    /** How long the number on the Migrations tab is kept in the user's session, in seconds */
    const MIGRATIONS_SECONDS = 60;

    /** At most this many pending migrations are listed (the rest are counted) */
    const MIGRATIONS_LISTED = 50;

    /**
     * Whether the kernel has what the Migrations panel reads: the status report of its migrations
     * (expMigrationStatus::report(), Exponential 6.0.15) and the module migration with its policy function read
     * (Setup > Migrations). Without either the panel is not offered at all; there is no fallback to another policy.
     *
     * @return bool
     */
    public static function migrationKernel()
    {
        if ( !class_exists( 'expMigrationStatus' ) || !method_exists( 'expMigrationStatus', 'report' ) || !class_exists( 'eZModule' ) )
            return false;
        $module = eZModule::exists( 'migration' );
        if ( !$module instanceof eZModule )
            return false;
        $functions = $module->attribute( 'available_functions' );
        return is_array( $functions ) && isset( $functions['read'] );
    }

    /** @return bool the user may see the migrations: a user of the sidebar with the kernel's migration/read */
    public function readsMigrations()
    {
        $caps = $this->capabilities();
        return !empty( $caps['use'] ) && self::migrationKernel() && $this->may( 'migration', 'read' );
    }

    /**
     * The Migrations panel in the boot answer: null for a user without migration/read or a kernel without the
     * migrations; else how many need attention (pending, failed, recorded without their file), the badge on the
     * panel's tab. The number is kept in the user's session for MIGRATIONS_SECONDS: the boot of every page asks.
     *
     * @return array|null attention
     */
    public function migrationsOffer()
    {
        if ( !$this->readsMigrations() )
            return null;
        $http = eZHTTPTool::instance();
        $userId = $this->userId();
        $kept = $http->hasSessionVariable( 'expwtMigrations' ) ? $http->sessionVariable( 'expwtMigrations' ) : null;
        if ( is_array( $kept ) && isset( $kept['user'], $kept['count'], $kept['at'] )
             && (int)$kept['user'] === $userId && time() - (int)$kept['at'] < self::MIGRATIONS_SECONDS )
            return array( 'attention' => (int)$kept['count'] );
        try
        {
            $count = self::migrationsView( expMigrationStatus::report( array( 'runs' => 1 ) ), null )['attention'];
        }
        catch ( Throwable $e )
        {
            eZDebug::writeError( get_class( $e ) . ': ' . $e->getMessage(), __METHOD__ );
            $count = 0;
        }
        $http->setSessionVariable( 'expwtMigrations', array( 'user' => $userId, 'count' => $count, 'at' => time() ) );
        return array( 'attention' => $count );
    }

    /**
     * The Migrations panel (R4.5): read-only, the state of the installation's migrations as Setup > Migrations shows
     * it. Running and rolling back stay in the administration; every item links there.
     *
     * @return array migrationsView()
     * @throws expwtApiError 403 without migration/read (or without the migrations in the kernel), 503 when the
     *                       report cannot be made
     */
    public function migrations()
    {
        if ( !$this->readsMigrations() )
            throw new expwtApiError( 403, 'forbidden', 'You may not do this.' );
        try
        {
            $report = expMigrationStatus::report( array( 'runs' => 1 ) );
        }
        catch ( Throwable $e )
        {
            eZDebug::writeError( get_class( $e ) . ': ' . $e->getMessage(), __METHOD__ );
            throw new expwtApiError( 503, 'migrations_unavailable', 'The migrations cannot be read now.' );
        }
        $view = self::migrationsView( $report, $this->migrationsAdmin() );
        eZHTTPTool::instance()->setSessionVariable( 'expwtMigrations', array( 'user' => $this->userId(), 'count' => $view['attention'], 'at' => time() ) );
        return $view;
    }

    /**
     * The administration the panel links to: [SiteAccessSettings] AdminSiteAccessList[] as everywhere in the sidebar,
     * else the administration the kernel's site menu finds for the user (ezpSiteAccessMenu: by its design, the one
     * named admin first), as an address of its own (scheme and host included when it has another host).
     *
     * @return string|null ending in /, null when there is none the user may log in to
     */
    protected function migrationsAdmin()
    {
        $base = self::adminUrl();
        if ( $base || !class_exists( 'ezpSiteAccessMenu' ) )
            return $base;
        try
        {
            // the menu's own list, with the administrations in it whatever the menu shows of them
            $settings = ezpSiteAccessMenu::settings();
            $current = eZSiteAccess::current();
            $user = $this->currentUser();
            $menu = ezpSiteAccessMenu::build( ezpSiteAccessMenu::names( $settings ), ezpSiteAccessURL::frontSiteAccess(),
                                              $current ? (string)$current['name'] : '', $settings['admin'],
                                              array( 'ezpSiteAccessMenu', 'siteAccessInfo' ), array( 'ezpSiteAccessURL', 'root' ),
                                              function ( $name ) use ( $user ) { return (bool)$user->canLoginToSiteAccess( array( 'name' => (string)$name ) ); },
                                              true );
            $found = null;
            foreach ( isset( $menu['groups'] ) ? (array)$menu['groups'] : array() as $group )
                if ( $group['identifier'] === 'admin' )
                    foreach ( (array)$group['items'] as $item )
                        if ( !empty( $item['url'] ) && ( $found === null || $item['identifier'] === 'admin' ) )
                            $found = $item['url'];
            return $found !== null ? rtrim( $found, '/' ) . '/' : null;
        }
        catch ( Throwable $e )
        {
            eZDebug::writeError( get_class( $e ) . ': ' . $e->getMessage(), __METHOD__ );
            return null;
        }
    }

    /**
     * What the panel shows of a status report (exp-migration/1, expMigrationStatus::report()). Pure.
     *
     * Only what a reader of Setup > Migrations sees there: no file paths, process ids or host names; a failed run's
     * message at most 300 characters.
     *
     * @param array $report
     * @param string|null $admin the administration's base address ("/admin/"), null for no links
     * @return array pending (id, source, description, url), more (pending not listed), failed, orphans, last (the
     *               latest run or null), lock (who runs now or null), table, problems, attention
     */
    public static function migrationsView( array $report, $admin )
    {
        $link = function ( $path ) use ( $admin ) { return $admin ? $admin . $path : null; };
        $logLink = function ( $runId ) use ( $link ) {
            return is_string( $runId ) && $runId !== '' ? $link( 'migration/log/' . rawurlencode( $runId ) ) : $link( 'migration/history' );
        };
        $item = function ( array $m ) use ( $link ) {
            return array( 'id' => (string)( isset( $m['id'] ) ? $m['id'] : '' ), 'source' => (string)( isset( $m['source'] ) ? $m['source'] : '' ),
                          'description' => (string)( isset( $m['description'] ) ? $m['description'] : '' ), 'url' => $link( 'migration/status' ) );
        };
        $pending = array();
        $failed = array();
        foreach ( isset( $report['migrations'] ) ? (array)$report['migrations'] : array() as $m )
        {
            $state = isset( $m['state'] ) ? $m['state'] : '';
            if ( $state === 'pending' )
                $pending[] = $item( $m );
            else if ( $state === 'failed' )
            {
                $entry = $item( $m );
                $runId = isset( $m['record']['run_id'] ) ? $m['record']['run_id'] : null;
                if ( $runId )
                    $entry['url'] = $logLink( $runId );
                $failed[] = $entry;
            }
        }
        $orphans = array();
        foreach ( isset( $report['orphans'] ) ? (array)$report['orphans'] : array() as $o )
            $orphans[] = array( 'id' => (string)$o['id'], 'source' => (string)$o['source'], 'state' => (string)$o['state'],
                                'url' => $logLink( isset( $o['run_id'] ) ? $o['run_id'] : null ) );
        $last = null;
        if ( !empty( $report['runs'][0] ) )
        {
            $run = $report['runs'][0];
            $caption = !empty( $run['caption_post'] ) ? $run['caption_post'] : ( !empty( $run['caption_pre'] ) ? $run['caption_pre'] : '' );
            $last = array( 'id' => (string)$run['run_id'], 'state' => (string)$run['state'],
                           'started' => isset( $run['started_at'] ) ? $run['started_at'] : null,
                           'finished' => isset( $run['finished_at'] ) ? $run['finished_at'] : null,
                           'by' => (string)( isset( $run['run_by'] ) ? $run['run_by'] : '' ),
                           'caption' => (string)$caption, 'tag' => (string)( isset( $run['tag_name'] ) ? $run['tag_name'] : '' ),
                           'push' => (string)( isset( $run['push_state'] ) ? $run['push_state'] : '' ),
                           'message' => isset( $run['state'] ) && $run['state'] === 'failed' && isset( $run['message'] ) ? substr( (string)$run['message'], 0, 300 ) : '',
                           'url' => $logLink( $run['run_id'] ) );
        }
        $lock = null;
        if ( !empty( $report['lock'] ) && is_array( $report['lock'] ) )
        {
            $h = $report['lock'];
            $lock = array( 'purpose' => (string)( isset( $h['purpose'] ) ? $h['purpose'] : '' ), 'by' => (string)( isset( $h['user'] ) ? $h['user'] : '' ),
                           'started' => !empty( $h['started'] ) && is_numeric( $h['started'] ) ? date( 'c', (int)$h['started'] ) : null,
                           'url' => $link( 'migration/status' ) );
        }
        return array(
            'pending' => array_slice( $pending, 0, self::MIGRATIONS_LISTED ),
            'more' => max( 0, count( $pending ) - self::MIGRATIONS_LISTED ),
            'failed' => $failed,
            'orphans' => $orphans,
            'last' => $last,
            'lock' => $lock,
            'table' => !isset( $report['table'] ) || (bool)$report['table'],
            'problems' => isset( $report['errors'] ) ? count( (array)$report['errors'] ) : 0,
            'attention' => count( $pending ) + count( $failed ) + count( $orphans ),
            'url' => $link( 'migration/status' ),
        );
    }

    // ------------------------------------------------------------------------- an image's focal point and crop (E7)

    /**
     * An image field of the page for its Images part (E7): the original, the focal point and crop rectangle of the
     * version the user edits (their draft in the language, else the published version), and the aliases the page's
     * templates use (expwtImages::relevantAliases()), each with its filters from the original (the browser previews
     * them live) and its file as it is now. Needs content/edit of the page in the language.
     *
     * @param array $in objectId, version (the user's draft), language, field
     * @return array supported, version, field, original, focal, crop, aliases
     */
    public function images( array $in )
    {
        list( $object, $version, $language, $attribute, $draft ) = $this->imageField( $in, false );
        return $this->imageInfo( $version, $attribute, $draft );
    }

    /**
     * Sets the focal point and crop rectangle of an image field in the user's draft (made by the first save, as the
     * field's own editor makes it), through the kernel's eZImageAliasHandler::setFocalPoint(): the image's aliases
     * are made again around it in that draft only; the published page changes when the draft is published. Audited
     * as content.expwt.image.focal.
     *
     * @param array $in objectId, version, language, field, focal (x, y or null), crop (x, y, width, height or null)
     * @return array as images(), with the draft
     */
    public function imagesSave( array $in )
    {
        $focal = expwtImages::point( isset( $in['focal'] ) ? $in['focal'] : null );
        $crop = expwtImages::rectangle( isset( $in['crop'] ) ? $in['crop'] : null );
        if ( $focal === false || $crop === false )
            throw new expwtApiError( 400, 'bad_value', 'This value does not fit the field.' );
        list( $object, $version, $language, $attribute, $draft ) = $this->imageField( $in, true );
        $handler = $attribute->content();
        if ( !is_object( $handler ) || !$handler->supportsFocalPoint() )
            throw new expwtApiError( 400, 'bad_value', 'This image cannot have a focal point.' );
        $before = array( 'focal' => $handler->focalPoint(), 'crop' => $handler->cropRectangle() );
        $versionNumber = (int)$version->attribute( 'version' );
        $db = eZDB::instance();
        $db->begin();
        if ( !$handler->setFocalPoint( $focal, $crop ) )
        {
            $db->rollback();
            throw new expwtApiError( 400, 'bad_value', 'This image cannot have a focal point.' );
        }
        $handler->store( $attribute );
        $attribute->store();
        $version->setAttribute( 'modified', time() );
        $version->store();
        $db->commit();
        ezpEvent::getInstance()->notify( 'content/cache/version', array( (int)$object->attribute( 'id' ), $versionNumber ) );
        if ( class_exists( 'expAuditHook' ) && $before !== array( 'focal' => $focal, 'crop' => $crop ) )
            expAuditHook::emit( 'content.expwt.image.focal', array(
                'object' => array( 'type' => 'content_object', 'id' => (int)$object->attribute( 'id' ), 'version' => $versionNumber,
                                   'language' => $language, 'field' => (string)$attribute->attribute( 'contentclass_attribute_identifier' ) ),
                'verb' => 'change', 'before' => $before, 'after' => array( 'focal' => $focal, 'crop' => $crop ) ) );
        // the stored attribute again, as the next request sees it
        $dataMap = $object->fetchDataMap( $versionNumber, $language );
        $field = (string)$attribute->attribute( 'contentclass_attribute_identifier' );
        return $this->imageInfo( $version, isset( $dataMap[$field] ) ? $dataMap[$field] : $attribute, $version );
    }

    /**
     * The image field of $in in the user's draft, or (reading, no draft yet) in the published version; with $create
     * a draft is made in the language when the user has none, as the field's editor makes it (expwt/field).
     *
     * @return array eZContentObject, eZContentObjectVersion, language, eZContentObjectAttribute, the draft or null
     */
    protected function imageField( array $in, $create )
    {
        if ( !expwtImages::available() )
            throw new expwtApiError( 404, 'images_off', 'Focal points are not available here.' );
        $object = $this->readableObject( $in );
        $language = isset( $in['language'] ) && $in['language'] !== '' ? (string)$in['language'] : (string)$object->attribute( 'initial_language_code' );
        if ( !$object->canEdit( false, false, false, $language ) )
            throw new expwtApiError( 403, 'forbidden', 'You may not do this.' );
        $draft = isset( $in['version'] ) && $in['version'] ? $this->ownDraft( $object, $in['version'] ) : $this->latestOwnDraft( $object, $language );
        if ( isset( $in['version'] ) && $in['version'] && !$draft )
            throw new expwtApiError( 404, 'not_found', 'This draft is not there any more.' );
        if ( !$draft && $create )
        {
            $db = eZDB::instance();
            $db->begin();
            $existing = in_array( $language, $object->availableLanguages(), true );
            $draft = $object->createNewVersionIn( $language, $existing ? false : (string)$object->attribute( 'initial_language_code' ) );
            $draft->setAttribute( 'status', eZContentObjectVersion::STATUS_DRAFT );
            $draft->store();
            $db->commit();
        }
        $version = $draft ?: $object->version( (int)$object->attribute( 'current_version' ) );
        if ( !$version instanceof eZContentObjectVersion )
            throw new expwtApiError( 404, 'not_found', 'This page is not there, or you may not read it.' );
        $field = isset( $in['field'] ) ? (string)$in['field'] : '';
        $dataMap = $object->fetchDataMap( (int)$version->attribute( 'version' ), $language );
        if ( $field === '' || !isset( $dataMap[$field] ) || (string)$dataMap[$field]->attribute( 'data_type_string' ) !== 'ezimage' )
            throw new expwtApiError( 400, 'bad_input', 'This field cannot be edited here.', array( 'field' => $field ) );
        return array( $object, $version, $language, $dataMap[$field], $draft );
    }

    /** @return array what images() answers for an image attribute */
    protected function imageInfo( eZContentObjectVersion $version, eZContentObjectAttribute $attribute, $draft )
    {
        $handler = $attribute->content();
        $out = array( 'supported' => false, 'version' => $draft ? (int)$draft->attribute( 'version' ) : null,
                      'field' => (string)$attribute->attribute( 'contentclass_attribute_identifier' ),
                      'original' => null, 'focal' => null, 'crop' => null, 'aliases' => array() );
        if ( !is_object( $handler ) || !$handler->supportsFocalPoint() )
            return $out;
        $original = $handler->attribute( 'original' );
        $out['supported'] = true;
        $out['original'] = array( 'url' => self::fileUrl( $original['url'] ), 'width' => (int)$original['width'], 'height' => (int)$original['height'],
                                  'alt' => (string)$original['alternative_text'] );
        $out['focal'] = $handler->focalPoint();
        $out['crop'] = $handler->cropRectangle();

        // the aliases the image has, in this version and in the published one (what the page's templates made)
        $made = array_keys( $handler->aliasList() );
        $object = $version->contentObject();
        $published = $object ? (int)$object->attribute( 'current_version' ) : 0;
        if ( $published && $published !== (int)$version->attribute( 'version' ) )
        {
            $map = $object->fetchDataMap( $published, (string)$attribute->attribute( 'language_code' ) );
            $identifier = $out['field'];
            if ( isset( $map[$identifier] ) && is_object( $map[$identifier]->content() ) )
                $made = array_merge( $made, array_keys( $map[$identifier]->content()->aliasList() ) );
        }
        $aliasList = eZImageManager::factory()->aliasList();
        foreach ( expwtImages::relevantAliases( $aliasList, array_values( array_unique( $made ) ) ) as $name )
        {
            $alias = $handler->imageAlias( $name );
            $out['aliases'][] = array(
                'name' => $name,
                'filters' => expwtImages::chain( $aliasList, $name ),
                'url' => is_array( $alias ) && !empty( $alias['url'] ) ? self::fileUrl( $alias['url'] ) : null,
                'width' => is_array( $alias ) ? (int)$alias['width'] : 0,
                'height' => is_array( $alias ) ? (int)$alias['height'] : 0,
            );
        }
        return $out;
    }

    /** @return string the address of a stored file (var/.../storage/...) */
    protected static function fileUrl( $path )
    {
        $path = (string)$path;
        if ( $path === '' )
            return '';
        return rtrim( eZSys::wwwDir(), '/' ) . '/' . ltrim( $path, '/' );
    }

    // ------------------------------------------------------------------------------------------------ the device preview

    /**
     * The device preview of the page (E8): the frames of expwt.ini [DevicePreviewSettings] (their names translated)
     * and the two addresses a frame may show, the published page and the user's own newest draft of it in the
     * language (the kernel's content/versionview, which checks again that the user may read it). Read-only.
     *
     * @param array $in nodeId, language (optional: the page's language here)
     * @return array devices, shown, custom, urls (published, draft), draft (version, modified) or null
     */
    public function previewDevices( array $in )
    {
        $node = isset( $in['nodeId'] ) ? eZContentObjectTreeNode::fetch( $in['nodeId'] ) : null;
        if ( !$node instanceof eZContentObjectTreeNode || !$node->canRead() )
            throw new expwtApiError( 404, 'not_found', 'This page is not there, or you may not read it.' );
        $object = $node->object();
        if ( !$object instanceof eZContentObject )
            throw new expwtApiError( 404, 'not_found', 'This page is not there, or you may not read it.' );
        $language = isset( $in['language'] ) && preg_match( '/^[a-z]{3}-[A-Z]{2}$/', $in['language'] ) ? $in['language'] : '';
        if ( $language === '' )
            $language = method_exists( $node, 'currentLanguage' ) && $node->currentLanguage() ? (string)$node->currentLanguage()
                                                                                                 : (string)$object->attribute( 'initial_language_code' );
        $draft = $this->latestOwnDraft( $object, $language );
        $id = (int)$object->attribute( 'id' );
        return self::devicesView( $this->settings,
            self::url( '/' . ltrim( (string)$node->attribute( 'url_alias' ), '/' ) ),
            $draft ? self::url( '/content/versionview/' . $id . '/' . (int)$draft->attribute( 'version' ) . '/' . $language ) : null,
            $draft ? array( 'version' => (int)$draft->attribute( 'version' ), 'modified' => (int)$draft->attribute( 'modified' ) ) : null,
            function ( $name ) { return expwtStrings::tr( $name ); } );
    }

    /**
     * Pure: what the device preview gets. Every address carries ?in=sidebar, so the page in a frame comes without a
     * sidebar and a debug bar of its own (expwtHook::framePage()).
     *
     * @param array $settings expwtSettings
     * @param string $published the page's address
     * @param string|null $draft the address of the user's draft
     * @param array|null $draftInfo version, modified
     * @param callable $tr translates a device's name
     * @return array
     */
    public static function devicesView( array $settings, $published, $draft, $draftInfo, callable $tr )
    {
        $devices = array();
        foreach ( $settings['devices'] as $d )
            $devices[] = array( 'name' => (string)call_user_func( $tr, $d['name'] ) ) + $d;
        $ids = array_map( function ( $d ) { return $d['id']; }, $devices );
        return array(
            'devices' => $devices,
            'shown' => $settings['devices_shown'] ? array_values( $settings['devices_shown'] ) : $ids,
            'custom' => array( 'min' => expwtSettings::DEVICE_MIN, 'max' => expwtSettings::DEVICE_MAX_WIDTH, 'height' => 900 ),
            'urls' => array( 'published' => self::framed( $published ), 'draft' => $draft !== null ? self::framed( $draft ) : null ),
            'draft' => $draft !== null ? $draftInfo : null,
        );
    }

    /** Pure: an address of the site with in=sidebar added (no sidebar and no debug bar in the page it shows) */
    public static function framed( $url )
    {
        $url = (string)$url;
        $hash = '';
        if ( ( $at = strpos( $url, '#' ) ) !== false )
        {
            $hash = substr( $url, $at );
            $url = substr( $url, 0, $at );
        }
        return $url . ( strpos( $url, '?' ) === false ? '?' : '&' ) . 'in=sidebar' . $hash;
    }

    // ------------------------------------------------------------------------------------------------ the inbox

    /** @return bool the user may use the kernel's collaboration (the module's policy), where the approvals are */
    public function collaborates()
    {
        if ( !$this->currentUser()->isRegistered() || !class_exists( 'expCollaborationInbox' ) )
            return false;
        $access = $this->currentUser()->hasAccessTo( 'collaboration' );
        return isset( $access['accessWord'] ) && $access['accessWord'] !== 'no';
    }

    /** How long the count on the closed tab is kept in the user's session, in seconds */
    const WAITING_SECONDS = 60;

    /**
     * @param bool $fresh count again (after a decision), not the count kept in the session
     * @return int how many approvals wait for the user (the count on the closed tab, F29); 0 without collaboration.
     *             Two counting queries through the persistent object layer (every database engine), kept in the
     *             user's session for WAITING_SECONDS: the boot of every page asks for it.
     */
    public function waitingCount( $fresh = false )
    {
        if ( !$this->collaborates() )
            return 0;
        $http = eZHTTPTool::instance();
        $userId = $this->userId();
        $kept = $http->hasSessionVariable( 'expwtWaiting' ) ? $http->sessionVariable( 'expwtWaiting' ) : null;
        if ( !$fresh && is_array( $kept ) && isset( $kept['user'], $kept['count'], $kept['at'] )
             && (int)$kept['user'] === $userId && time() - (int)$kept['at'] < self::WAITING_SECONDS )
            return (int)$kept['count'];
        $count = self::countWaitingFor( $userId );
        $http->setSessionVariable( 'expwtWaiting', array( 'user' => $userId, 'count' => $count, 'at' => time() ) );
        return $count;
    }

    /** @return int the waiting approvals (ezapprove, undecided, active) the user is an approver of */
    protected static function countWaitingFor( $userId )
    {
        $rows = eZPersistentObject::fetchObjectList( eZCollaborationItemParticipantLink::definition(), array( 'collaboration_id' ),
                                                     array( 'participant_id' => (int)$userId,
                                                            'participant_role' => eZCollaborationItemParticipantLink::ROLE_APPROVER ),
                                                     null, null, false );
        $ids = array();
        foreach ( (array)$rows as $row )
            $ids[] = (int)$row['collaboration_id'];
        if ( !$ids )
            return 0;
        $count = 0;
        foreach ( array_chunk( array_unique( $ids ), 500 ) as $chunk )
            $count += (int)eZPersistentObject::count( eZCollaborationItem::definition(),
                                                      array( 'id' => array( $chunk ), 'type_identifier' => 'ezapprove',
                                                             'data_int3' => eZApproveCollaborationHandler::STATUS_WAITING,
                                                             'status' => eZCollaborationItem::STATUS_ACTIVE ) );
        return $count;
    }

    /**
     * The inbox (F29): the approvals of the kernel's approve workflow (collaboration items of ezapprove) that wait for
     * the user, read through the kernel's collaboration inbox (expCollaborationInbox), newest first. Each with the
     * page it is about, its author, the last comment, whether the user is one of its approvers (only they decide it),
     * and the draft's preview (content/versionview, which checks the user may read that version).
     */
    public function inbox( array $in )
    {
        // without the kernel's collaboration: only the failed scheduled tasks (F29)
        if ( !$this->collaborates() )
        {
            $notices = $this->notices();
            $counts = $this->noticeCount( true );
            return array( 'total' => 0, 'offset' => 0, 'items' => array(), 'waiting' => 0, 'count' => $counts['count'],
                          'collaborates' => false ) + $notices;
        }
        $userId = $this->userId();
        $inbox = expCollaborationInbox::fetch( array( 'status' => 'waiting', 'role' => 'approver', 'type' => 'ezapprove',
                                                      'offset' => isset( $in['offset'] ) ? (int)$in['offset'] : 0, 'limit' => 25 ) );
        $items = array();
        foreach ( $inbox['items'] as $row )
        {
            $language = '';
            $object = eZContentObject::fetch( (int)$row['object_id'] );
            $version = $object instanceof eZContentObject ? $object->version( (int)$row['version'] ) : null;
            if ( $version instanceof eZContentObjectVersion )
                $language = (string)$version->initialLanguageCode();
            $items[] = array(
                'id' => (int)$row['id'],
                'title' => (string)$row['title'],
                'author' => (string)$row['author_name'],
                'created' => (int)$row['created'],
                'modified' => (int)$row['modified'],
                'objectId' => (int)$row['object_id'],
                'version' => (int)$row['version'],
                'unread' => (int)$row['unread_messages'],
                'lastMessage' => $row['last_message'] ? array( 'text' => (string)$row['last_message']['text'],
                                                               'author' => (string)$row['last_message']['author'],
                                                               'created' => (int)$row['last_message']['created'] ) : null,
                'approver' => self::isApprover( (int)$row['id'], $userId ),
                'preview' => $version && $language !== '' ? self::url( '/content/versionview/' . (int)$row['object_id'] . '/' . (int)$row['version'] . '/' . $language ) : null,
            );
        }
        // the counts on the tabs, counted again now that the list is open; the other notifications beside (F29)
        $counts = $this->noticeCount( true );
        return array( 'total' => (int)$inbox['total'], 'offset' => (int)$inbox['offset'], 'items' => $items,
                      'waiting' => $this->waitingCount( true ), 'count' => $counts['count'], 'collaborates' => true ) + $this->notices();
    }

    // ------------------------------------------------------------------------- notifications (F29), beside the approvals

    /** How far back the publish results and failed scheduled tasks reach, in days */
    const NOTICE_DAYS = 30;

    /**
     * The number on the closed tab and the Inbox panel's tab (F29): the approvals waiting for the user, the items with
     * comments the user has not read (mentions among them), the user's pages decided since the user last looked
     * (publish results), and the user's scheduled tasks that failed in the last NOTICE_DAYS days. Each item counts
     * once. A handful of queries through the persistent object layer (every database engine), kept in the user's
     * session for WAITING_SECONDS.
     *
     * @param bool $fresh count again (the Inbox panel opened, something marked as read or decided)
     * @return array waiting (approvals), count (everything)
     */
    public function noticeCount( $fresh = false )
    {
        $http = eZHTTPTool::instance();
        $userId = $this->userId();
        $kept = $http->hasSessionVariable( 'expwtNotices' ) ? $http->sessionVariable( 'expwtNotices' ) : null;
        if ( !$fresh && is_array( $kept ) && isset( $kept['user'], $kept['waiting'], $kept['count'], $kept['at'] )
             && (int)$kept['user'] === $userId && time() - (int)$kept['at'] < self::WAITING_SECONDS )
            return array( 'waiting' => (int)$kept['waiting'], 'count' => (int)$kept['count'] );
        $waiting = $this->collaborates() ? self::waitingIds( $userId ) : array();
        // comments and publish results: the items the user takes part in (what the kernel checks for an item), also
        // for an author without the collaboration policy (the shipped Editor role); approving needs the policy
        $unread = class_exists( 'expCollaborationInbox' ) ? self::unreadIds( $userId ) : array( 'comments' => array(), 'results' => array() );
        $count = self::noticeTotal( $waiting, $unread['comments'], $unread['results'], self::failedCount( $userId ) );
        $http->setSessionVariable( 'expwtNotices', array( 'user' => $userId, 'waiting' => count( $waiting ), 'count' => $count, 'at' => time() ) );
        return array( 'waiting' => count( $waiting ), 'count' => $count );
    }

    /** @return int every notification once: an item waiting for approval with unread comments counts one */
    public static function noticeTotal( array $waiting, array $comments, array $results, $failed )
    {
        return count( array_unique( array_merge( $waiting, $comments, $results ) ) ) + max( 0, (int)$failed );
    }

    /** @return int[] the waiting approvals (ezapprove, undecided, active) the user is an approver of */
    protected static function waitingIds( $userId )
    {
        $rows = eZPersistentObject::fetchObjectList( eZCollaborationItemParticipantLink::definition(), array( 'collaboration_id' ),
                                                     array( 'participant_id' => (int)$userId,
                                                            'participant_role' => eZCollaborationItemParticipantLink::ROLE_APPROVER ),
                                                     null, null, false );
        $ids = array();
        foreach ( (array)$rows as $row )
            $ids[] = (int)$row['collaboration_id'];
        $out = array();
        foreach ( array_chunk( array_unique( $ids ), 500 ) as $chunk )
        {
            $items = eZPersistentObject::fetchObjectList( eZCollaborationItem::definition(), array( 'id' ),
                                                          array( 'id' => array( $chunk ), 'type_identifier' => 'ezapprove',
                                                                 'data_int3' => eZApproveCollaborationHandler::STATUS_WAITING,
                                                                 'status' => eZCollaborationItem::STATUS_ACTIVE ), null, null, false );
            foreach ( (array)$items as $item )
                $out[] = (int)$item['id'];
        }
        return $out;
    }

    /**
     * @return array comments (items with a message of someone else after the user last read the item), results (the
     *               user's own approvals decided after the user last read them, within NOTICE_DAYS)
     */
    protected static function unreadIds( $userId )
    {
        $status = eZPersistentObject::fetchObjectList( eZCollaborationItemStatus::definition(), array( 'collaboration_id', 'last_read' ),
                                                       array( 'user_id' => (int)$userId ), null, null, false );
        $status = (array)$status;
        if ( !$status )
            return array( 'comments' => array(), 'results' => array() );
        $ids = array();
        foreach ( $status as $row )
            $ids[] = (int)$row['collaboration_id'];
        $items = array();
        $messages = array();
        foreach ( array_chunk( array_unique( $ids ), 500 ) as $chunk )
        {
            $items = array_merge( $items, (array)eZPersistentObject::fetchObjectList( eZCollaborationItem::definition(),
                array( 'id', 'creator_id', 'modified', 'type_identifier', 'data_int3' ), array( 'id' => array( $chunk ) ), null, null, false ) );
            $messages = array_merge( $messages, (array)eZPersistentObject::fetchObjectList( eZCollaborationItemMessageLink::definition(),
                array( 'collaboration_id', 'participant_id', 'modified' ), array( 'collaboration_id' => array( $chunk ) ), null, null, false ) );
        }
        return self::unreadFrom( $status, $items, $messages, $userId, time() - self::NOTICE_DAYS * 86400 );
    }

    /**
     * Pure (tested): which items have news for the user.
     *
     * @param array $status rows collaboration_id, last_read of the user
     * @param array $items rows id, creator_id, modified, type_identifier, data_int3
     * @param array $messages rows collaboration_id, participant_id, modified
     * @param int $userId
     * @param int $since publish results older than this are no news any more
     * @return array comments (item ids), results (item ids)
     */
    public static function unreadFrom( array $status, array $items, array $messages, $userId, $since )
    {
        $read = array();
        foreach ( $status as $row )
            $read[(int)$row['collaboration_id']] = (int)$row['last_read'];
        $comments = array();
        foreach ( $messages as $m )
        {
            $id = (int)$m['collaboration_id'];
            if ( isset( $read[$id] ) && (int)$m['participant_id'] !== (int)$userId && (int)$m['modified'] > $read[$id] )
                $comments[$id] = $id;
        }
        $results = array();
        foreach ( $items as $item )
        {
            $id = (int)$item['id'];
            // eZApproveCollaborationHandler::STATUS_ACCEPTED, _DENIED, _DEFERRED (1, 2, 3; 0 is waiting)
            $decided = in_array( (int)$item['data_int3'], array( 1, 2, 3 ), true );
            if ( isset( $read[$id] ) && $item['type_identifier'] === 'ezapprove' && $decided && (int)$item['creator_id'] === (int)$userId
                 && (int)$item['modified'] > $read[$id] && (int)$item['modified'] >= $since )
                $results[$id] = $id;
        }
        return array( 'comments' => array_values( $comments ), 'results' => array_values( $results ) );
    }

    /** @return bool the text mentions the login (@login, not inside a longer name or an e-mail address) */
    public static function mentions( $text, $login )
    {
        $login = (string)$login;
        if ( $login === '' )
            return false;
        return (bool)preg_match( '/(?<![\w@.])@' . preg_quote( $login, '/' ) . '(?![\w-])/iu', (string)$text );
    }

    /** @return int the user's scheduled tasks (workflow processes) that failed within NOTICE_DAYS */
    protected static function failedCount( $userId )
    {
        if ( !class_exists( 'eZWorkflowProcess' ) )
            return 0;
        return (int)eZPersistentObject::count( eZWorkflowProcess::definition(),
                                               array( 'user_id' => (int)$userId, 'status' => eZWorkflow::STATUS_FAILED,
                                                      'modified' => array( '>=', time() - self::NOTICE_DAYS * 86400 ) ) );
    }

    /**
     * The notifications beside the approvals, for the Inbox panel (F29): comments (unread, mentions marked), the
     * user's publish results, the user's failed scheduled tasks. Read from the kernel's collaboration inbox and its
     * workflow processes; nothing is marked as read by reading them.
     */
    public function notices()
    {
        $userId = $this->userId();
        $login = (string)$this->currentUser()->attribute( 'login' );
        $comments = array();
        $results = array();
        $admin = $this->collaborates();
        if ( class_exists( 'expCollaborationInbox' ) )
        {
            $unread = self::unreadIds( $userId );
            $inbox = expCollaborationInbox::fetch( array( 'status' => 'all', 'limit' => 100 ) );
            foreach ( $inbox['items'] as $row )
            {
                $id = (int)$row['id'];
                $entry = array( 'id' => $id, 'title' => (string)$row['title'], 'author' => (string)$row['author_name'],
                                'state' => (string)$row['state'], 'modified' => (int)$row['modified'],
                                'objectId' => (int)$row['object_id'], 'version' => (int)$row['version'],
                                'lastMessage' => $row['last_message'] ? array( 'text' => (string)$row['last_message']['text'],
                                                                               'author' => (string)$row['last_message']['author'],
                                                                               'created' => (int)$row['last_message']['created'] ) : null,
                                'url' => $admin ? self::adminItemUrl( $id ) : null );
                if ( in_array( $id, $unread['comments'], true ) )
                    $comments[] = $entry + array( 'unread' => (int)$row['unread_messages'],
                                                  'mention' => $row['last_message'] && self::mentions( $row['last_message']['text'], $login ) );
                if ( in_array( $id, $unread['results'], true ) )
                    $results[] = $entry;
            }
        }
        $failed = array();
        if ( class_exists( 'eZWorkflowProcess' ) )
        {
            $rows = eZPersistentObject::fetchObjectList( eZWorkflowProcess::definition(), null,
                                                         array( 'user_id' => $userId, 'status' => eZWorkflow::STATUS_FAILED,
                                                                'modified' => array( '>=', time() - self::NOTICE_DAYS * 86400 ) ),
                                                         array( 'modified' => 'desc' ), array( 'offset' => 0, 'length' => 25 ), false );
            foreach ( (array)$rows as $row )
            {
                $objectId = (int)$row['content_id'];
                $version = (int)$row['content_version'];
                $workflow = eZWorkflow::fetch( (int)$row['workflow_id'] );
                $object = $objectId ? eZContentObject::fetch( $objectId ) : null;
                $failed[] = array( 'id' => (int)$row['id'], 'title' => $object ? expCollaborationInbox::approvalTitle( $objectId, $version ) : '',
                                   'workflow' => $workflow ? (string)$workflow->attribute( 'name' ) : '',
                                   'modified' => (int)$row['modified'], 'objectId' => $objectId, 'version' => $version,
                                   'edit' => $object && $version ? self::url( '/content/edit/' . $objectId . '/' . $version ) : null );
            }
        }
        return array( 'comments' => $comments, 'results' => $results, 'failed' => $failed );
    }

    /** @return string|null the item in the administration's collaboration view, where it is read and answered */
    protected static function adminItemUrl( $id )
    {
        $base = self::adminUrl();
        return $base ? $base . 'collaboration/item/full/' . (int)$id : null;
    }

    /**
     * Marks a collaboration item as read for the user, as the kernel's collaboration view does on opening it
     * (eZCollaborationItem::setLastRead); only an item the user takes part in.
     */
    public function markRead( array $in )
    {
        $item = isset( $in['itemId'] ) ? eZCollaborationItem::fetch( (int)$in['itemId'] ) : null;
        if ( !$item instanceof eZCollaborationItem || !$item->userIsParticipant( $this->currentUser() ) )
            throw new expwtApiError( 404, 'not_found', 'There is no such item.' );
        $item->setLastRead( $this->userId() );
        return $this->noticeCount( true );
    }

    /** @return bool the user is an approver of the collaboration item (the role the kernel's handler asks for) */
    protected static function isApprover( $itemId, $userId )
    {
        foreach ( (array)eZCollaborationItemParticipantLink::fetchParticipantList( array( 'item_id' => (int)$itemId ) ) as $participant )
        {
            if ( (int)$participant->attribute( 'participant_id' ) === (int)$userId
                 && (int)$participant->attribute( 'participant_role' ) === eZCollaborationItemParticipantLink::ROLE_APPROVER )
                return true;
        }
        return false;
    }

    /**
     * Approves or denies an approval, with a comment: through the kernel's own view collaboration/action, run here
     * with the fields its form posts (CollaborationActionCustom, CollaborationTypeIdentifier, CollaborationItemID,
     * CollaborationAction_Accept or _Deny, Collaboration_ApproveComment). The view checks the user may open the item,
     * its approve handler that the user is one of the approvers and that it still waits, records the comment and the
     * decision, and the publishing workflow goes on as after a decision in the administration. Nothing of it is done
     * here a second time.
     */
    public function decide( array $in )
    {
        $decision = isset( $in['decision'] ) ? (string)$in['decision'] : '';
        $actions = array( 'approve' => 'Accept', 'deny' => 'Deny' );
        $item = isset( $in['itemId'] ) ? eZCollaborationItem::fetch( (int)$in['itemId'] ) : null;
        if ( !isset( $actions[$decision] ) )
            throw new expwtApiError( 400, 'bad_input', 'This value does not fit the field.' );
        if ( !$item instanceof eZCollaborationItem || (string)$item->attribute( 'type_identifier' ) !== 'ezapprove' )
            throw new expwtApiError( 404, 'not_found', 'This page is not there, or you may not read it.' );
        $module = $this->collaborates() ? eZModule::exists( 'collaboration' ) : null;
        $params = array();
        if ( !$module instanceof eZModule || !$this->currentUser()->hasAccessToView( $module, 'action', $params ) )
            throw new expwtApiError( 403, 'forbidden', 'You may not do this.' );

        $post = array( 'CollaborationActionCustom' => '1', 'CollaborationTypeIdentifier' => 'ezapprove',
                       'CollaborationItemID' => (string)(int)$item->attribute( 'id' ),
                       'CollaborationAction_' . $actions[$decision] => '1',
                       'Collaboration_ApproveComment' => isset( $in['comment'] ) ? (string)$in['comment'] : '' );
        $before = $_POST;
        expCollaborationGroupManager::takeNotice();
        try
        {
            $_POST = $post + $_POST;
            $module->run( 'action', array() );
        }
        finally
        {
            $_POST = $before;
        }
        $notice = expCollaborationGroupManager::takeNotice();
        $item = eZCollaborationItem::fetch( (int)$item->attribute( 'id' ) );
        $state = $item ? expCollaborationInbox::stateOf( $item ) : '';
        $wanted = $decision === 'approve' ? expCollaborationInbox::STATE_APPROVED : expCollaborationInbox::STATE_DENIED;
        if ( $state !== $wanted )
        {
            // the kernel's own reason (not an approver, decided already ...), in the user's language
            throw new expwtApiError( 409, 'not_decided', $notice && $notice['text'] !== '' ? (string)$notice['text'] : 'The approval could not be decided.' );
        }
        $counts = $this->noticeCount( true );
        return array( 'state' => $state, 'message' => $notice ? (string)$notice['text'] : '', 'waiting' => $counts['waiting'], 'count' => $counts['count'] );
    }

    /**
     * @return string a field's label: the class attribute's name; for one without a name in any language (a field
     *                added by a script) the name of its datatype, so no field of the sidebar is without a label
     */
    public static function fieldName( $classAttribute, $identifier )
    {
        $name = $classAttribute ? trim( (string)$classAttribute->attribute( 'name' ) ) : '';
        if ( $name === '' && $classAttribute && $classAttribute->dataType() )
        {
            $information = $classAttribute->dataType()->attribute( 'information' );
            $name = isset( $information['name'] ) ? trim( (string)$information['name'] ) : '';
        }
        return $name !== '' ? $name : (string)$identifier;
    }

    /**
     * @return string the category of a class attribute (content.ini [ClassAttributeSettings] CategoryList); the
     *                default category for none, or for one that is not in the list
     */
    public static function categoryOf( $classAttribute )
    {
        $ini = eZINI::instance( 'content.ini' );
        $default = (string)$ini->variable( 'ClassAttributeSettings', 'DefaultCategory' );
        $category = $classAttribute ? (string)$classAttribute->attribute( 'category' ) : '';
        $list = $ini->hasVariable( 'ClassAttributeSettings', 'CategoryList' ) ? (array)$ini->variable( 'ClassAttributeSettings', 'CategoryList' ) : array();
        return $category !== '' && isset( $list[$category] ) ? $category : $default;
    }

    /**
     * @return array the categories the Page panel groups the fields by: the default one first (its fields are not in
     *               a group), then the others in the order of content.ini CategoryList, each with its name in the
     *               user's language where the sidebar's catalogue has it ("Meta")
     */
    public static function categories()
    {
        $ini = eZINI::instance( 'content.ini' );
        $default = (string)$ini->variable( 'ClassAttributeSettings', 'DefaultCategory' );
        $out = array( array( 'id' => $default, 'name' => '', 'default' => true ) );
        $list = $ini->hasVariable( 'ClassAttributeSettings', 'CategoryList' ) ? (array)$ini->variable( 'ClassAttributeSettings', 'CategoryList' ) : array();
        foreach ( $list as $id => $name )
        {
            if ( (string)$id !== $default )
                $out[] = array( 'id' => (string)$id, 'name' => ezpI18n::tr( expwtStrings::CONTEXT, (string)$name ), 'default' => false );
        }
        return $out;
    }

    /**
     * Saves fields into the user's draft (made on the first save). When someone published a newer version since the
     * editor loaded the page, the fields are merged field by field (expwtMerge); a field both changed differently is
     * answered as a conflict (409) for the editor to choose.
     */
    public function saveDraft( array $in )
    {
        $object = $this->readableObject( $in );
        $language = isset( $in['language'] ) ? $in['language'] : (string)$object->attribute( 'initial_language_code' );
        $draft = isset( $in['version'] ) ? $this->ownDraft( $object, $in['version'] ) : null;
        if ( isset( $in['version'] ) && !$draft )
            throw new expwtApiError( 404, 'not_found', 'This draft is not there any more.' );
        $source = $draft ?: $object->version( (int)$object->attribute( 'current_version' ) );
        $known = array();
        foreach ( $this->fieldsOf( $source, $language ) as $field )
            $known[$field['identifier']] = $field;

        $values = array();
        foreach ( $in['fields'] as $identifier => $value )
        {
            $identifier = (string)$identifier;
            if ( !isset( $known[$identifier] ) || !$known[$identifier]['editable'] )
                throw new expwtApiError( 400, 'bad_input', 'This field cannot be edited here.', array( 'field' => $identifier ) );
            $string = $known[$identifier]['input'] === 'richtext' ? (string)$value : expwtFields::toStringForm( $known[$identifier], $value );
            if ( $string === false )
                throw new expwtApiError( 400, 'bad_value', 'This value does not fit the field.', array( 'field' => $identifier ) );
            $values[$identifier] = $string;
        }

        $current = (int)$object->attribute( 'current_version' );
        if ( isset( $in['publishedVersion'] ) && (int)$in['publishedVersion'] !== $current && (int)$object->attribute( 'status' ) === eZContentObject::STATUS_PUBLISHED )
        {
            $theirs = array();
            foreach ( $this->fieldsOf( $object->version( $current ), $language ) as $field )
                $theirs[$field['identifier']] = $field['value'];
            $merge = expwtMerge::merge( $in['base'], $values, $theirs, $in['choices'] );
            if ( $merge['conflicts'] )
                throw new expwtApiError( 409, 'conflict', 'Someone else changed these fields in the meantime.',
                                         array( 'conflicts' => $merge['conflicts'], 'publishedVersion' => $current ) );
            $values = $merge['merged'];
            if ( !$values )
                return array( 'status' => 'UNCHANGED', 'version' => $draft ? (int)$draft->attribute( 'version' ) : null, 'publishedVersion' => $current );
        }

        $inputs = array();
        foreach ( $values as $identifier => $value )
            $inputs[] = array( 'identifier' => $identifier, 'value' => $value,
                               'format' => $known[$identifier]['input'] === 'richtext' ? 'HTML' : 'RAW' );
        $writer = $this->writer();
        $result = $draft ? $writer->updateDraft( (int)$object->attribute( 'id' ), (int)$draft->attribute( 'version' ), $language, $inputs )
                         : $writer->update( array( 'id' => (int)$object->attribute( 'id' ), 'fields' => $inputs, 'language' => $language, 'publish' => false ) );
        $version = (int)$result['version'];
        return array( 'status' => 'DRAFT', 'version' => $version, 'publishedVersion' => $current,
                      'previewUrl' => self::url( '/content/versionview/' . (int)$object->attribute( 'id' ) . '/' . $version . '/' . $language ) );
    }

    /**
     * Publishes the user's draft now, or at publishAt, and takes the page down at unpublishAt, through the kernel's
     * mechanism (F21, expContentSchedule): the dates go into the draft's date fields and the draft is published
     * through content/publish, where the wait-until-date event holds it until publishAt (SCHEDULED) and the hide
     * cronjob takes it down at unpublishAt. A workflow (an approval) may hold the publish: PENDING.
     */
    public function publish( array $in )
    {
        $object = $this->readableObject( $in );
        $draft = isset( $in['version'] ) ? $this->ownDraft( $object, $in['version'] ) : null;
        if ( !$draft )
            throw new expwtApiError( 404, 'not_found', 'This draft is not there any more.' );
        $language = (string)$draft->initialLanguageCode();
        $missing = expwtWriter::missingRequired( $draft, $language );
        if ( $missing )
            throw new expwtApiError( 400, 'required', 'Fill in the required fields first.', array( 'fields' => $missing ) );
        $id = (int)$object->attribute( 'id' );
        $now = time();
        $dates = expwtSchedule::request( $in );
        if ( $dates['error'] !== null )
            throw new expwtApiError( 400, 'bad_value', 'This is not a date and time.' );
        if ( !$object->canEdit( false, false, false, $language ) )
            throw new expwtApiError( 403, 'forbidden', 'You may not do this.' );
        if ( class_exists( 'expContentSchedule' ) )
        {
            $can = expContentSchedule::forObject( $object );
            $current = $can['publish'] ? expContentSchedule::dateOf( $draft, $can['publish']['identifier'] ) : 0;
            $plan = expContentSchedule::plan( $can['publish'], $can['takeDown'], $dates['at'], $dates['until'], $now, $current );
        }
        else
        {
            // a kernel without the shared mechanism: publish now only
            $later = ( $dates['at'] !== null && $dates['at'] > $now ) || $dates['until'];
            $plan = array( 'writes' => array(), 'deferred' => false, 'error' => $later ? 'no_publish_date' : null );
        }
        if ( $plan['error'] === 'no_publish_date' || $plan['error'] === 'no_take_down_date' )
            throw new expwtApiError( 400, 'not_schedulable', 'Not possible here: this kind of page has no date field for it.' );
        if ( $plan['error'] !== null )
            throw new expwtApiError( 400, 'bad_value', 'The page would be taken down before it is published.' );
        if ( $plan['writes'] )
            expContentSchedule::setDates( $draft, $plan['writes'] );
        $result = $this->writer()->publishDraft( $id, (int)$draft->attribute( 'version' ) );
        expwtLock::release( $id, $this->userId() );
        $until = $dates['until'] ? $dates['until'] : null;
        if ( $plan['deferred'] && (string)$result['status'] === 'PENDING' )
            return array( 'status' => 'SCHEDULED', 'publishAt' => $dates['at'], 'unpublishAt' => $until );
        $node = !empty( $result['nodeId'] ) ? eZContentObjectTreeNode::fetch( (int)$result['nodeId'] ) : null;
        return array( 'status' => (string)$result['status'], 'nodeId' => $result['nodeId'], 'unpublishAt' => $until,
                      'url' => $node instanceof eZContentObjectTreeNode ? self::url( '/' . ltrim( (string)$node->attribute( 'url_alias' ), '/' ) ) : null );
    }

    /**
     * The Page panel's "Publish at" and "Take down at" (expwtSchedule::view()): the date fields of the version's class
     * the kernel's mechanism reads, with the dates the version carries; neither on a kernel without the mechanism.
     *
     * @return array publish, takeDown
     */
    protected static function scheduleView( eZContentObject $object, $version )
    {
        if ( !class_exists( 'expContentSchedule' ) || !$version instanceof eZContentObjectVersion )
            return array( 'publish' => null, 'takeDown' => null );
        $can = expContentSchedule::forObject( $object );
        $publish = $can['publish'] ? expContentSchedule::dateOf( $version, $can['publish']['identifier'] ) : 0;
        $takeDown = $can['takeDown'] ? expContentSchedule::dateOf( $version, $can['takeDown']['identifier'] ) : 0;
        return expwtSchedule::view( $can, $publish, $takeDown, time() );
    }

    /** Throws the user's draft away; a new object that was never published goes with it. */
    public function discard( array $in )
    {
        $object = $this->readableObject( $in, true );
        $draft = isset( $in['version'] ) ? $this->ownDraft( $object, $in['version'] ) : null;
        if ( !$draft )
            throw new expwtApiError( 404, 'not_found', 'This draft is not there any more.' );
        $db = eZDB::instance();
        $db->begin();
        if ( (int)$object->attribute( 'status' ) === eZContentObject::STATUS_DRAFT && count( $object->versions() ) <= 1 )
            $object->purge();
        else
            $draft->removeThis();
        $db->commit();
        expwtLock::release( (int)$object->attribute( 'id' ), $this->userId() );
        return array( 'status' => 'DISCARDED' );
    }

    public function lock( array $in )
    {
        $object = $this->readableObject( $in );
        $user = $this->user();
        $decision = expwtLock::decide( expwtLock::load( (int)$object->attribute( 'id' ) ), $user['id'], $user['name'], time(),
                                       $this->settings['lock_seconds'], !empty( $in['takeOver'] ) );
        if ( $decision['changed'] )
            expwtLock::store( (int)$object->attribute( 'id' ), $decision['lock'] );
        return array( 'mine' => $decision['held_by_other'] === null,
                      'heldBy' => $decision['held_by_other'] ? array( 'name' => $decision['held_by_other']['name'], 'since' => (int)$decision['held_by_other']['since'] ) : null,
                      'until' => (int)$decision['lock']['until'] );
    }

    public function unlock( array $in )
    {
        $object = $this->readableObject( $in );
        return array( 'released' => expwtLock::release( (int)$object->attribute( 'id' ), $this->userId() ) );
    }

    // ------------------------------------------------------------------------------------------------ create

    /** The classes the user may create below this page (children) and beside it (siblings), as content/create allows */
    public function createClasses( array $in )
    {
        $node = $this->readableNode( $in );
        $parent = $node->attribute( 'parent_node_id' ) > 1 ? eZContentObjectTreeNode::fetch( (int)$node->attribute( 'parent_node_id' ) ) : null;
        return array( 'nodeId' => (int)$node->attribute( 'node_id' ),
                      'children' => $this->creatable( $node ),
                      'parentId' => $parent instanceof eZContentObjectTreeNode && $parent->canRead() ? (int)$parent->attribute( 'node_id' ) : null,
                      'siblings' => $parent instanceof eZContentObjectTreeNode && $parent->canRead() ? $this->creatable( $parent ) : array() );
    }

    protected function creatable( eZContentObjectTreeNode $node )
    {
        $out = array();
        if ( !$node->canCreate() )
            return $out;
        foreach ( (array)$node->canCreateClassList( true ) as $class )
        {
            if ( !$class instanceof eZContentClass )
                continue;
            $identifier = (string)$class->attribute( 'identifier' );
            if ( in_array( $identifier, $this->settings['hidden_classes'], true ) || !expGraphQLClassCatalogue::cached()->classByIdentifier( $identifier ) )
                continue;
            $out[] = array( 'identifier' => $identifier, 'name' => (string)$class->attribute( 'name' ) );
        }
        usort( $out, function ( $a, $b ) { return strcasecmp( $a['name'], $b['name'] ); } );
        return $out;
    }

    /** A new draft below parentId with only its title (the class's name field), then edited in the sidebar */
    public function createNew( array $in )
    {
        if ( !isset( $in['parentId'], $in['classIdentifier'], $in['title'] ) )
            throw new expwtApiError( 400, 'bad_input', 'Give a title.' );
        $class = eZContentClass::fetchByIdentifier( $in['classIdentifier'] );
        if ( !$class instanceof eZContentClass || in_array( $in['classIdentifier'], $this->settings['hidden_classes'], true ) )
            throw new expwtApiError( 404, 'not_found', 'There is no such class.' );
        $titleField = self::titleField( $class );
        $fields = $titleField !== null ? array( array( 'identifier' => $titleField, 'value' => $in['title'], 'format' => 'RAW' ) ) : array();
        $result = $this->writer()->createDraft( array( 'parentId' => $in['parentId'], 'classIdentifier' => $in['classIdentifier'], 'fields' => $fields )
                                                + ( isset( $in['language'] ) ? array( 'language' => $in['language'] ) : array() ) );
        return array( 'status' => 'DRAFT', 'objectId' => (int)$result['contentId'], 'version' => (int)$result['version'] );
    }

    /** @return string|null the first text field of the class (the one its name pattern starts with, as a rule) */
    public static function titleField( eZContentClass $class )
    {
        $pattern = (string)$class->attribute( 'contentobject_name' );
        if ( preg_match( '/<([a-z0-9_]+)/', $pattern, $m ) )
            return $m[1];
        foreach ( (array)$class->fetchAttributes() as $attribute )
            if ( $attribute->attribute( 'data_type_string' ) === 'ezstring' )
                return (string)$attribute->attribute( 'identifier' );
        return null;
    }

    // ------------------------------------------------------------------------------------------------ structure

    /** The pages below a page (the site-side picker and the sort list): 50 at a time */
    public function children( array $in )
    {
        $node = $this->readableNode( $in );
        $offset = isset( $in['offset'] ) ? (int)$in['offset'] : 0;
        $list = array();
        foreach ( (array)$node->subTree( array( 'Depth' => 1, 'DepthOperator' => 'eq', 'Limit' => 50, 'Offset' => $offset,
                                                'SortBy' => $node->sortArray() ) ) as $child )
        {
            $list[] = array( 'nodeId' => (int)$child->attribute( 'node_id' ), 'name' => (string)$child->attribute( 'name' ),
                             'class' => (string)$child->attribute( 'class_identifier' ), 'container' => (bool)$child->attribute( 'is_container' ),
                             'hidden' => (bool)$child->attribute( 'is_hidden' ), 'priority' => (int)$child->attribute( 'priority' ) );
        }
        return array( 'nodeId' => (int)$node->attribute( 'node_id' ), 'name' => (string)$node->attribute( 'name' ),
                      'parentId' => (int)$node->attribute( 'parent_node_id' ), 'total' => (int)$node->subTreeCount( array( 'Depth' => 1, 'DepthOperator' => 'eq' ) ),
                      'offset' => $offset, 'children' => $list,
                      'sortable' => (int)$node->attribute( 'sort_field' ) === eZContentObjectTreeNode::SORT_FIELD_PRIORITY && $node->canEdit() );
    }

    public function move( array $in )
    {
        if ( !isset( $in['nodeId'], $in['parentId'] ) )
            throw new expwtApiError( 400, 'bad_input', 'Where to?' );
        $r = $this->writer()->move( $in['nodeId'], $in['parentId'] );
        return array( 'status' => (string)$r['status'], 'nodeId' => $r['nodeId'] );
    }

    /** A copy of the page below a parent (its own: beside it); the copy's address to go to. */
    public function copy( array $in )
    {
        if ( !isset( $in['nodeId'], $in['parentId'] ) )
            throw new expwtApiError( 400, 'bad_input', 'Where to?' );
        $r = $this->writer()->copy( $in['nodeId'], $in['parentId'] );
        $node = !empty( $r['nodeId'] ) ? eZContentObjectTreeNode::fetch( (int)$r['nodeId'] ) : null;
        return array( 'status' => (string)$r['status'], 'nodeId' => $r['nodeId'],
                      'url' => $node instanceof eZContentObjectTreeNode ? self::url( '/' . ltrim( (string)$node->attribute( 'url_alias' ), '/' ) ) : null );
    }

    public function addLocation( array $in )
    {
        $node = $this->readableNode( $in );
        if ( !isset( $in['parentId'] ) )
            throw new expwtApiError( 400, 'bad_input', 'Where to?' );
        $r = $this->writer()->addLocation( (int)$node->attribute( 'contentobject_id' ), $in['parentId'] );
        return array( 'status' => (string)$r['status'], 'nodeId' => $r['nodeId'] );
    }

    public function visibility( array $in, $hide )
    {
        $node = $this->readableNode( $in );
        $r = $this->writer()->visibility( (int)$node->attribute( 'node_id' ), (bool)$hide );
        return array( 'status' => (string)$r['status'], 'hidden' => (bool)$hide );
    }

    public function trash( array $in )
    {
        $node = $this->readableNode( $in );
        $parent = (int)$node->attribute( 'parent_node_id' );
        $r = $this->writer()->remove( array( 'nodeId' => (int)$node->attribute( 'node_id' ), 'trash' => true ) );
        $parentNode = eZContentObjectTreeNode::fetch( $parent );
        return array( 'status' => (string)$r['status'],
                      'parentUrl' => $parentNode instanceof eZContentObjectTreeNode ? self::url( '/' . ltrim( (string)$parentNode->attribute( 'url_alias' ), '/' ) ) : self::url( '/' ) );
    }

    /** New priorities for the children of a page sorted by priority, in the order the editor dragged them to */
    public function sort( array $in )
    {
        $node = $this->readableNode( $in );
        if ( (int)$node->attribute( 'sort_field' ) !== eZContentObjectTreeNode::SORT_FIELD_PRIORITY )
            throw new expwtApiError( 400, 'not_priority_sorted', 'The pages here are not sorted by priority.' );
        if ( !$node->canEdit() )
            throw new expwtApiError( 403, 'forbidden', 'You may not do this.' );
        $children = array();
        foreach ( (array)$node->subTree( array( 'Depth' => 1, 'DepthOperator' => 'eq', 'Limit' => 1000 ) ) as $child )
            $children[(int)$child->attribute( 'node_id' )] = true;
        $priorities = expwtSchedule::priorities( $in['order'], array_keys( $children ) );
        if ( !$priorities )
            throw new expwtApiError( 400, 'bad_input', 'This is not the list of pages here.' );
        $ids = array_keys( $priorities );
        $values = array_values( $priorities );
        if ( eZOperationHandler::operationIsAvailable( 'content_updatepriority' ) )
            eZOperationHandler::execute( 'content', 'updatepriority', array( 'node_id' => (int)$node->attribute( 'node_id' ), 'priority' => $values, 'priority_id' => $ids ), null, true );
        else
            eZContentOperationCollection::updatePriority( (int)$node->attribute( 'node_id' ), $values, $ids );
        eZContentCacheManager::clearContentCache( (int)$node->attribute( 'contentobject_id' ) );
        return array( 'status' => 'DONE', 'priorities' => $priorities );
    }

    // ------------------------------------------------------------------------------------------------ helpers

    /** @return expwtWriter */
    protected function writer()
    {
        if ( $this->writer === null )
            $this->writer = expwtWriter::forCurrentUser();
        return $this->writer;
    }

    /** @return eZContentObjectTreeNode a page the user may read */
    protected function readableNode( array $in )
    {
        $node = isset( $in['nodeId'] ) ? eZContentObjectTreeNode::fetch( $in['nodeId'] ) : null;
        if ( !$node instanceof eZContentObjectTreeNode || !$node->canRead() )
            throw new expwtApiError( 404, 'not_found', 'This page is not there, or you may not read it.' );
        return $node;
    }

    /**
     * @param array $in objectId (and version)
     * @param bool $draftOnly a new object's draft counts when it is the user's own
     * @return eZContentObject an object the user may read, or whose draft is theirs
     */
    protected function readableObject( array $in, $draftOnly = false )
    {
        $object = isset( $in['objectId'] ) ? eZContentObject::fetch( $in['objectId'] ) : null;
        if ( !$object instanceof eZContentObject )
            throw new expwtApiError( 404, 'not_found', 'This page is not there, or you may not read it.' );
        if ( !$object->canRead() && !( isset( $in['version'] ) && $this->ownDraft( $object, $in['version'] ) ) )
            throw new expwtApiError( 404, 'not_found', 'This page is not there, or you may not read it.' );
        return $object;
    }

    /** @return eZContentObjectVersion|null the version when it is a draft of the current user */
    protected function ownDraft( eZContentObject $object, $versionNumber )
    {
        $version = (int)$versionNumber > 0 ? $object->version( (int)$versionNumber ) : null;
        if ( !$version instanceof eZContentObjectVersion || (int)$version->attribute( 'creator_id' ) !== $this->userId() )
            return null;
        return in_array( (int)$version->attribute( 'status' ), array( eZContentObjectVersion::STATUS_DRAFT, eZContentObjectVersion::STATUS_INTERNAL_DRAFT ), true )
               ? $version : null;
    }

    /** @return eZContentObjectVersion|null the newest draft of the current user in the language */
    protected function latestOwnDraft( eZContentObject $object, $language )
    {
        $found = null;
        foreach ( (array)$object->versions() as $version )
        {
            if ( (int)$version->attribute( 'creator_id' ) !== $this->userId() || (int)$version->attribute( 'status' ) !== eZContentObjectVersion::STATUS_DRAFT
                 || (string)$version->initialLanguageCode() !== (string)$language )
                continue;
            if ( !$found || (int)$version->attribute( 'version' ) > (int)$found->attribute( 'version' ) )
                $found = $version;
        }
        return $found;
    }

    /** @return bool the user may use a kernel view (by its module's policy functions) */
    protected function viewExists( $uri )
    {
        $parts = explode( '/', trim( $uri, '/' ) );
        $module = eZModule::exists( $parts[0] );
        if ( !$module instanceof eZModule || !isset( $parts[1] ) )
            return false;
        $views = $module->attribute( 'views' );
        return isset( $views[$parts[1]] );
    }

    /** @return bool the current user has module/function */
    protected function may( $module, $function )
    {
        $access = $this->currentUser()->hasAccessTo( $module, $function );
        return isset( $access['accessWord'] ) && $access['accessWord'] !== 'no';
    }

    /** @return string an address of this siteaccess (with its prefix) */
    public static function url( $path )
    {
        $url = $path;
        eZURI::transformURI( $url );
        return $url;
    }

    /** @return string|null the administration's address (site.ini [SiteSettings] AdminSiteURL... first admin siteaccess) */
    protected static function adminUrl()
    {
        $ini = eZINI::instance();
        if ( $ini->hasVariable( 'SiteAccessSettings', 'AdminSiteAccessList' ) )
        {
            $list = (array)$ini->variable( 'SiteAccessSettings', 'AdminSiteAccessList' );
            if ( $list )
                return '/' . reset( $list ) . '/';
        }
        return null;
    }
}
