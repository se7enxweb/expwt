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


    // ------------------------------------------------------------------------------------------------ the inbox

    /** @return bool the user may use the kernel's collaboration (the module's policy), where the approvals are */
    public function collaborates()
    {
        if ( !$this->currentUser()->isRegistered() || !class_exists( 'expCollaborationInbox' ) )
            return false;
        $access = $this->currentUser()->hasAccessTo( 'collaboration' );
        return isset( $access['accessWord'] ) && $access['accessWord'] !== 'no';
    }

    /** @return int how many approvals wait for the user (the count on the closed tab, F29); 0 without collaboration */
    public function waitingCount()
    {
        if ( !$this->collaborates() )
            return 0;
        $inbox = expCollaborationInbox::fetch( array( 'status' => 'waiting', 'role' => 'approver', 'type' => 'ezapprove', 'limit' => 1 ) );
        return (int)$inbox['total'];
    }

    /**
     * The inbox (F29): the approvals of the kernel's approve workflow (collaboration items of ezapprove) that wait for
     * the user, read through the kernel's collaboration inbox (expCollaborationInbox), newest first. Each with the
     * page it is about, its author, the last comment, whether the user is one of its approvers (only they decide it),
     * and the draft's preview (content/versionview, which checks the user may read that version).
     */
    public function inbox( array $in )
    {
        if ( !$this->collaborates() )
            throw new expwtApiError( 403, 'forbidden', 'You may not do this.' );
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
        return array( 'total' => (int)$inbox['total'], 'offset' => (int)$inbox['offset'], 'items' => $items );
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
        return array( 'state' => $state, 'message' => $notice ? (string)$notice['text'] : '', 'waiting' => $this->waitingCount() );
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
