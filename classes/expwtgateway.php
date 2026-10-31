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
