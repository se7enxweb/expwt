<?php
/**
 * The kernel's content writer of the GraphQL API (expGraphQLWriter: the kernel's operations, the user's rights
 * checked before anything is written, the workflows run on publish) with what the sidebar adds to it:
 *
 *   updateDraft()   writes into a draft the user already has, instead of making a new version for every save, so an
 *                   editing session is one draft however often it is saved (decision 11: always on a draft)
 *   createDraft()   a new object left a draft with only the fields given (a quick title), the required ones are
 *                   asked for before it is published (missingRequired())
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

class expwtWriter extends expGraphQLWriter
{
    /**
     * @return expwtWriter for the current user, with the classes the GraphQL API shows
     */
    public static function forCurrentUser()
    {
        $catalogue = expGraphQLClassCatalogue::cached();
        return new self( new expGraphQLRepository( $catalogue ), $catalogue, eZUser::currentUser() );
    }

    /**
     * @param int $id the object
     * @param int $versionNumber a draft of the user
     * @param string $language
     * @param array $fields FieldInput values: identifier, value, format
     * @return array as update() with publish false
     */
    public function updateDraft( $id, $versionNumber, $language, array $fields )
    {
        $object = eZContentObject::fetch( (int)$id );
        $version = $object instanceof eZContentObject ? $object->version( (int)$versionNumber ) : null;
        if ( !$version instanceof eZContentObjectVersion || !$object->canRead() )
            throw self::notFound();
        if ( (int)$version->attribute( 'creator_id' ) !== (int)$this->userId()
             || !in_array( (int)$version->attribute( 'status' ), array( eZContentObjectVersion::STATUS_DRAFT, eZContentObjectVersion::STATUS_INTERNAL_DRAFT ), true ) )
            throw self::forbidden();
        $language = $language ?: (string)$version->initialLanguageCode();
        if ( !$object->canEdit( false, false, false, $language ) )
            throw self::forbidden();
        $class = $this->exposedClass( (string)$object->attribute( 'class_identifier' ) );
        $prepared = expGraphQLFieldInput::prepare( $class, $fields, false );
        $db = eZDB::instance();
        $db->begin();
        try
        {
            $version->setAttribute( 'modified', time() );
            $version->store();
            $this->writeFields( $version->contentObjectAttributes( $language ), $prepared, (int)$object->attribute( 'id' ) );
            $db->commit();
        }
        catch ( Exception $e )
        {
            $db->rollback();
            throw $e;
        }
        return $this->finish( $object, (int)$versionNumber, $language, false );
    }

    /**
     * @param array $args parentId, classIdentifier, fields, language
     * @return array as create() with publish false; the required fields may still be empty
     */
    public function createDraft( array $args )
    {
        $parent = $this->readableNode( $args['parentId'] );
        $class = $this->exposedClass( (string)$args['classIdentifier'] );
        $classObject = eZContentClass::fetchByIdentifier( $class['identifier'] );
        if ( !$classObject instanceof eZContentClass )
            throw self::notFound();
        $language = $this->languageOf( isset( $args['language'] ) ? $args['language'] : null, null );
        if ( $parent->checkAccess( 'create', (int)$classObject->attribute( 'id' ), (int)$parent->attribute( 'contentclass_id' ), false, $language, $this->userId() ) != 1 )
            throw self::forbidden();
        $fields = expGraphQLFieldInput::prepare( $class, isset( $args['fields'] ) ? (array)$args['fields'] : array(), false );
        $db = eZDB::instance();
        $db->begin();
        try
        {
            $object = $classObject->instantiate( $this->userId(), 0, false, $language );
            $object->store();
            eZNodeAssignment::create( array( 'contentobject_id' => $object->attribute( 'id' ),
                                             'contentobject_version' => $object->attribute( 'current_version' ),
                                             'parent_node' => (int)$parent->attribute( 'node_id' ),
                                             'is_main' => 1,
                                             'sort_field' => $classObject->attribute( 'sort_field' ),
                                             'sort_order' => $classObject->attribute( 'sort_order' ) ) )->store();
            $version = $object->version( 1 );
            $version->setAttribute( 'modified', time() );
            $version->setAttribute( 'status', eZContentObjectVersion::STATUS_DRAFT );
            $version->store();
            $this->writeFields( $version->contentObjectAttributes( $language ), $fields, (int)$object->attribute( 'id' ) );
            $db->commit();
        }
        catch ( Exception $e )
        {
            $db->rollback();
            throw $e;
        }
        return $this->finish( $object, 1, $language, false );
    }

    /**
     * @param eZContentObjectVersion $version
     * @param string $language
     * @return string[] identifiers of required fields without content
     */
    public static function missingRequired( eZContentObjectVersion $version, $language )
    {
        $missing = array();
        foreach ( (array)$version->contentObjectAttributes( $language ) as $attribute )
        {
            $classAttribute = $attribute->contentClassAttribute();
            if ( $classAttribute && $classAttribute->attribute( 'is_required' ) && !$attribute->hasContent() )
                $missing[] = (string)$attribute->attribute( 'contentclass_attribute_identifier' );
        }
        return $missing;
    }
}
